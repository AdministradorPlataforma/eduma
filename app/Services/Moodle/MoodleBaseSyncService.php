<?php
declare(strict_types=1);

namespace App\Services\Moodle;

use App\Services\BaseService;
use App\Services\BulkDatabaseService;
use App\Services\SyncStateDbService;
use App\Services\LoggerService;
use Modules\Moodle\MoodleClient;
use Modules\Moodle\MoodleParallelClient;
use PDO;

/**
 * Clase base para servicios de sincronización con Moodle.
 * Proporciona infraestructura común: clientes, estado, stats y logging.
 */
abstract class MoodleBaseSyncService extends BaseService {

    protected MoodleClient $client;
    protected MoodleParallelClient $parallelClient;
    protected BulkDatabaseService $bulkDb;
    protected SyncStateDbService $stateService;
    protected PDO $db;
    
    /** @var array Estadísticas de la fase actual */
    protected array $stats = [
        'processed' => 0,
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'start_time' => null
    ];

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
        
        $this->stats['start_time'] = microtime(true);
    }

    /**
     * Verifica la conexión con Moodle
     */
    public function checkConnection(): array {
        return $this->client->healthCheck();
    }

    /**
     * Obtiene estadísticas formateadas
     */
    public function getStats(): array {
        $this->stats['time_seconds'] = round(microtime(true) - $this->stats['start_time'], 2);
        return $this->stats;
    }

    /**
     * Sincroniza estadísticas con el servicio de estado global
     */
    protected function updateStateStats(): void {
        $this->stateService->updateStats(
            (int)($this->stats['processed'] ?? 0),
            (int)($this->stats['updated'] ?? 0),
            (int)($this->stats['errors'] ?? 0)
        );
    }

    /**
     * Maneja la detención solicitada por usuario
     */
    protected function handleStop(): array {
        $this->stateService->updateProgress(
            $this->stateService->getProgress(),
            'Sincronización detenida por el usuario'
        );
        $this->stats['status'] = 'stopped';
        return $this->getStats();
    }

    /**
     * Registra métricas de fase en el servicio de estado
     */
    protected function recordPhaseMetrics(string $phase, array $stats): void {
        $this->stateService->recordMetric($phase, 'processed', $stats['processed'] ?? 0);
        $this->stateService->recordMetric($phase, 'duration', $stats['time_seconds'] ?? 0, 'seconds');
    }
}
