<?php
declare(strict_types=1);

namespace App\Services\Moodle;

use App\Services\LoggerService;
use Config\MoodleWS;
use PDO;

/**
 * Servicio especializado en sincronización de Cursos y Calificaciones.
 */
class CourseSyncService extends MoodleBaseSyncService {

    /**
     * FASE 2: Sincronizar Cursos
     */
    public function sincronizar(): array {
        $this->stats['start_time'] = microtime(true);
        
        try {
            $allCourses = $this->client->getAllCourses();
            $this->stateService->updateProgress(20, "Procesando " . count($allCourses) . " cursos...");
            
            $result = $this->bulkDb->bulkUpsertCourses($allCourses);
            
            $this->stats = array_merge($this->stats, $result);
            $this->stats['total_from_moodle'] = count($allCourses);
            $this->stats['time_seconds'] = round(microtime(true) - $this->stats['start_time'], 2);
            
            LoggerService::info("Cursos sincronizados", $this->stats);
            return $this->stats;
            
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando cursos", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Sincronizar CALIFICACIONES
     */
    public function sincronizarCalificaciones(?array $courseIds = null): array {
        $this->stats['start_time'] = microtime(true);
        $gradeStats = ['courses_processed' => 0, 'grades_saved' => 0, 'errors' => 0];
        
        try {
            if ($courseIds === null) {
                $currentYear = (int)date('Y');
                $stmt = $this->db->prepare(
                    "SELECT id_moodle FROM cursos 
                     WHERE visible = 1 
                     AND YEAR(start_date) >= ? 
                     ORDER BY start_date DESC"
                );
                $stmt->execute([$currentYear - 1]);
                $courseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            if (empty($courseIds)) return $gradeStats;
            
            $parallelCapacity = MoodleWS::getParallelRequests();
            $this->stateService->updateProgress(0, "Calificaciones: " . count($courseIds) . " cursos...");
            
            $chunks = array_chunk($courseIds, 20);
            $processedCourses = 0;
            
            foreach ($chunks as $courseChunk) {
                if ($this->stateService->shouldStop()) break;
                
                foreach ($courseChunk as $courseId) {
                    try {
                        $courseResult = $this->procesarCalificacionesCurso((int)$courseId);
                        $gradeStats['courses_processed']++;
                        $gradeStats['grades_saved'] += $courseResult['saved'] ?? 0;
                        $gradeStats['errors'] += $courseResult['errors'] ?? 0;
                    } catch (\Exception $e) {
                        $gradeStats['errors']++;
                        LoggerService::warning("Error calificaciones curso $courseId", ['error' => $e->getMessage()]);
                    }
                }
                
                $processedCourses += count($courseChunk);
                $percent = (int)(($processedCourses / count($courseIds)) * 100);
                $this->stateService->updateProgress($percent, "Calificaciones: $processedCourses/" . count($courseIds) . "...");
            }
            
            $gradeStats['time_seconds'] = round(microtime(true) - $this->stats['start_time'], 2);
            return $gradeStats;
            
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando calificaciones", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function procesarCalificacionesCurso(int $courseId): array {
        $stats = ['saved' => 0, 'errors' => 0];
        
        $enrolled = $this->client->getEnrolledUsers($courseId);
        if (empty($enrolled)) return $stats;
        
        $students = [];
        foreach ($enrolled as $u) {
            $isStudent = false;
            foreach ($u['roles'] ?? [] as $r) {
                if (($r['shortname'] ?? '') === 'student') {
                    $isStudent = true;
                    break;
                }
            }
            if ($isStudent && isset($u['id'])) $students[] = $u;
        }
        
        if (empty($students)) return $stats;
        
        $studentChunks = array_chunk($students, 500);
        foreach ($studentChunks as $chunkStudents) {
            $gradePairs = [];
            foreach ($chunkStudents as $student) {
                $gradePairs[] = ['courseId' => $courseId, 'userId' => (int)$student['id']];
            }
            
            $gradesResponse = $this->parallelClient->getGradesParallel($gradePairs);
            if (empty($gradesResponse['results'])) continue;
            
            $chunkStudentIds = array_column($chunkStudents, 'id');
            $enrollmentMap = $this->bulkDb->getEnrollmentIdMap([$courseId], $chunkStudentIds);
            
            // Self-Healing
            $this->repairMissingEnrollments($courseId, $chunkStudentIds, $enrollmentMap);
            
            $gradesToSave = [];
            foreach ($gradesResponse['results'] as $key => $gradeData) {
                if (empty($gradeData) || !isset($gradeData['usergrades'][0]['gradeitems'])) continue;
                
                $parts = explode('_', $key);
                if (count($parts) != 2) continue;
                
                $uId = (int)$parts[1];
                $mapKey = $courseId . '_' . $uId;
                if (!isset($enrollmentMap[$mapKey])) continue;
                
                $matriculaId = $enrollmentMap[$mapKey];
                foreach ($gradeData['usergrades'][0]['gradeitems'] as $item) {
                    $gradesToSave[] = $this->transformGradeItem($matriculaId, $item);
                }
            }
            
            if (!empty($gradesToSave)) {
                $bulkRes = $this->bulkDb->bulkUpsertGrades(array_filter($gradesToSave));
                $stats['saved'] += ($bulkRes['inserted'] ?? 0) + ($bulkRes['updated'] ?? 0);
                $stats['errors'] += ($bulkRes['errors'] ?? 0);
            }
        }
        
        return $stats;
    }

    private function repairMissingEnrollments(int $courseId, array $studentIds, array &$map): void {
        $missing = [];
        foreach ($studentIds as $id) {
            if (!isset($map[$courseId . '_' . $id])) $missing[] = $id;
        }

        if (!empty($missing)) {
            $stmt = $this->db->prepare("SELECT id FROM cursos WHERE id_moodle = ?");
            $stmt->execute([$courseId]);
            $localId = $stmt->fetchColumn();
            
            if ($localId) {
                $placeholders = implode(',', array_fill(0, count($missing), '?'));
                $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE id_moodle IN ($placeholders)");
                $stmt->execute($missing);
                $localUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($localUsers)) {
                    $this->bulkDb->bulkEnrollMissingUsers((int)$localId, $localUsers);
                    $map = $this->bulkDb->getEnrollmentIdMap([$courseId], $studentIds);
                }
            }
        }
    }

    private function transformGradeItem(int $matriculaId, array $item): ?array {
        if (!isset($item['id']) || !is_numeric($item['id'])) return null;
        
        $itemId = (int)$item['id'];
        $gradeMax = (float)($item['grademax'] ?? 100.0);
        $gradeVal = null;
        
        if (($item['gradeformatted'] ?? '-') !== '-' && isset($item['graderaw'])) {
            $gradeVal = round((float)$item['graderaw'], 4);
        }

        return [
            'matricula_id' => $matriculaId,
            'item_id' => $itemId,
            'item_name' => $this->sanitize($item['itemname'] ?? 'Item'),
            'es_nota_final' => ($item['itemtype'] ?? '') === 'course' ? 1 : 0,
            'grade' => $gradeVal,
            'grade_max' => $gradeMax,
            'feedback' => $this->sanitizeFeedback($item['feedback'] ?? ''),
            'date_graded' => $this->parseDate($item['gradedategraded'] ?? null),
            'data_hash' => $this->calculateHash($matriculaId, $itemId, $gradeVal, $gradeMax, $item['gradedategraded'] ?? null)
        ];
    }

    private function sanitize(string $val): string {
        return htmlspecialchars(strip_tags(html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function sanitizeFeedback(string $fb): string {
        $fb = preg_replace('/<(script|style|object|iframe)[^>]*?>.*?<\/\1>/si', '', html_entity_decode($fb, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return trim(strip_tags($fb, '<p><br><strong><b><em><i><ul><ol><li><span>'));
    }

    private function parseDate($ts): ?string {
        return (is_numeric($ts) && $ts > 946684800) ? date('Y-m-d H:i:s', (int)$ts) : null;
    }

    private function calculateHash(int $m, int $i, ?float $g, float $x, $d): string {
        $data = json_encode(['m' => $m, 'i' => $i, 'g' => $g, 'x' => $x, 'd' => $d], JSON_UNESCAPED_UNICODE);
        $version = (string)\Config\Env::get('APP_SECRET_VERSION', '1');
        $secret = \Config\Env::get("APP_SECRET_V{$version}") ?: \Config\Env::get('APP_SECRET');
        return "v{$version}$" . hash('sha256', $data . $secret);
    }
}
