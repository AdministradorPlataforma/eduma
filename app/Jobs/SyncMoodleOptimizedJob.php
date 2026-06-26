<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Services\MoodleSyncOptimizedService;
use App\Services\SyncStateDbService;
use App\Services\LoggerService;
use App\Exceptions\Moodle\StopSyncException;
use Exception;

/**
 * Job de Sincronización Optimizado
 * 
 * Versión mejorada que utiliza:
 * - Procesamiento paralelo
 * - Bulk operations
 * - Estado en BD
 * 
 * @version 2.0
 */
class SyncMoodleOptimizedJob implements JobInterface {
    
    /** @var string Tipo de sincronización: 'all', 'users', 'courses', 'categories', 'cohorts', 'grades', 'delta' */
    private string $syncType;
    
    /** @var bool Forzar sincronización completa */
    private bool $force;
    
    /** @var array|null IDs específicos a sincronizar (opcional) */
    private ?array $specificIds;

    /** @var array Opciones adicionales (ej. {regenerate_passwords: true}) */
    private array $options;

    public function __construct(
        string $syncType = 'all', 
        bool $force = false,
        ?array $specificIds = null,
        array $options = []
    ) {
        $this->syncType = $syncType;
        $this->force = $force;
        $this->specificIds = $specificIds;
        $this->options = $options;
    }

    public function handle(): void {
        $container = \App\Core\Container::getInstance();
        $service = $container->get(\App\Services\MoodleSyncOptimizedService::class);
        $state = $container->get(\App\Services\SyncStateDbService::class);
        $startTime = microtime(true);

        try {
            LoggerService::info("SyncMoodleOptimizedJob: Iniciando", [
                'type' => $this->syncType,
                'force' => $this->force
            ]);

            $result = [];

            switch ($this->syncType) {
                case 'all':
                    $result = $service->sincronizarTodo($this->force, $this->options);
                    break;

                case 'delta':
                    $result = $service->sincronizarDelta();
                    break;

                case 'unlocked_users':
                    $result = $service->sincronizarUsuariosDesbloqueados();
                    break;

                case 'enrollments_2026':
                    $result = $service->sincronizarMatriculas2026();
                    break;

                case 'categories':
                    $state->startSync('categories');
                    $result = $service->sincronizarCategorias();
                    $state->completeSync('categories');
                    break;

                case 'courses':
                    $state->startSync('courses');
                    $result = $service->sincronizarCursosOptimizado();
                    $state->completeSync('courses');
                    break;

                case 'users':
                    $state->startSync('users');
                    $result = $service->sincronizarUsuariosOptimizado($this->force, $this->options);
                    $state->completeSync('users');
                    break;

                case 'enrollments':
                    $state->startSync('enrollments');
                    $result = $service->sincronizarMatriculasOptimizado();
                    $state->completeSync('enrollments');
                    break;

                case 'cohorts':
                    $state->startSync('cohorts');
                    $result = $service->sincronizarCohortesOptimizado();
                    $state->completeSync('cohorts');
                    break;

                case 'grades':
                    $state->startSync('grades');
                    $result = $service->sincronizarCalificacionesOptimizado($this->specificIds);
                    $state->completeSync('grades');
                    break;

                default:
                    throw new Exception("Tipo de sincronización no soportado: {$this->syncType}");
            }

            $elapsed = round(microtime(true) - $startTime, 2);
            
            LoggerService::info("SyncMoodleOptimizedJob: Completado", [
                'type' => $this->syncType,
                'elapsed_seconds' => $elapsed,
                'result' => $result
            ]);

        } catch (StopSyncException $e) {
            // Detención solicitada por el usuario — NO es un error
            $elapsed = round(microtime(true) - $startTime, 2);
            
            $state->markAsStopped();
            
            LoggerService::info("SyncMoodleOptimizedJob: Detenido por el usuario", [
                'type' => $this->syncType,
                'elapsed_seconds' => $elapsed
            ]);
            
            // NO re-lanzar: no es un fallo, es detención intencional
            return;

        } catch (Exception $e) {
            $elapsed = round(microtime(true) - $startTime, 2);
            
            $state->errorSync($e->getMessage(), $this->syncType);
            
            LoggerService::error("SyncMoodleOptimizedJob: Error", [
                'type' => $this->syncType,
                'elapsed_seconds' => $elapsed,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new Exception(
                "Error en SyncMoodleOptimizedJob [{$this->syncType}]: " . $e->getMessage(), 
                0, 
                $e
            );
        }
    }

    /**
     * Serialización para cola
     */
    public function __serialize(): array {
        return [
            'syncType' => $this->syncType,
            'force' => $this->force,
            'specificIds' => $this->specificIds,
            'options' => $this->options
        ];
    }

    public function __unserialize(array $data): void {
        $this->syncType = $data['syncType'];
        $this->force = $data['force'];
        $this->specificIds = $data['specificIds'] ?? null;
        $this->options = $data['options'] ?? [];
    }
}
