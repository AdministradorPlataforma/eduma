<?php
declare(strict_types=1);

namespace App\Services\Sync;

use App\Services\BaseService;
use Modules\Moodle\MoodleClient;
use App\Services\BulkDatabaseService;
use App\Services\SyncStateDbService;
use App\Services\LoggerService;

class UserSyncService extends BaseService
{
    private MoodleClient $client;
    private BulkDatabaseService $bulkDb;
    private SyncStateDbService $stateService;

    public function __construct(
        MoodleClient $client,
        BulkDatabaseService $bulkDb,
        SyncStateDbService $stateService
    ) {
        $this->client = $client;
        $this->bulkDb = $bulkDb;
        $this->stateService = $stateService;
    }

    /**
     * Sincroniza usuarios desde Moodle de forma optimizada.
     */
    public function sync(bool $force = false, array $options = []): array
    {
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
            $this->stateService->updateProgress(35, "Obteniendo usuarios desde Moodle...");
            
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
            
            $bulkResult = $this->bulkDb->bulkUpsertUsers($allUsers, $options);
            $stats = array_merge($stats, $bulkResult);
            
            $stats['time_seconds'] = round(microtime(true) - $startTime, 2);
            LoggerService::info("Sincronización de usuarios finalizada", $stats);
            
            return $stats;
            
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando usuarios", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
