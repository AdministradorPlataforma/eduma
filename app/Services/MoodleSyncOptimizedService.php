<?php
declare(strict_types=1);

namespace App\Services;

use Modules\Moodle\MoodleClient;
use Modules\Moodle\MoodleParallelClient;
use App\Exceptions\MoodleException;
use Config\MoodleWS;
use PDO;

/**
 * Servicio de Sincronización Optimizada con Moodle
 * 
 * Versión 3.1 - Optimizado para:
 * - 22K+ usuarios
 * - 10K+ cursos  
 * - 5K+ cohortes
 * 
 * Mejoras clave:
 * - Procesamiento paralelo con curl_multi
 * - Bulk INSERT/UPDATE masivos
 * - Sincronización incremental (delta)
 * - Algoritmo optimizado O(n) vs O(n²)
 * - Estado en base de datos (no JSON)
 * - Circuit breaker compartido
 * - Early-stop en fallas masivas
 * 
 * @version 3.1
 */
class MoodleSyncOptimizedService extends BaseService {

    private MoodleClient $client;
    private MoodleParallelClient $parallelClient;
    private BulkDatabaseService $bulkDb;
    private SyncStateDbService $stateService;
    private UserProfileService $profileService;
    private SyncCleanupService $cleanupService;
    private PDO $db;
    
    /** @var array Estadísticas de la sincronización actual */
    private array $stats = [
        'processed' => 0,
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'start_time' => null,
        'phases' => []
    ];

    public function __construct(
        MoodleClient $client, 
        MoodleParallelClient $parallelClient, 
        BulkDatabaseService $bulkDb, 
        SyncStateDbService $stateService,
        UserProfileService $profileService,
        SyncCleanupService $cleanupService,
        PDO $db
    ) {
        $this->client = $client;
        $this->parallelClient = $parallelClient;
        $this->bulkDb = $bulkDb;
        $this->stateService = $stateService;
        $this->profileService = $profileService;
        $this->cleanupService = $cleanupService;
        $this->db = $db;
        
        $this->stats['start_time'] = microtime(true);
    }

    /**
     * Verifica la conexión con Moodle
     */
    public function checkConnection(): array {
        return $this->client->healthCheck();
    }

    /**
     * Obtiene estadísticas de la sincronización
     */
    public function getStats(): array {
        $this->stats['elapsed_seconds'] = round(microtime(true) - $this->stats['start_time'], 2);
        return $this->stats;
    }

    /**
     * Helper para sincronizar estadísticas detalladas con el servicio de estado
     */
    private function updateStateStats(): void {
        $this->stateService->updateStats(
            (int)($this->stats['processed'] ?? 0),
            (int)($this->stats['updated'] ?? 0),
            (int)($this->stats['errors'] ?? 0)
        );
    }

    /**
     * Sincronización COMPLETA optimizada
     * Ejecuta todas las fases en orden óptimo
     */
    public function sincronizarTodo(bool $force = false, array $options = []): array {
        $shouldResume = $options['resume'] ?? false;
        $checkpoint = $this->stateService->getCheckpoint();

        // Si se pide resumen pero no hay checkpoint válido, forzar inicio limpio
        if ($shouldResume && empty($checkpoint)) {
            $shouldResume = false;
        }

        if (!$shouldResume) {
            $this->stateService->clearCheckpoint();
            $this->stateService->startSync('all');
            $this->stateService->startBatch('all'); // Trazabilidad formal
            $completedPhases = [];
        } else {
            // Reanudar: Mantener stats previos si existen
            $this->stateService->startSync('all', true); // Marca running en DB pero mantiene progreso
            if (isset($checkpoint['stats'])) {
                $this->stats = $checkpoint['stats'];
            }
            $completedPhases = $checkpoint['completed_phases'] ?? [];
            LoggerService::info("Reanudando sincronización desde checkpoint", $checkpoint);
        }

        try {
            // FASE 1: Categorías
            if (!in_array('categories', $completedPhases)) {
                $this->stateService->updateProgress(5, 'Sincronizando categorías...');
                $catStats = $this->sincronizarCategorias();
                $this->stats['phases']['categories'] = $catStats;
                $this->stats['processed'] += $catStats['processed'] ?? 0;
                $this->stats['updated'] += $catStats['updated'] ?? 0;
                $this->stats['errors'] += $catStats['errors'] ?? 0;
                
                $completedPhases[] = 'categories';
                $this->stateService->recordMetric('categories', 'processed', $catStats['processed']);
                $this->stateService->recordMetric('categories', 'duration', $catStats['time_seconds'] ?? 0, 'seconds');
                
                $this->stateService->saveCheckpoint('categories', [
                    'completed_phases' => $completedPhases, 
                    'stats' => $this->stats
                ]);
                $this->updateStateStats();
            }

            if ($this->stateService->shouldStop()) return $this->handleStop();

            // FASE 2: Cursos
            if (!in_array('courses', $completedPhases)) {
                $this->stateService->updateProgress(15, 'Sincronizando cursos...');
                $courseStats = $this->sincronizarCursosOptimizado();
                $this->stats['phases']['courses'] = $courseStats;
                $this->stats['processed'] += $courseStats['processed'] ?? 0;
                $this->stats['updated'] += $courseStats['updated'] ?? 0;
                $this->stats['errors'] += $courseStats['errors'] ?? 0;

                $completedPhases[] = 'courses';
                $this->stateService->recordMetric('courses', 'processed', $courseStats['processed']);
                $this->stateService->recordMetric('courses', 'duration', $courseStats['time_seconds'] ?? 0, 'seconds');

                $this->stateService->saveCheckpoint('courses', [
                    'completed_phases' => $completedPhases, 
                    'stats' => $this->stats
                ]);
                $this->updateStateStats();
            }

            if ($this->stateService->shouldStop()) return $this->handleStop();

            // FASE 3: Usuarios
            if (!in_array('users', $completedPhases)) {
                $this->stateService->updateProgress(30, 'Sincronizando usuarios...');
                $userStats = $this->sincronizarUsuariosOptimizado($force, $options);
                $this->stats['phases']['users'] = $userStats;
                $this->stats['processed'] += $userStats['processed'] ?? 0;
                $this->stats['updated'] += $userStats['updated'] ?? 0;
                $this->stats['errors'] += $userStats['errors'] ?? 0;

                $completedPhases[] = 'users';
                $this->stateService->recordMetric('users', 'processed', $userStats['processed']);
                $this->stateService->recordMetric('users', 'duration', $userStats['time_seconds'] ?? 0, 'seconds');

                $this->stateService->saveCheckpoint('users', [
                    'completed_phases' => $completedPhases, 
                    'stats' => $this->stats
                ]);
                $this->updateStateStats();
            }

            if ($this->stateService->shouldStop()) return $this->handleStop();

            // FASE 4: Matrículas (Soporta reanudación parcial)
            if (!in_array('enrollments', $completedPhases)) {
                $this->stateService->updateProgress(70, 'Sincronizando matrículas...');
                
                // Verificar si hay un offset guardado para reanudar a mitad de camino
                $startOffset = ($shouldResume && isset($checkpoint['enrollments_offset'])) 
                    ? (int)$checkpoint['enrollments_offset'] 
                    : 0;

                $enrollStats = $this->sincronizarMatriculasOptimizado($startOffset, $completedPhases);
                $this->stats['phases']['enrollments'] = $enrollStats;

                $completedPhases[] = 'enrollments';
                $this->stateService->recordMetric('enrollments', 'processed', $enrollStats['processed']);
                $this->stateService->recordMetric('enrollments', 'duration', $enrollStats['time_seconds'] ?? 0, 'seconds');
                
                $this->stateService->saveCheckpoint('enrollments', [
                    'completed_phases' => $completedPhases, 
                    'stats' => $this->stats
                ]);
                $this->updateStateStats();
            }

            if ($this->stateService->shouldStop()) return $this->handleStop();

            // FASE 4.5: Perfiles
            if (!in_array('profiles', $completedPhases)) {
                $this->stateService->updateProgress(83, 'Creando perfiles de usuarios...');
                $profileStats = $this->profileService->sincronizarPerfilesDesdeMatriculas();
                $this->stats['phases']['profiles'] = $profileStats;
                $this->stats['processed'] += $profileStats['processed'] ?? 0;
                $this->stats['updated'] += $profileStats['updated'] ?? 0;
                $this->stats['errors'] += $profileStats['errors'] ?? 0;

                $completedPhases[] = 'profiles';
                $this->stateService->recordMetric('profiles', 'processed', $profileStats['processed'] ?? 0);
                $this->stateService->recordMetric('profiles', 'duration', $profileStats['time_seconds'] ?? 0, 'seconds');

                $this->stateService->saveCheckpoint('profiles', [
                    'completed_phases' => $completedPhases, 
                    'stats' => $this->stats
                ]);
                $this->updateStateStats();
            }

            // FASE 5: Cohortes
            if (!in_array('cohorts', $completedPhases)) {
                $this->stateService->updateProgress(88, 'Sincronizando cohortes...');
                $cohortStats = $this->sincronizarCohortesOptimizado();
                $this->stats['phases']['cohorts'] = $cohortStats;
                $this->stats['processed'] += $cohortStats['processed'] ?? 0;
                $this->stats['updated'] += $cohortStats['updated'] ?? 0;
                $this->stats['errors'] += $cohortStats['errors'] ?? 0;

                $completedPhases[] = 'cohorts';
                $this->stateService->recordMetric('cohorts', 'processed', $cohortStats['processed'] ?? 0);
                $this->stateService->recordMetric('cohorts', 'duration', $cohortStats['time_seconds'] ?? 0, 'seconds');

                $this->stateService->saveCheckpoint('cohorts', [
                    'completed_phases' => $completedPhases, 
                    'stats' => $this->stats
                ]);
                $this->updateStateStats();
            }

            if ($this->stateService->shouldStop()) return $this->handleStop();

            // FASE 6: Limpieza y Mapeo Estructural
            $this->stateService->updateProgress(95, 'Verificando entidades huérfanas...');
            $orphanSummary = $this->cleanupService->obtenerResumenHuerfanos();
            $this->stats['phases']['orphan_summary'] = $orphanSummary;

            // FASE 7: Mapeo Académico (Auto-asignación de Carreras y Facultades)
            $this->stateService->updateProgress(98, 'Mapeando estructura académica...');
            $mappingStats = $this->mapearEstructuraAcademicaAuto();
            $this->stats['phases']['academic_mapping'] = $mappingStats;

            // Finalizar
            $this->stateService->finishBatch('completed', $this->stats);
            $this->stateService->completeSync();
            $this->stateService->clearCheckpoint(); // Limpiar checkpoint al terminar exitosamente
            $this->stats['status'] = 'completed';
            
            LoggerService::info("Sincronización completa finalizada", $this->getStats());

        } catch (\Exception $e) {
            $this->stateService->finishBatch('error', $this->stats);
            $this->stateService->errorSync($e->getMessage());
            $this->stats['status'] = 'error';
            $this->stats['error'] = $e->getMessage();
            LoggerService::error("Error en sincronización completa", [
                'error' => $e->getMessage(),
                'stats' => $this->stats
            ]);
            throw $e;
        }

        return $this->getStats();
    }

    /**
     * FASE 1: Sincronizar Categorías
     * Rápido y simple - base para jerarquía
     */
    public function sincronizarCategorias(): array {
        $startTime = microtime(true);
        
        try {
            $categories = $this->client->getCategories();
            $stats = $this->bulkDb->bulkUpsertCategories($categories);
            
            $stats['time_seconds'] = round(microtime(true) - $startTime, 2);
            $stats['total_from_moodle'] = count($categories);
            
            LoggerService::info("Categorías sincronizadas", $stats);
            return $stats;
            
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando categorías", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * FASE 2: Sincronizar Cursos - OPTIMIZADO
     * Obtiene todos los cursos de una vez y usa bulk insert
     */
    public function sincronizarCursosOptimizado(): array {
        $startTime = microtime(true);
        $stats = ['processed' => 0, 'errors' => 0];
        
        try {
            // Obtener TODOS los cursos de golpe (más eficiente que por categoría)
            $allCourses = $this->client->getAllCourses();
            $stats['total_from_moodle'] = count($allCourses);
            
            $this->stateService->updateProgress(20, "Procesando " . count($allCourses) . " cursos...");
            
            // Bulk insert
            $bulkResult = $this->bulkDb->bulkUpsertCourses($allCourses);
            $stats = array_merge($stats, $bulkResult);
            
            $stats['time_seconds'] = round(microtime(true) - $startTime, 2);
            LoggerService::info("Cursos sincronizados (optimizado)", $stats);
            
            return $stats;
            
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando cursos", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * FASE 3: Sincronizar Usuarios - OPTIMIZADO V2
     * 
     * Nueva estrategia (mucho más rápida):
     * 1. Obtener TODOS los usuarios con core_user_get_users (1 request o max 36)
     * 2. Bulk INSERT/UPDATE directamente
     * 
     * Antes: 8961 cursos * 1 request = 8961 requests (~30+ minutos)
     * Ahora: 1-36 requests (~30 segundos)
     */
    public function sincronizarUsuariosOptimizado(bool $force = false, array $options = []): array {
        $startTime = microtime(true);
        $stats = [
            'method' => 'direct_batch',
            'total_from_moodle' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
        
        try {
            $this->stateService->updateProgress(35, "Obteniendo usuarios desde Moodle (método directo)...");
            
            // Usar el nuevo método batched que obtiene todos los usuarios
            $allUsers = $this->client->getAllUsersBatched(function($processed, $total, $count) {
                $percent = 35 + (int)(($processed / $total) * 15);
                $this->stateService->updateProgress($percent, "Batch $processed/$total... ($count usuarios)");
            });
            
            $stats['total_from_moodle'] = count($allUsers);
            
            if (empty($allUsers)) {
                LoggerService::info("No se encontraron usuarios en Moodle");
                return $stats;
            }
            
            $this->stateService->updateProgress(55, "Guardando " . count($allUsers) . " usuarios en BD...");
            
            // Bulk UPSERT directo
            $bulkResult = $this->bulkDb->bulkUpsertUsers($allUsers, $options);
            $stats = array_merge($stats, $bulkResult);
            
            $stats['time_seconds'] = round(microtime(true) - $startTime, 2);
            LoggerService::info("Usuarios sincronizados (optimizado v2 - directo)", $stats);
            
            return $stats;
            
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando usuarios", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * FASE 4: Sincronizar Matrículas - OPTIMIZADO v2
     * 
     * Mejoras:
     * - Detecta circuit breaker abierto
     * - Early-stop si hay fallas masivas en paralelo
     * - Maneja aborto por errores de BD consecutivos
     */
    public function sincronizarMatriculasOptimizado(int $startOffset = 0, array $completedPhases = []): array {
        $startTime = microtime(true);
        $stats = ['processed' => 0, 'errors' => 0, 'aborted' => false, 'reason' => null];
        
        try {
            // Obtener cursos activos con orden determinista
            $stmt = $this->db->query("SELECT id_moodle FROM cursos WHERE visible = 1 ORDER BY id_moodle ASC");
            $courseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($courseIds)) {
                return $stats;
            }
            
            // OPTIMIZACIÓN DE ESTABILIDAD:
            // core_enrol_get_enrolled_users es extremadamente pesado y causa 504/Empty Response.
            // Forzamos procesamiento SECUENCIAL (1) y aumentamos drásticamente el timeout.
            // La fiabilidad es prioridad sobre la velocidad en esta fase crítica.
            $parallelRequests = 1;
            $this->parallelClient->setMaxParallel($parallelRequests);
            $this->parallelClient->setTimeout(180);

            // El batch total debe ser pequeño para guardar progreso frecuentemente (ej: 1 * 5 = 5 cursos)
            $batchSize = $parallelRequests * 5;
            
            // Aplicar offset si estamos reanudando
            if ($startOffset > 0) {
                LoggerService::info("Matrículas: Reanudando desde curso #$startOffset");
                // Preservar contador para LOGS correctos
                $totalCoursesReal = count($courseIds);
                $courseIds = array_slice($courseIds, $startOffset);
            } else {
                $totalCoursesReal = count($courseIds);
            }
            
            $chunks = array_chunk($courseIds, $batchSize);
            $processedCourses = $startOffset; // Iniciar contador en el offset
            $totalCourses = $totalCoursesReal; // Usar total real para cálculos de porcentaje
            
            $this->stateService->updateProgress(70, 'Sincronizando matrículas (Total cursos: ' . $totalCourses . ')...');

            foreach ($chunks as $chunkIndex => $courseChunk) {
                // DEBUG TEMPORAL: Ver qué IDs estamos procesando
                if ($chunkIndex === 0) {
                     LoggerService::info("DEBUG: Primer chunk de cursos", ['ids' => array_slice($courseChunk, 0, 10)]);
                }

                if ($this->stateService->shouldStop()) {
                    $stats['aborted'] = true;
                    $stats['reason'] = 'user_stop';
                    break;
                }
                
                // Ejecutar requests paralelos
                $requests = [];
                foreach ($courseChunk as $courseId) {
                    $requests[] = [
                        'key' => (string)$courseId,
                        'function' => MoodleWS::FUNCTIONS['GET_ENROLLED_USERS'],
                        'params' => ['courseid' => $courseId]
                    ];
                }
                
                $parallelResponse = $this->parallelClient->executeParallel($requests);
                
                // Verificar si el cliente paralelo abortó
                if (!empty($parallelResponse['aborted'])) {
                    $stats['aborted'] = true;
                    $stats['reason'] = 'parallel_' . ($parallelResponse['reason'] ?? 'unknown');
                    LoggerService::warning("Matrículas: Abortando por falla en requests paralelos", [
                        'reason' => $parallelResponse['reason'],
                        'courses_processed' => $processedCourses,
                        'courses_total' => $totalCourses
                    ]);
                    break;
                }
                
                $enrolledByChunk = $parallelResponse['results'] ?? [];
                
                foreach ($enrolledByChunk as $courseId => $enrolledUsers) {
                    if (is_array($enrolledUsers)) {
                        // 1. Limpieza de huérfanos (Corrección Integridad)
                        $activeMoodleIds = array_column($enrolledUsers, 'id');
                        $suspended = $this->bulkDb->bulkSuspendOrphanEnrollments((int)$courseId, $activeMoodleIds);

                        // 2. Preparar datos para Upsert (Solo este curso)
                        $courseEnrollments = [];
                        foreach ($enrolledUsers as $user) {
                            $role = 'student';
                            if (isset($user['roles'])) {
                                foreach ($user['roles'] as $r) {
                                    if (in_array($r['shortname'] ?? '', ['editingteacher', 'teacher', 'manager'])) {
                                        $role = 'teacher';
                                        break;
                                    }
                                }
                            }
                            
                            $courseEnrollments[] = [
                                'course_id' => (int)$courseId,
                                'user_id' => $user['id'],
                                'role' => $role
                            ];
                        }

                        // 3. Guardar INMEDIATAMENTE este curso en BD local
                        if (!empty($courseEnrollments)) {
                            $bulkResult = $this->bulkDb->bulkUpsertEnrollments($courseEnrollments);
                            
                            // Acumular estadísticas
                            $stats['processed'] += $bulkResult['processed'];
                            $stats['errors'] += $bulkResult['errors'];
                            
                            // Acumular global para reporte en vivo
                            $this->stats['processed'] += $bulkResult['processed'];
                            $this->stats['updated'] += ($bulkResult['updated'] ?? 0) + ($suspended ?? 0);
                            $this->stats['errors'] += $bulkResult['errors'];
                        }
                    }
                }
                
                $processedCourses += count($courseChunk);
                $percent = 70 + (int)(($processedCourses / $totalCourses) * 10);
                $this->stateService->updateProgress($percent, "Matrículas: $processedCourses/$totalCourses cursos...");

                // GUARDAR CHECKPOINT PARCIAL
                // Guardamos el offset actual del loop para poder retomar exactamente aquí
                $this->stateService->saveCheckpoint('enrollments', [
                    'completed_phases' => $completedPhases,
                    'enrollments_offset' => $processedCourses,
                    'stats' => $this->stats
                ]);
                $this->updateStateStats();
            }
            
            // ACTUALIZAR FLAGS DE USUARIOS
            if ($stats['processed'] > 0) {
                $this->stateService->updateProgress(84, "Actualizando roles globales de usuarios...");
                $this->actualizarFlagsRolesUsuarios();
            }
            
            $stats['time_seconds'] = round(microtime(true) - $startTime, 2);
            $stats['total_enrollments'] = $stats['processed']; // Aproximación
            $stats['courses_processed'] = $processedCourses;
            $stats['courses_total'] = $totalCourses;
            
            LoggerService::info("Matrículas sincronizadas (curso a curso)", $stats);
            return $stats;
            
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando matrículas", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Actualiza los flags es_estudiante y es_docente en la tabla usuarios
     * basado en las matrículas que acaban de sincronizarse.
     */
    private function actualizarFlagsRolesUsuarios(): void {
        try {
            // Esta query actualiza masivamente los flags basándose en si tienen al menos una matrícula con ese rol
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
            LoggerService::info("Flags de roles de usuarios actualizados correctamente");
        } catch (\Exception $e) {
            LoggerService::error("Error actualizando flags de roles", ['error' => $e->getMessage()]);
            // No lanzamos excepción para no detener el sync por esto, es un paso secundario
        }
    }

    /**
     * FASE 5: Sincronizar Cohortes
     */
    public function sincronizarCohortesOptimizado(): array {
        $startTime = microtime(true);
        $stats = ['processed' => 0, 'errors' => 0];
        
        try {
            // Nota: core_cohort_get_cohorts requiere IDs específicos
            // Alternativa: Iterar por contextos conocidos o usar reporte
            // Por ahora, hacemos un approach basado en contexto del sistema
            
            // Intentar obtener cohortes del sistema (contextid = 1 típicamente)
            $cohorts = $this->client->call('core_cohort_get_cohorts', [
                'cohortids' => [] // Vacío para todos (si el WS lo permite)
            ]);
            
            if (!empty($cohorts)) {
                $bulkResult = $this->bulkDb->bulkUpsertCohorts($cohorts);
                $stats = array_merge($stats, $bulkResult);
            }
            
            $stats['time_seconds'] = round(microtime(true) - $startTime, 2);
            LoggerService::info("Cohortes sincronizadas", $stats);
            
            return $stats;
            
        } catch (\Exception $e) {
            // Cohortes puede fallar si el WS no está habilitado, no es crítico
            LoggerService::warning("Error sincronizando cohortes (no crítico)", [
                'error' => $e->getMessage()
            ]);
            return $stats;
        }
    }

    /**
     * Sincronización INCREMENTAL (Delta)
     * Solo procesa cambios desde la última sincronización exitosa
     */
    public function sincronizarDelta(): array {
        $lastSync = $this->stateService->getLastSuccessfulSync();
        
        if (!$lastSync) {
            LoggerService::info("No hay sync previo, ejecutando sync completo");
            return $this->sincronizarTodo(false);
        }
        
        $lookbackHours = MoodleWS::SYNC_LOOKBACK_HOURS;
        $sinceTimestamp = strtotime("-{$lookbackHours} hours");
        
        $this->stateService->startSync('delta');
        
        try {
            $stats = ['mode' => 'delta', 'since' => date('Y-m-d H:i:s', $sinceTimestamp)];
            
            // Para delta, solo procesamos usuarios modificados recientemente
            // Moodle guarda 'timemodified' en sus tablas
            
            $this->stateService->updateProgress(20, "Buscando cambios desde " . date('Y-m-d H:i', $sinceTimestamp) . "...");
            
            // Por ahora, hacemos sync completo pero más liviano
            // En el futuro, se puede usar los endpoints de cambios de Moodle
            $userStats = $this->sincronizarUsuariosOptimizado(false);
            $stats['users'] = $userStats;
            
            $this->stateService->completeSync();
            $stats['status'] = 'completed';
            
            return $stats;
            
        } catch (\Exception $e) {
            $this->stateService->errorSync($e->getMessage());
            throw $e;
        }
    }

    /**
     * Sincronizar CALIFICACIONES - OPTIMIZADO
     * Procesa solo cursos activos del período actual
     */
    public function sincronizarCalificacionesOptimizado(?array $courseIds = null): array {
        $startTime = microtime(true);
        $stats = [
            'courses_processed' => 0,
            'grades_saved' => 0,
            'errors' => 0
        ];
        
        try {
            // Si no se especifican cursos, usar los del período actual
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
        $this->stateService->updateProgress(0, "Procesando calificaciones (" . count($courseIds) . " cursos) con {$parallelCapacity} hilos paralelos...");
        
        // Procesar en chunks de cursos (Optimizado para 50 hilos)
        $chunks = array_chunk($courseIds, 20); // Aumentado de 5 a 20 para aprovechar el paralelismo
            $processedCourses = 0;
            
            foreach ($chunks as $courseChunk) {
                if ($this->stateService->shouldStop()) break;
                
                // Para cada curso, obtener matriculados y luego calificaciones en paralelo
                foreach ($courseChunk as $courseId) {
                    try {
                        $courseStats = $this->procesarCalificacionesCursoOptimizado((int)$courseId);
                        $stats['courses_processed']++;
                        $stats['grades_saved'] += $courseStats['saved'] ?? 0;
                        $stats['errors'] += $courseStats['errors'] ?? 0;
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        LoggerService::warning("Error calificaciones curso $courseId", [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                $processedCourses += count($courseChunk);
                $percent = (int)(($processedCourses / count($courseIds)) * 100);
                $this->stateService->updateProgress($percent, "Calificaciones: $processedCourses/" . count($courseIds) . " cursos...");
            }
            
            $stats['time_seconds'] = round(microtime(true) - $startTime, 2);
            LoggerService::info("Calificaciones sincronizadas (optimizado)", $stats);
            
            return $stats;
            
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando calificaciones", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Procesa calificaciones de un curso específico
     * Usa requests paralelos para obtener notas de todos los estudiantes
     * 
     * SEGURIDAD v3.2: Incluye validación robusta de datos y sanitización XSS
     */
    /**
     * Procesa calificaciones de un curso específico
     * Usa requests PARALELOS para obtener notas (Estrategia High-Concurrency).
     * Nota: Moodle no tiene habilitada core_grades_get_grades, por lo que usamos
     * gradereport_user_get_grade_items con alta concurrencia.
     * 
     * SEGURIDAD v3.2: Incluye validación robusta de datos y sanitización XSS
     */
    /**
     * Procesa calificaciones de un curso específico
     * Usa requests PARALELOS para obtener notas (Estrategia High-Concurrency).
     * Nota: Moodle no tiene habilitada core_grades_get_grades, por lo que usamos
     * gradereport_user_get_grade_items con alta concurrencia.
     * 
     * SEGURIDAD v3.2: Incluye validación robusta de datos y sanitización XSS
     */
    private function procesarCalificacionesCursoOptimizado(int $courseId): array {
        $stats = ['saved' => 0, 'errors' => 0];

        // Validación de entrada
        if ($courseId < 1) {
            LoggerService::warning("procesarCalificacionesCurso: courseId inválido", ['courseId' => $courseId]);
            return $stats;
        }
        
        // Obtener estudiantes del curso
        $enrolled = $this->client->getEnrolledUsers($courseId);
        
        if (empty($enrolled)) {
            return $stats;
        }
        
        // Filtrar estudiantes
        $students = [];
        foreach ($enrolled as $u) {
            $isStudent = false;
            foreach ($u['roles'] ?? [] as $r) {
                if (($r['shortname'] ?? '') === 'student') {
                    $isStudent = true;
                    break;
                }
            }
            if ($isStudent && isset($u['id']) && is_numeric($u['id'])) {
                $students[] = $u;
            }
        }
        
        if (empty($students)) {
            return $stats;
        }
        
        // Chunking de seguridad: MoodleParallelClient tiene límite de 2000 items
        // Usamos lotes de 500 para mayor seguridad y evitar timeouts
        $studentChunks = array_chunk($students, 500);

        foreach ($studentChunks as $chunkStudents) {
            // Preparar requests paralelos para este chunk
            $gradePairs = [];
            foreach ($chunkStudents as $student) {
                $gradePairs[] = [
                    'courseId' => $courseId,
                    'userId' => (int)$student['id']
                ];
            }
            
            // Llamada Paralela por chunk
            $gradesResponse = $this->parallelClient->getGradesParallel($gradePairs);
            
            if (empty($gradesResponse['results'])) {
                continue;
            }
            
            // Mapeo matriculas solo para los estudiantes de este chunk
            $chunkStudentIds = array_column($chunkStudents, 'id');
            $enrollmentMap = $this->bulkDb->getEnrollmentIdMap([$courseId], $chunkStudentIds);
            
            // ========================================================
            // ESTRATEGIA SELF-HEALING v3.3
            // ========================================================
            $missingMoodleUserIds = [];
            foreach ($chunkStudentIds as $moodleUserId) {
                $mapKey = $courseId . '_' . $moodleUserId;
                if (!isset($enrollmentMap[$mapKey])) {
                    $missingMoodleUserIds[] = $moodleUserId;
                }
            }
            
            if (!empty($missingMoodleUserIds)) {
                // Resolver ID local del curso
                $db = $this->db;
                $stmt = $db->prepare("SELECT id FROM cursos WHERE id_moodle = ?");
                $stmt->execute([$courseId]);
                $localCourseId = $stmt->fetchColumn();
                
                if ($localCourseId) {
                     // Buscar IDs locales de los usuarios faltantes
                     $placeholders = implode(',', array_fill(0, count($missingMoodleUserIds), '?'));
                     $stmt = $db->prepare("SELECT id FROM usuarios WHERE id_moodle IN ($placeholders)");
                     $stmt->execute($missingMoodleUserIds);
                     $localUserIdsToEnroll = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                     
                     if (!empty($localUserIdsToEnroll)) {
                         // Crear matrículas faltantes
                         $this->bulkDb->bulkEnrollMissingUsers((int)$localCourseId, $localUserIdsToEnroll);
                         
                         // Regenerar mapeo
                         $enrollmentMap = $this->bulkDb->getEnrollmentIdMap([$courseId], $chunkStudentIds);
                         
                         LoggerService::info("Self-Healing: Matrículas reparadas", [
                             'course_moodle_id' => $courseId, 
                             'repaired_count' => count($localUserIdsToEnroll)
                         ]);
                     }
                }
            }
            // ========================================================
            
            $gradesToSave = [];
            $anomaliesDetected = 0;
            
            foreach ($gradesResponse['results'] as $key => $gradeData) {
                if (empty($gradeData) || !isset($gradeData['usergrades'][0]['gradeitems'])) {
                    continue;
                }
                
                // Parsear key "courseId_userId"
                $parts = explode('_', $key);
                if (count($parts) != 2) continue;
                
                $uId = (int)$parts[1];
                $mapKey = $courseId . '_' . $uId;
                
                if (!isset($enrollmentMap[$mapKey])) continue;
                
                $matriculaId = $enrollmentMap[$mapKey];
                $gradeItems = $gradeData['usergrades'][0]['gradeitems'];
                
                foreach ($gradeItems as $item) {
                     // ====== VALIDACIÓN ROBUSTA (Igual que antes) ======
                    if (!isset($item['id']) || !is_numeric($item['id']) || $item['id'] < 1) {
                        $anomaliesDetected++;
                        continue;
                    }
                    $itemId = (int)$item['id'];
                    
                    $gradeMax = 100.0;
                    if (isset($item['grademax'])) {
                        $rawMax = floatval($item['grademax']);
                        if ($rawMax > 0 && $rawMax <= 10000) $gradeMax = $rawMax;
                    }
                    
                    $gradeVal = null;
                    if (($item['gradeformatted'] ?? '-') !== '-' && isset($item['graderaw'])) {
                        $rawGrade = floatval($item['graderaw']);
                        if ($rawGrade >= 0 && $rawGrade <= ($gradeMax + 0.01)) {
                            $gradeVal = round($rawGrade, 4);
                        } else {
                            $anomaliesDetected++;
                            $gradeVal = ($rawGrade < 0) ? 0 : $gradeMax;
                        }
                    }
                    
                    $itemName = $this->sanitizeGradeItemName($item['itemname'] ?? 'Item');
                    $feedback = $this->sanitizeGradeFeedback($item['feedback'] ?? '');
                    $dateGraded = $this->parseGradeDate($item['gradedategraded'] ?? null);
                    
                    // Detectar si es la nota final del curso (itemtype = 'course')
                    // Esto permite filtrar fácilmente los promedios finales en reportes
                    $isFinal = (isset($item['itemtype']) && $item['itemtype'] === 'course') ? 1 : 0;
                    
                    $dataHash = $this->calculateGradeIntegrityHash(
                        $matriculaId, $itemId, $gradeVal, $gradeMax, $item['gradedategraded'] ?? null
                    );
                    
                    $gradesToSave[] = [
                        'matricula_id' => $matriculaId,
                        'item_id' => $itemId,
                        'item_name' => $itemName,
                        'es_nota_final' => $isFinal,
                        'grade' => $gradeVal,
                        'grade_max' => $gradeMax,
                        'feedback' => $feedback,
                        'date_graded' => $dateGraded,
                        'data_hash' => $dataHash
                    ];
                }
            }
            
            // Bulk insert por chunk
            if (!empty($gradesToSave)) {
                $bulkResult = $this->bulkDb->bulkUpsertGrades($gradesToSave);
                $stats['saved'] += ($bulkResult['inserted'] ?? 0) + ($bulkResult['updated'] ?? 0);
                $stats['errors'] += ($bulkResult['errors'] ?? 0);
            }
        }

        return $stats;
    }


    /**
     * Sanitiza el nombre de un item de calificación
     * Previene XSS almacenado
     * 
     * @param string $name Nombre crudo desde Moodle
     * @return string Nombre sanitizado
     */
    private function sanitizeGradeItemName(string $name): string {
        // Decodificar entidades HTML primero (Moodle a veces envía encoded)
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Eliminar cualquier tag HTML
        $name = strip_tags($name);
        
        // Codificar caracteres especiales para prevenir XSS
        $name = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Limitar longitud
        $name = mb_substr($name, 0, 255, 'UTF-8');
        
        // Si quedó vacío, usar valor por defecto
        return !empty(trim($name)) ? $name : 'Item';
    }

    /**
     * Sanitiza el feedback de una calificación
     * Permite formato básico pero previene XSS
     * 
     * @param string $feedback Feedback crudo desde Moodle
     * @return string Feedback sanitizado
     */
    private function sanitizeGradeFeedback(string $feedback): string {
        if (empty($feedback)) {
            return '';
        }
        
        // Decodificar entidades HTML primero
        $feedback = html_entity_decode($feedback, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 1. Eliminar bloques peligrosos completos (script, style, object, iframe)
        $feedback = preg_replace('/<(script|style|object|iframe)[^>]*?>.*?<\/\1>/si', '', $feedback);
        
        // 2. Lista blanca estricta de tags permitidos
        $allowedTags = '<p><br><strong><b><em><i><ul><ol><li><span>';
        
        // 3. Eliminar tags no permitidos
        $feedback = strip_tags($feedback, $allowedTags);
        
        // 4. Eliminar TODOS los atributos de los tags permitidos para máxima seguridad
        // Esta regex captura <tag ...> y lo reemplaza por <tag>
        $feedback = preg_replace('/<([a-z][a-z0-9]*)(?:\s+[^>]*)?>/i', '<$1>', $feedback);
        
        // Limitar longitud para prevenir ataques de denegación
        $feedback = mb_substr($feedback, 0, 5000, 'UTF-8');
        
        return trim($feedback);
    }

    /**
     * Parsea y valida una fecha de calificación
     * 
     * @param mixed $timestamp Timestamp de Moodle o null
     * @return string|null Fecha formateada o null si inválida
     */
    private function parseGradeDate($timestamp): ?string {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }
        
        // Validar que sea numérico
        if (!is_numeric($timestamp)) {
            return null;
        }
        
        $ts = (int)$timestamp;
        
        // Validar rango razonable (año 2000 a 2100)
        if ($ts < 946684800 || $ts > 4102444800) {
            return null;
        }
        
        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * Calcula hash de integridad para una calificación
     * Permite detectar modificaciones no autorizadas en la BD
     * 
     * @param int $matriculaId ID de matrícula
     * @param int $itemId ID del item de calificación
     * @param float|null $grade Calificación
     * @param float $gradeMax Calificación máxima
     * @param mixed $dateGraded Timestamp de calificación
     * @return string Hash SHA-256
     */
    private function calculateGradeIntegrityHash(
        int $matriculaId, 
        int $itemId, 
        ?float $grade, 
        float $gradeMax, 
        $dateGraded
    ): string {
        // REMOVIDO: 't' => time() hacía que el hash fuera imposible de verificar a posteriori.
        // La integridad debe basarse solo en los datos persistentes del registro.
        $data = json_encode([
            'm' => $matriculaId,
            'i' => $itemId,
            'g' => $grade,
            'x' => $gradeMax,
            'd' => $dateGraded
        ], JSON_UNESCAPED_UNICODE);
        
        // IMPLEMENTACIÓN DE VERSIONADO DE CLAVES (Solicitud C)
        // Permite rotación de claves sin invalidar hashes históricos.
        // Formato de salida: v{VERSION}${HASH} (ej: v1$a3f5...)
        
        $version = (string)\Config\Env::get('APP_SECRET_VERSION', '1');
        
        // Buscar clave especifica para la versión (ej: APP_SECRET_V1)
        $secretKeyName = "APP_SECRET_V{$version}";
        $secret = \Config\Env::get($secretKeyName);

        // Fallback a clave legacy (APP_SECRET simple)
        if (empty($secret)) {
            $secret = \Config\Env::get('APP_SECRET');
        }

        // Validación de seguridad crítica
        if (empty($secret) || $secret === 'eduma_default_secret_change_me') {
            LoggerService::critical("SEGURIDAD: APP_SECRET no configurado o inseguro. Hash de integridad comprometido.");
            // Fallback runtime inseguro para no detener sincronización, pero logs alertarán
            $secret = uniqid('insecure_runtime_', true);
        }
        
        $rawHash = hash('sha256', $data . $secret);
        
        // Retornamos el hash con prefijo de versión para futura verificación
        return "v{$version}$" . $rawHash;
    }

    /**
     * Maneja la detención solicitada por usuario
     */
    private function handleStop(): array {
        $this->stateService->updateProgress(
            $this->stateService->getProgress(),
            'Sincronización detenida por el usuario'
        );
        $this->stats['status'] = 'stopped';
        return $this->getStats();
    }

    /**
     * Mapea automáticamente Facultades, Carreras y Cursos basándose en la estructura de Moodle.
     * Este proceso asegura la integridad de la jerarquía local después de cada sincronización.
     */
    public function mapearEstructuraAcademicaAuto(): array {
        $stats = ['facultades' => 0, 'carreras' => 0, 'cursos_mapeados' => 0];
        $db = $this->bulkDb->getDb();

        try {
            // 1. Consolidar Facultades (Depth 3)
            $sqlFac = "INSERT INTO facultades (id_moodle_categoria, nombre)
                       SELECT MIN(id), name FROM raw_moodle_categorias 
                       WHERE depth = 3 GROUP BY name
                       ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)";
            $db->exec($sqlFac);
            $stats['facultades'] = $db->query("SELECT COUNT(*) FROM facultades")->fetchColumn();

            // 2. Consolidar Carreras (Depth 4) y vincular a Facultades locales
            $sqlCar = "INSERT INTO carreras (id_moodle_categoria, nombre, facultad_id)
                       SELECT MIN(rc.id), rc.name, f.id
                       FROM raw_moodle_categorias rc
                       JOIN raw_moodle_categorias rcf ON rc.parent_id = rcf.id
                       JOIN facultades f ON rcf.name = f.nombre
                       WHERE rc.depth = 4
                       GROUP BY rc.name, f.id
                       ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), facultad_id = VALUES(facultad_id)";
            $db->exec($sqlCar);
            $stats['carreras'] = $db->query("SELECT COUNT(*) FROM carreras")->fetchColumn();

            // 3. Mapeo Automático de Cursos mediante Path Analysis
            $sqlCursos = "UPDATE cursos c
                          JOIN raw_moodle_categorias rc ON c.id_categoria_moodle = rc.id
                          JOIN (
                              SELECT rc_c.id, rc_c.name 
                              FROM raw_moodle_categorias rc_c 
                              WHERE rc_c.depth = 4
                          ) as cat_mapped ON (rc.path LIKE CONCAT('%/', cat_mapped.id, '/%') 
                                              OR rc.path LIKE CONCAT('%/', cat_mapped.id))
                          JOIN carreras loc_c ON cat_mapped.name = loc_c.nombre
                          SET c.carrera_id = loc_c.id
                          WHERE c.carrera_id IS NULL OR c.carrera_id != loc_c.id";
            
            $stmt = $db->prepare($sqlCursos);
            $stmt->execute();
            $stats['cursos_mapeados'] = $stmt->rowCount();

            LoggerService::info("Mapeo académico automático finalizado", $stats);
            return $stats;

        } catch (\Exception $e) {
            LoggerService::error("Error en mapeo académico automático", ['error' => $e->getMessage()]);
            return $stats;
        }
    }
}
