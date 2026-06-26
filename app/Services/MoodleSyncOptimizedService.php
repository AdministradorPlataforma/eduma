<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Moodle\CategorySyncService;
use App\Services\Moodle\CourseSyncService;
use App\Services\Moodle\UserSyncService;
use App\Services\SyncStateDbService;
use App\Services\SyncCleanupService;
use App\Services\LoggerService;
use App\Exceptions\Moodle\StopSyncException;
use Config\MoodleWS;
use PDO;

/**
 * Orquestador de Sincronización Optimizada con Moodle (v4.0)
 * 
 * Delega la ejecución a sub-servicios especializados para cada entidad.
 */
class MoodleSyncOptimizedService extends BaseService {

    private CategorySyncService $categoryService;
    private CourseSyncService $courseService;
    private UserSyncService $userService;
    private SyncStateDbService $stateService;
    private SyncCleanupService $cleanupService;
    private PDO $db;
    
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
        CategorySyncService $categoryService,
        CourseSyncService $courseService,
        UserSyncService $userService,
        SyncStateDbService $stateService,
        SyncCleanupService $cleanupService,
        PDO $db
    ) {
        $this->categoryService = $categoryService;
        $this->courseService = $courseService;
        $this->userService = $userService;
        $this->stateService = $stateService;
        $this->cleanupService = $cleanupService;
        $this->db = $db;
        
        $this->stats['start_time'] = microtime(true);
    }

    public function checkConnection(): array {
        return $this->categoryService->checkConnection();
    }

    public function getStats(): array {
        $this->stats['elapsed_seconds'] = round(microtime(true) - $this->stats['start_time'], 2);
        return $this->stats;
    }

    private function updateStateStats(): void {
        $this->stateService->updateStats(
            (int)($this->stats['processed'] ?? 0),
            (int)($this->stats['updated'] ?? 0),
            (int)($this->stats['errors'] ?? 0)
        );
    }

    public function sincronizarTodo(bool $force = false, array $options = []): array {
        $shouldResume = $options['resume'] ?? false;
        $checkpoint = $this->stateService->getCheckpoint();

        if ($shouldResume && empty($checkpoint)) $shouldResume = false;

        if (!$shouldResume) {
            $this->stateService->clearCheckpoint();
            $this->stateService->startSync('all');
            $this->stateService->startBatch('all');
            $completedPhases = [];
        } else {
            $this->stateService->startSync('all', true);
            if (isset($checkpoint['stats'])) $this->stats = $checkpoint['stats'];
            $completedPhases = $checkpoint['completed_phases'] ?? [];
            LoggerService::info("Reanudando sincronización desde checkpoint", $checkpoint);
        }

        try {
            // FASE 1: Categorías
            if (!in_array('categories', $completedPhases)) {
                $this->stateService->updateProgress(5, 'Sincronizando categorías...');
                $res = $this->categoryService->sincronizar();
                $this->accumulateStats('categories', $res);
                $completedPhases[] = 'categories';
                $this->saveCheckpoint('categories', $completedPhases);
            }

            if ($this->stateService->shouldStop()) return $this->handleStop();

            // FASE 2: Cursos
            if (!in_array('courses', $completedPhases)) {
                $this->stateService->updateProgress(15, 'Sincronizando cursos...');
                $res = $this->courseService->sincronizar();
                $this->accumulateStats('courses', $res);
                $completedPhases[] = 'courses';
                $this->saveCheckpoint('courses', $completedPhases);
            }

            if ($this->stateService->shouldStop()) return $this->handleStop();

            // FASE 3: Usuarios
            if (!in_array('users', $completedPhases)) {
                $this->stateService->updateProgress(30, 'Sincronizando usuarios...');
                $res = $this->userService->sincronizar($options);
                $this->accumulateStats('users', $res);
                $completedPhases[] = 'users';
                $this->saveCheckpoint('users', $completedPhases);
            }

            if ($this->stateService->shouldStop()) return $this->handleStop();

            // FASE 4: Matrículas (Maneja su propio checkpoint interno de offset)
            if (!in_array('enrollments', $completedPhases)) {
                $this->stateService->updateProgress(70, 'Sincronizando matrículas...');
                $offset = ($shouldResume && isset($checkpoint['enrollments_offset'])) ? (int)$checkpoint['enrollments_offset'] : 0;
                $res = $this->userService->sincronizarMatriculas($offset, $completedPhases);
                
                // Las matrículas ya guardan su propio progreso, solo actualizamos el orquestador al terminar la fase
                $this->accumulateStats('enrollments', $res);
                if (!isset($res['aborted']) || !$res['aborted']) {
                    $completedPhases[] = 'enrollments';
                    $this->saveCheckpoint('enrollments', $completedPhases);
                }
            }

            if ($this->stateService->shouldStop()) return $this->handleStop();

            // FASE 4.5: Perfiles
            if (!in_array('profiles', $completedPhases)) {
                $this->stateService->updateProgress(83, 'Procesando perfiles...');
                $res = $this->userService->sincronizarPerfiles();
                $this->accumulateStats('profiles', $res);
                $completedPhases[] = 'profiles';
                $this->saveCheckpoint('profiles', $completedPhases);
            }

            // FASE 5: Cohortes
            if (!in_array('cohorts', $completedPhases)) {
                $this->stateService->updateProgress(88, 'Sincronizando cohortes...');
                $res = $this->userService->sincronizarCohortes();
                $this->accumulateStats('cohorts', $res);
                $completedPhases[] = 'cohorts';
                $this->saveCheckpoint('cohorts', $completedPhases);
            }

            // FASE 6: Limpieza y Mapeo
            $this->stateService->updateProgress(95, 'Verificando huérfanos...');
            $this->stats['phases']['orphans'] = $this->cleanupService->obtenerResumenHuerfanos();
            
            $this->stateService->updateProgress(98, 'Mapeando estructura académica...');
            $this->stats['phases']['mapping'] = $this->categoryService->mapearEstructuraAcademicaAuto();

            // Finalizar
            $this->stateService->finishBatch('completed', $this->stats);
            $this->stateService->completeSync();
            $this->stateService->clearCheckpoint();
            $this->stats['status'] = 'completed';
            
            return $this->getStats();

        } catch (StopSyncException $e) {
            LoggerService::info("Sincronización detenida por el usuario (StopSyncException)");
            return $this->handleStop();
        } catch (\Exception $e) {
            if ($e->getMessage() === 'USER_STOP_REQUESTED' || $e instanceof StopSyncException) {
                return $this->handleStop();
            }
            $this->stateService->finishBatch('error', $this->stats);
            $this->stateService->errorSync($e->getMessage());
            $this->stats['status'] = 'error';
            $this->stats['error'] = $e->getMessage();
            throw $e;
        }
    }

    private function accumulateStats(string $phase, array $res): void {
        $this->stats['phases'][$phase] = $res;
        $this->stats['processed'] += $res['processed'] ?? 0;
        $this->stats['updated'] += $res['updated'] ?? 0;
        $this->stats['errors'] += $res['errors'] ?? 0;
        
        $this->stateService->recordMetric($phase, 'processed', $res['processed'] ?? 0);
        $this->stateService->recordMetric($phase, 'duration', $res['time_seconds'] ?? 0, 'seconds');
        $this->updateStateStats();
    }

    private function saveCheckpoint(string $phase, array $completed): void {
        $this->stateService->saveCheckpoint($phase, [
            'completed_phases' => $completed,
            'stats' => $this->stats
        ]);
    }

    private function handleStop(): array {
        LoggerService::info("Orquestador: Ejecutando graceful shutdown", $this->stats);
        
        // 1. Actualizar progreso con mensaje final
        $this->stateService->updateProgress(
            $this->stateService->getProgress(), 
            'Sincronización detenida por el usuario'
        );
        
        // 2. Marcar batch como detenido
        $this->stateService->finishBatch('stopped', $this->stats);
        
        // 3. Marcar la sincronización como completada (estado 'stopped') en DB
        $this->stateService->markAsStopped();
        
        // 4. Limpiar checkpoint para que no intente reanudar
        $this->stateService->clearCheckpoint();
        
        $this->stats['status'] = 'stopped';
        return $this->getStats();
    }

    public function sincronizarDelta(): array {
        $this->stateService->startSync('delta');
        try {
            // El delta usa el sync de usuarios optimizado
            $res = $this->userService->sincronizar(['mode' => 'delta']);
            $this->stateService->completeSync();
            return ['status' => 'completed', 'users' => $res];
        } catch (\Exception $e) {
            $this->stateService->errorSync($e->getMessage());
            throw $e;
        }
    }

    public function sincronizarUsuariosDesbloqueados(): array {
        $this->stateService->startSync('unlocked_users');
        $this->stateService->startBatch('unlocked_users');
        try {
            $res = $this->userService->sincronizarUsuariosDesbloqueados();
            $this->stateService->finishBatch('completed', $res);
            $this->stateService->completeSync();
            return $res;
        } catch (\Exception $e) {
            $this->stateService->errorSync($e->getMessage());
            throw $e;
        }
    }

    public function sincronizarMatriculas2026(): array {
        $this->stateService->startSync('enrollments_2026');
        $this->stateService->startBatch('enrollments_2026');
        try {
            $courses = $this->courseService->getCoursesByYear(2026);
            $res = $this->userService->sincronizarMatriculasPorCursos($courses);
            $this->stateService->finishBatch('completed', $res);
            $this->stateService->completeSync();
            return $res;
        } catch (\Exception $e) {
            $this->stateService->errorSync($e->getMessage());
            throw $e;
        }
    }

    public function sincronizarCalificaciones(?array $courseIds = null): array {
        return $this->courseService->sincronizarCalificaciones($courseIds);
    }

    // Wrappers para compatibilidad con SyncMoodleOptimizedJob
    public function sincronizarCategorias(): array {
        return $this->categoryService->sincronizar();
    }

    public function sincronizarCursosOptimizado(): array {
        return $this->courseService->sincronizar();
    }

    public function sincronizarUsuariosOptimizado(bool $force = false, array $options = []): array {
        return $this->userService->sincronizar($options);
    }

    public function sincronizarMatriculasOptimizado(): array {
        return $this->userService->sincronizarMatriculas();
    }

    public function sincronizarCohortesOptimizado(): array {
        return $this->userService->sincronizarCohortes();
    }

    public function sincronizarCalificacionesOptimizado(?array $courseIds = null): array {
        return $this->courseService->sincronizarCalificaciones($courseIds);
    }
}

