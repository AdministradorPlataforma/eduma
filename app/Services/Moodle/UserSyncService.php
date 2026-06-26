<?php
declare(strict_types=1);

namespace App\Services\Moodle;

use App\Services\LoggerService;
use App\Services\UserProfileService;
use App\Exceptions\Moodle\StopSyncException;
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
     * FASE 3: Sincronizar Usuarios (Moodle -> Eduma)
     */
    public function sincronizar(array $options = []): array {
        $this->stats['start_time'] = microtime(true);
        
        try {
            $this->stateService->updateProgress(35, "Obteniendo usuarios desde Moodle...");
            
            $allUsers = $this->client->getAllUsersBatched(function($processed, $total, $count) {
                $percent = 35 + (int)(($processed / $total) * 15);
                $this->stateService->updateProgress($percent, "Lote Usuarios $processed/$total...");
                return $this->stateService->shouldStop();
            });
            
            if (empty($allUsers)) return $this->stats;
            
            $this->stateService->updateProgress(55, "Guardando " . count($allUsers) . " usuarios...");
            $result = $this->bulkDb->bulkUpsertUsers($allUsers, $options);
            
            $this->stats = array_merge($this->stats, $result);
            $this->stats['total_from_moodle'] = count($allUsers);
            $this->stats['time_seconds'] = round(microtime(true) - $this->stats['start_time'], 2);
            
            LoggerService::info("Usuarios sincronizados", $this->stats);
            return $this->stats;
            
        } catch (StopSyncException $e) {
            LoggerService::info("Sincronización de usuarios detenida por el usuario");
            throw $e;
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando usuarios", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function sincronizarUsuariosDesbloqueados(): array {
        $this->stats['start_time'] = microtime(true);
        $this->stateService->updateProgress(5, 'Buscando usuarios desbloqueados en Moodle...');

        $stmt = $this->db->query(
            "SELECT id_moodle FROM usuarios WHERE id_moodle IS NOT NULL AND suspended = 1"
        );
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($ids)) {
            $this->stateService->updateProgress(100, 'No hay usuarios suspendidos localmente');
            return ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $this->stateService->updateProgress(20, 'Consultando estado de usuarios en Moodle...');
        $usersResponse = $this->client->getUsersByIds($ids);
        
        // Validación de tipo (PHP 7.4 no permite iterar null)
        $users = $usersResponse['users'] ?? $usersResponse;
        if (!is_array($users)) {
            $users = [];
        }

        $activeUsers = [];
        foreach ($users as $user) {
            if (isset($user['suspended']) && (int)$user['suspended'] === 0) {
                $activeUsers[] = $user;
            }
        }

        if (empty($activeUsers)) {
            $this->stateService->updateProgress(100, 'No se encontraron usuarios desbloqueados');
            return ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $this->stateService->updateProgress(40, 'Sincronizando usuarios desbloqueados...');
        $result = $this->bulkDb->bulkUpsertUsers($activeUsers);
        $result['time_seconds'] = round(microtime(true) - $this->stats['start_time'], 2);

        $this->stateService->updateProgress(100, 'Usuarios desbloqueados sincronizados');
        LoggerService::info('Usuarios desbloqueados sincronizados', $result);
        return $result;
    }

    public function sincronizarMatriculasPorCursos(array $courses): array {
        $this->stats['start_time'] = microtime(true);
        $courseIds = array_column($courses, 'id');

        if (empty($courseIds)) {
            return ['processed' => 0, 'errors' => 0, 'aborted' => false];
        }

        $this->stateService->updateProgress(5, 'Sincronizando cursos 2026...');
        $this->bulkDb->bulkUpsertCourses($courses);

        $this->stateService->updateProgress(15, 'Obteniendo matrículas de cursos 2026...');
        $this->parallelClient->setMaxParallel(MoodleWS::getParallelRequests());
        $this->parallelClient->setTimeout(180);

        $allUserIds = [];
        $courseChunks = array_chunk($courseIds, MoodleWS::PARALLEL_REQUESTS);

        foreach ($courseChunks as $chunkIndex => $courseChunk) {
            if ($this->stateService->shouldStop()) {
                throw new StopSyncException('Detención solicitada en recolección de matrículas');
            }

            $requests = array_map(fn($id) => [
                'key' => (string)$id,
                'function' => MoodleWS::FUNCTIONS['GET_ENROLLED_USERS'],
                'params' => ['courseid' => $id]
            ], $courseChunk);

            $response = $this->parallelClient->executeParallel(
                $requests,
                true,
                fn() => $this->stateService->shouldStop()
            );

            foreach ($response['results'] as $courseId => $enrolledUsers) {
                if (!is_array($enrolledUsers)) {
                    continue;
                }
                foreach ($enrolledUsers as $user) {
                    if (isset($user['id'])) {
                        $allUserIds[] = (int)$user['id'];
                    }
                }
            }

            $percent = 15 + (int)((($chunkIndex + 1) / count($courseChunks)) * 20);
            $this->stateService->updateProgress($percent, 'Recolectando usuarios de cursos 2026...');
        }

        $allUserIds = array_values(array_unique($allUserIds));

        if (!empty($allUserIds)) {
            $placeholders = implode(',', array_fill(0, count($allUserIds), '?'));
            $stmt = $this->db->prepare("SELECT id_moodle FROM usuarios WHERE id_moodle IN ($placeholders)");
            $stmt->execute($allUserIds);
            $existingUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $missingUserIds = array_diff($allUserIds, $existingUserIds);

            if (!empty($missingUserIds)) {
                $this->stateService->updateProgress(45, 'Traendo datos de usuarios faltantes desde Moodle...');
                $usersResponse = $this->client->getUsersByIds(array_values($missingUserIds));
                $usersToInsert = $usersResponse['users'] ?? $usersResponse;
                
                if (!empty($usersToInsert) && is_array($usersToInsert)) {
                    $this->bulkDb->bulkUpsertUsers($usersToInsert);
                }
            }
        }

        $this->stateService->updateProgress(60, 'Guardando matrículas 2026...');
        $processed = 0;
        $errors = 0;

        foreach ($courseChunks as $chunkIndex => $courseChunk) {
            if ($this->stateService->shouldStop()) {
                throw new StopSyncException('Detención solicitada durante guardado de matrículas');
            }

            $requests = array_map(fn($id) => [
                'key' => (string)$id,
                'function' => MoodleWS::FUNCTIONS['GET_ENROLLED_USERS'],
                'params' => ['courseid' => $id]
            ], $courseChunk);

            $response = $this->parallelClient->executeParallel(
                $requests,
                true,
                fn() => $this->stateService->shouldStop()
            );

            foreach ($response['results'] as $courseId => $enrolledUsers) {
                if (!is_array($enrolledUsers)) {
                    continue;
                }

                $activeMoodleIds = [];
                $enrollments = [];

                foreach ($enrolledUsers as $user) {
                    if (!isset($user['id'])) {
                        continue;
                    }
                    $activeMoodleIds[] = (int)$user['id'];
                    $role = 'student';
                    foreach ($user['roles'] ?? [] as $r) {
                        if (in_array($r['shortname'] ?? '', ['editingteacher', 'teacher', 'manager'])) {
                            $role = 'teacher';
                            break;
                        }
                    }
                    $enrollments[] = ['course_id' => (int)$courseId, 'user_id' => (int)$user['id'], 'role' => $role];
                }

                if (!empty($enrollments)) {
                    $res = $this->bulkDb->bulkUpsertEnrollments($enrollments);
                    $processed += $res['processed'] ?? 0;
                    $errors += $res['errors'] ?? 0;
                }

                if (!empty($activeMoodleIds)) {
                    $suspended = $this->bulkDb->bulkSuspendOrphanEnrollments((int)$courseId, $activeMoodleIds);
                    $processed += $suspended;
                }
            }

            $percent = 60 + (int)((($chunkIndex + 1) / count($courseChunks)) * 35);
            $this->stateService->updateProgress($percent, 'Guardando matrículas 2026...');
        }

        $this->stats = [
            'processed' => $processed,
            'errors' => $errors,
            'time_seconds' => round(microtime(true) - $this->stats['start_time'], 2)
        ];

        $this->stateService->updateProgress(100, 'Matrículas 2026 sincronizadas');
        LoggerService::info('Matrículas 2026 sincronizadas', $this->stats);
        return $this->stats;
    }

    /**
     * FASE 4: Sincronizar Matrículas (Moodle -> Eduma)
     */
    public function sincronizarMatriculas(int $startOffset = 0, array $completedPhases = []): array {
        $this->stats['start_time'] = microtime(true);
        $enrollStats = ['processed' => 0, 'errors' => 0, 'aborted' => false];
        
        try {
            $courseIds = $this->db->query("SELECT id_moodle FROM cursos WHERE visible = 1 ORDER BY id_moodle ASC")->fetchAll(PDO::FETCH_COLUMN);
            if (empty($courseIds)) return $enrollStats;
            
            $this->parallelClient->setMaxParallel(MoodleWS::getParallelRequests());
            $this->parallelClient->setTimeout(180);

            if ($startOffset > 0) {
                $totalReal = count($courseIds);
                $courseIds = array_slice($courseIds, $startOffset);
            } else {
                $totalReal = count($courseIds);
            }
            
            $chunks = array_chunk($courseIds, MoodleWS::PARALLEL_REQUESTS);
            $processed = $startOffset;
            
            foreach ($chunks as $courseChunk) {
                if ($this->stateService->shouldStop()) {
                    throw new StopSyncException("Detención forzada en matrículas chunk");
                }
                
                $requests = array_map(fn($id) => [
                    'key' => (string)$id,
                    'function' => MoodleWS::FUNCTIONS['GET_ENROLLED_USERS'],
                    'params' => ['courseid' => $id]
                ], $courseChunk);
                
                $parallelResponse = $this->parallelClient->executeParallel(
                    $requests, 
                    true, 
                    fn() => $this->stateService->shouldStop()
                );
                if (!empty($parallelResponse['aborted'])) {
                    $enrollStats['aborted'] = true;
                    break;
                }
                
                if ($this->stateService->shouldStop()) {
                    throw new StopSyncException("Detenido por el usuario");
                }
                
                foreach ($parallelResponse['results'] ?? [] as $courseId => $enrolledUsers) {
                    if ($this->stateService->shouldStop()) {
                        throw new StopSyncException("Detenido por el usuario");
                    }
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
            
        } catch (StopSyncException $e) {
            LoggerService::info("Sincronización de matrículas detenida por el usuario");
            throw $e;
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando matrículas", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function actualizarFlagsRolesUsuarios(): void {
        try {
            if ($this->stateService->shouldStop()) throw new StopSyncException("Stop antes de roles");
            
            // OPTIMIZACIÓN: Sentencia estructurada para evitar bloqueos prolongados y procesar masivamente
            $sql = "UPDATE usuarios u
                    JOIN (
                        SELECT usuario_id,
                               MAX(CASE WHEN rol = 'student' THEN 1 ELSE 0 END) as is_std,
                               MAX(CASE WHEN rol IN ('editingteacher', 'teacher', 'manager') THEN 1 ELSE 0 END) as is_prof
                        FROM curso_matriculas
                        GROUP BY usuario_id
                    ) m ON u.id = m.usuario_id
                    SET u.es_estudiante = m.is_std,
                        u.es_docente = m.is_prof,
                        u.updated_at = NOW()";
            
            $this->db->exec($sql);
            
            if ($this->stateService->shouldStop()) throw new StopSyncException("Stop entre roles");

            $sqlOrphans = "UPDATE usuarios u 
                           LEFT JOIN curso_matriculas cm ON u.id = cm.usuario_id
                           SET u.es_estudiante = 0, u.es_docente = 0, u.updated_at = NOW()
                           WHERE cm.id IS NULL AND (u.es_estudiante = 1 OR u.es_docente = 1)";
            $this->db->exec($sqlOrphans);
            
        } catch (StopSyncException $e) {
            throw $e;
        } catch (\Exception $e) {
            // MANEJO DE ERRORES: Capturar errores SQL para que no se detenga el flujo principal
            LoggerService::error("Error actualizando flags de roles", ['error' => $e->getMessage()]);
        }
    }

    public function sincronizarCohortes(): array {
        $startTime = microtime(true);
        $cohortStats = ['processed' => 0, 'errors' => 0];
        try {
            $cohorts = $this->client->call('core_cohort_get_cohorts', ['cohortids' => []]);
            // Validación de respuesta API
            if (!empty($cohorts) && is_array($cohorts)) {
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
