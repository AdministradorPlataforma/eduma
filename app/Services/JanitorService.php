<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\SyncStateDbService;
use App\Services\LoggerService;
use App\Helpers\CacheHelper;
use PDO;

/**
 * Servicio Janitor (Conserje) para mantenimiento automático del sistema.
 */
class JanitorService extends BaseService
{
    private SyncStateDbService $syncState;
    private PDO $db;

    public function __construct(SyncStateDbService $syncState, PDO $db)
    {
        $this->syncState = $syncState;
        $this->db = $db;
    }

    /**
     * Ejecuta todas las tareas de mantenimiento.
     */
    public function runAll(): array
    {
        $report = [];
        
        // 1. Limpiar logs de sincronización antiguos (Retener 30 días)
        $report['sync_logs_purged'] = $this->syncState->cleanOldLogs(30);
        
        // 2. Limpiar logs de auditoría (Retener 90 días por cumplimiento)
        $report['audit_logs_purged'] = $this->purgeAuditLogs(90);
        
        // 3. Limpiar caché expirada
        CacheHelper::clear();
        $report['cache_cleared'] = true;
        
        // 4. Limpiar Jobs completados antiguos
        $report['jobs_cleaned'] = $this->cleanOldJobs(15);

        LoggerService::info("Mantenimiento Janitor completado", $report);
        
        return $report;
    }

    private function purgeAuditLogs(int $days): int
    {
        $sql = "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    private function cleanOldJobs(int $days): int
    {
        $sql = "DELETE FROM queue_jobs WHERE status IN ('completed', 'failed') AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
