<?php
declare(strict_types=1);

namespace App\Services\Sync;

use App\Services\BaseService;
use Modules\Moodle\MoodleClient;
use Modules\Moodle\MoodleParallelClient;
use App\Services\BulkDatabaseService;
use App\Services\SyncStateDbService;
use App\Services\LoggerService;
use PDO;

class GradeSyncService extends BaseService
{
    private MoodleClient $client;
    private MoodleParallelClient $parallelClient;
    private BulkDatabaseService $bulkDb;
    private SyncStateDbService $stateService;
    private PDO $db;

    public function __construct(
        MoodleClient $client, 
        MoodleParallelClient $parallelClient, 
        BulkDatabaseService $bulkDb, 
        SyncStateDbService $stateService,
        PDO $db
    ) {
        $this->client = $client;
        $this->parallelClient = $parallelClient;
        $this->bulkDb = $bulkDb;
        $this->stateService = $stateService;
        $this->db = $db;
    }

    /**
     * Sincroniza calificaciones de forma optimizada.
     */
    public function sync(?array $courseIds = null): array
    {
        $startTime = microtime(true);
        $stats = [
            'courses_processed' => 0,
            'grades_saved' => 0,
            'errors' => 0
        ];
        
        try {
            if ($courseIds === null) {
                $currentYear = date('Y');
                $stmt = $this->db->prepare(
                    "SELECT id_moodle FROM cursos 
                     WHERE visible = 1 
                     AND YEAR(start_date) >= ? 
                     ORDER BY start_date DESC"
                );
                $stmt->execute([$currentYear - 1]);
                $courseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            if (empty($courseIds)) {
                return $stats;
            }
            
            $parallelCapacity = \Config\MoodleWS::getParallelRequests();
            $this->stateService->updateProgress(0, "Procesando calificaciones (" . count($courseIds) . " cursos) con {$parallelCapacity} hilos...");
            
            $chunks = array_chunk($courseIds, 20);
            $processedCourses = 0;
            
            foreach ($chunks as $courseChunk) {
                if ($this->stateService->shouldStop()) break;
                
                foreach ($courseChunk as $courseId) {
                    try {
                        $courseStats = $this->procesarCalificacionesCursoOptimizado((int)$courseId);
                        $stats['courses_processed']++;
                        $stats['grades_saved'] += $courseStats['saved'] ?? 0;
                        $stats['errors'] += $courseStats['errors'] ?? 0;
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        LoggerService::warning("Error calificaciones curso $courseId", ['error' => $e->getMessage()]);
                    }
                }
                
                $processedCourses += count($courseChunk);
                $percent = (int)(($processedCourses / count($courseIds)) * 100);
                $this->stateService->updateProgress($percent, "Calificaciones: $processedCourses/" . count($courseIds) . " cursos...");
            }
            
            $stats['time_seconds'] = round(microtime(true) - $startTime, 2);
            LoggerService::info("Sincronización de calificaciones finalizada", $stats);
            
            return $stats;
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando calificaciones", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function procesarCalificacionesCursoOptimizado(int $courseId): array
    {
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
            
            $formattedGrades = [];
            foreach ($gradesResponse['results'] as $res) {
                $mUserId = (int)$res['userid'];
                $mapKey = $courseId . '_' . $mUserId;
                
                if (isset($enrollmentMap[$mapKey]) && !empty($res['gradeitems'])) {
                    foreach ($res['gradeitems'] as $item) {
                        $formattedGrades[] = [
                            'matricula_id' => $enrollmentMap[$mapKey],
                            'id_moodle_item' => $item['id'] ?? null,
                            'item_nombre' => $item['itemname'] ?? 'Nota Final',
                            'calificacion_final' => $item['graderaw'] ?? null,
                            'calificacion_maxima' => $item['grademax'] ?? 100.0,
                            'feedback' => $item['feedback'] ?? null,
                            'fecha_modificacion' => isset($item['timemodified']) ? date('Y-m-d H:i:s', (int)$item['timemodified']) : null
                        ];
                    }
                }
            }
            
            if (!empty($formattedGrades)) {
                $bulkUpsert = $this->bulkDb->bulkUpsertGrades($formattedGrades);
                $stats['saved'] += $bulkUpsert['inserted'] + $bulkUpsert['updated'];
                $stats['errors'] += $bulkUpsert['errors'];
            }
        }
        
        return $stats;
    }
}
