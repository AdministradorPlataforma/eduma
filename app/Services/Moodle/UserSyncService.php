<?php
declare(strict_types=1);

namespace App\Services\Moodle;

use App\Services\LoggerService;
use App\Services\UserProfileService;
use Config\MoodleWS;
use PDO;

/**
 * Servicio especializado en sincronización de Usuarios, Matrículas y Cohortes.
 */
class UserSyncService extends MoodleBaseSyncService {

    private UserProfileService $profileService;

    public function __construct(
        \Modules\Moodle\MoodleClient $client, 
        \Modules\Moodle\MoodleParallelClient $parallelClient, 
        \App\Services\BulkDatabaseService $bulkDb, 
        \App\Services\SyncStateDbService $stateService,
        \App\Services\UserProfileService $profileService,
        PDO $db
    ) {
        parent::__construct($client, $parallelClient, $bulkDb, $stateService, $db);
        $this->profileService = $profileService;
    }

    /**
     * FASE 3: Sincronizar Usuarios
     */
    public function sincronizar(array $options = []): array {
        $this->stats['start_time'] = microtime(true);
        
        try {
            $this->stateService->updateProgress(35, "Obteniendo usuarios desde Moodle...");
            
            $allUsers = $this->client->getAllUsersBatched(function($processed, $total, $count) {
                $percent = 35 + (int)(($processed / $total) * 15);
                $this->stateService->updateProgress($percent, "Batch $processed/$total...");
            });
            
            if (empty($allUsers)) return $this->stats;
            
            $this->stateService->updateProgress(55, "Guardando " . count($allUsers) . " usuarios...");
            $result = $this->bulkDb->bulkUpsertUsers($allUsers, $options);
            
            $this->stats = array_merge($this->stats, $result);
            $this->stats['total_from_moodle'] = count($allUsers);
            $this->stats['time_seconds'] = round(microtime(true) - $this->stats['start_time'], 2);
            
            LoggerService::info("Usuarios sincronizados", $this->stats);
            return $this->stats;
            
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando usuarios", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * FASE 4: Sincronizar Matrículas
     */
    public function sincronizarMatriculas(int $startOffset = 0, array $completedPhases = []): array {
        $this->stats['start_time'] = microtime(true);
        $enrollStats = ['processed' => 0, 'errors' => 0, 'aborted' => false];
        
        try {
            $courseIds = $this->db->query("SELECT id_moodle FROM cursos WHERE visible = 1 ORDER BY id_moodle ASC")->fetchAll(PDO::FETCH_COLUMN);
            if (empty($courseIds)) return $enrollStats;
            
            $this->parallelClient->setMaxParallel(1);
            $this->parallelClient->setTimeout(180);

            if ($startOffset > 0) {
                $totalReal = count($courseIds);
                $courseIds = array_slice($courseIds, $startOffset);
            } else {
                $totalReal = count($courseIds);
            }
            
            $chunks = array_chunk($courseIds, 5);
            $processed = $startOffset;
            
            foreach ($chunks as $courseChunk) {
                if ($this->stateService->shouldStop()) {
                    $enrollStats['aborted'] = true;
                    break;
                }
                
                $requests = array_map(fn($id) => [
                    'key' => (string)$id,
                    'function' => MoodleWS::FUNCTIONS['GET_ENROLLED_USERS'],
                    'params' => ['courseid' => $id]
                ], $courseChunk);
                
                $parallelResponse = $this->parallelClient->executeParallel($requests);
                if (!empty($parallelResponse['aborted'])) {
                    $enrollStats['aborted'] = true;
                    break;
                }
                
                foreach ($parallelResponse['results'] ?? [] as $courseId => $enrolledUsers) {
                    if (is_array($enrolledUsers)) {
                        $activeIds = array_column($enrolledUsers, 'id');
                        $suspended = $this->bulkDb->bulkSuspendOrphanEnrollments((int)$courseId, $activeIds);

                        $enrollments = [];
                        foreach ($enrolledUsers as $user) {
                            $role = 'student';
                            foreach ($user['roles'] ?? [] as $r) {
                                if (in_array($r['shortname'] ?? '', ['editingteacher', 'teacher', 'manager'])) {
                                    $role = 'teacher'; break;
                                }
                            }
                            $enrollments[] = ['course_id' => (int)$courseId, 'user_id' => $user['id'], 'role' => $role];
                        }

                        if (!empty($enrollments)) {
                            $res = $this->bulkDb->bulkUpsertEnrollments($enrollments);
                            $enrollStats['processed'] += $res['processed'];
                            $enrollStats['errors'] += $res['errors'];
                            $this->stateService->recordMetric('enrollments', 'updated', ($res['updated'] ?? 0) + $suspended);
                        }
                    }
                }
                
                $processed += count($courseChunk);
                $percent = 70 + (int)(($processed / $totalReal) * 10);
                $this->stateService->updateProgress($percent, "Matrículas: $processed/$totalReal...");
                
                $this->stateService->saveCheckpoint('enrollments', [
                    'completed_phases' => $completedPhases,
                    'enrollments_offset' => $processed,
                    'stats' => $this->stats // El orquestador manejará la acumulación real
                ]);
            }
            
            if ($enrollStats['processed'] > 0) $this->actualizarFlagsRolesUsuarios();
            
            $enrollStats['time_seconds'] = round(microtime(true) - $this->stats['start_time'], 2);
            return $enrollStats;
            
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando matrículas", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function actualizarFlagsRolesUsuarios(): void {
        $sql = "UPDATE usuarios u
                LEFT JOIN (
                    SELECT usuario_id, 
                           MAX(CASE WHEN rol = 'student' THEN 1 ELSE 0 END) as is_student,
                           MAX(CASE WHEN rol = 'teacher' THEN 1 ELSE 0 END) as is_teacher
                    FROM curso_matriculas
                    GROUP BY usuario_id
                ) m ON u.id = m.usuario_id
                SET u.es_estudiante = COALESCE(m.is_student, 0),
                    u.es_docente = COALESCE(m.is_teacher, 0)";
        $this->db->exec($sql);
    }

    public function sincronizarCohortes(): array {
        $startTime = microtime(true);
        $cohortStats = ['processed' => 0, 'errors' => 0];
        try {
            $cohorts = $this->client->call('core_cohort_get_cohorts', ['cohortids' => []]);
            if (!empty($cohorts)) {
                $cohortStats = array_merge($cohortStats, $this->bulkDb->bulkUpsertCohorts($cohorts));
            }
            $cohortStats['time_seconds'] = round(microtime(true) - $startTime, 2);
            return $cohortStats;
        } catch (\Exception $e) {
            LoggerService::warning("Error cohortes: " . $e->getMessage());
            return $cohortStats;
        }
    }

    public function sincronizarPerfiles(): array {
        return $this->profileService->sincronizarPerfilesDesdeMatriculas();
    }
}
