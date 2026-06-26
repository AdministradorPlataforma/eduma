<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Services\LoggerService;

/**
 * Servicio de Estado de Sincronización - Basado en Base de Datos
 * 
 * Reemplaza el sistema basado en archivo JSON por uno basado en BD.
 * Beneficios:
 * - Operaciones atómicas
 * - Soporte para múltiples workers
 * - Persistencia confiable
 * - Histórico de sincronizaciones
 * 
 * @version 2.0
 */
class SyncStateDbService extends BaseService {

    private PDO $db;
    private string $currentBatchId;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->currentBatchId = $this->generateBatchId();
    }

    /**
     * Genera un ID único para el batch de sincronización
     */
    private function generateBatchId(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Inicia una nueva sincronización
     */
    public function startSync(string $type = 'all', bool $isResume = false): string {
        // Primero, marcar cualquier sync anterior como 'stopped' si quedó en 'running'
        $this->db->exec(
            "UPDATE sync_status SET last_sync_status = 'stopped' 
             WHERE last_sync_status = 'running'"
        );

        // Actualizar o insertar estado para este tipo
        $sql = "INSERT INTO sync_status (entity_type, last_sync_start, last_sync_status, total_processed, total_errors)
                VALUES (:type, NOW(), 'running', 0, 0)
                ON DUPLICATE KEY UPDATE
                last_sync_start = NOW(),
                last_sync_status = 'running',
                total_processed = 0,
                total_errors = 0,
                last_error_message = NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['type' => $type]);

        // Registrar en log
        $msg = $isResume ? 'Sincronización reanudada automáticamente' : 'Sincronización iniciada';
        $this->logSync($type, 'info', 'running', $msg);

        // Configurar estado JSON
        $jsonState = [
            'status' => 'running',
            'type' => $type,
            'start_time' => date('Y-m-d H:i:s'),
            'message' => $isResume ? 'Reanudando sincronización...' : 'Iniciando...',
            'stop_requested' => false,
            'batch_id' => $this->currentBatchId
        ];

        // Solo resetear progreso si NO es reanudación
        if (!$isResume) {
            $jsonState['progress'] = 0;
            $jsonState['total_processed'] = 0;
            $jsonState['total_updated'] = 0;
            $jsonState['total_errors'] = 0;
        }

        // También actualizar archivo JSON para compatibilidad con UI existente
        $this->updateLegacyJsonState($jsonState);

        return $this->currentBatchId;
    }

    /**
     * Actualiza las estadísticas detalladas (procesados, actualizados, errores)
     */
    public function updateStats(int $processed, int $updated, int $errors, ?string $entityType = null): void {
        // Actualizar JSON legacy
        $state = $this->getLegacyJsonState();
        $state['total_processed'] = $processed;
        $state['total_updated'] = $updated;
        $state['total_errors'] = $errors;
        $this->updateLegacyJsonState($state);

        // Actualizar BD (solo columnas existentes)
        if ($entityType) {
            $sql = "UPDATE sync_status 
                    SET total_processed = :processed,
                        total_errors = :errors
                    WHERE entity_type = :type";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'processed' => $processed, 
                'errors' => $errors,
                'type' => $entityType
            ]);
        }
    }

    /**
     * Actualiza el progreso de la sincronización
     */
    public function updateProgress(int $percent, string $message, ?string $entityType = null): void {
        // Actualizar JSON legacy para UI
        $state = $this->getLegacyJsonState();
        $state['progress'] = min(100, max(0, $percent));
        $state['message'] = $message;
        $this->updateLegacyJsonState($state);
        // Nota: updateProgress ya no toca DB stats para evitar sobrescribir con 0 si no se pasan stats completos
    }

    /**
     * Marca la sincronización como completada
     */
    public function completeSync(?string $entityType = null): void {
        $type = $entityType ?? $this->getCurrentEntityType() ?? 'all';

        $sql = "UPDATE sync_status 
                SET last_sync_status = 'completed',
                    last_sync_end = NOW()
                WHERE entity_type = :type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['type' => $type]);

        // Log
        $this->logSync($type, 'info', 'success', 'Sincronización completada exitosamente');

        // JSON legacy - Mantener stats finales
        $state = $this->getLegacyJsonState();
        $state['status'] = 'completed';
        $state['progress'] = 100;
        $state['message'] = 'Sincronización completada';
        $state['stop_requested'] = false;
        $state['end_time'] = time();
        $this->updateLegacyJsonState($state);
    }

    /**
     * Marca la sincronización con error
     */
    public function errorSync(string $errorMessage, ?string $entityType = null): void {
        $type = $entityType ?? $this->getCurrentEntityType() ?? 'all';

        $sql = "UPDATE sync_status 
                SET last_sync_status = 'error',
                    last_sync_end = NOW(),
                    last_error_message = :error,
                    total_errors = total_errors + 1
                WHERE entity_type = :type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['error' => $errorMessage, 'type' => $type]);

        // Log
        $this->logSync($type, 'error', 'error', $errorMessage);

        // JSON legacy
        $state = $this->getLegacyJsonState();
        $state['status'] = 'error';
        $state['message'] = $errorMessage;
        $this->updateLegacyJsonState($state);
    }

    /**
     * Solicita detener la sincronización
     */
    public function requestStop(): void {
        // Marcar en BD para control de estado y corte de procesos
        $this->db->exec(
            "UPDATE sync_status SET last_sync_status = 'stopping' WHERE last_sync_status = 'running'"
        );

        // Mantener JSON legacy solo para compatibilidad visual.
        $state = $this->getLegacyJsonState();
        $state['stop_requested'] = true;
        $state['status'] = 'stopping';
        $state['message'] = 'Deteniendo...';
        $this->updateLegacyJsonState($state);

        LoggerService::info("SyncStateDb: Stop solicitado por el usuario");
    }

    private static float $lastShouldStopCheck = 0;
    private static bool $lastShouldStopResult = false;

    /**
     * Verifica si se solicitó detener
     */
    public function shouldStop(): bool {
        $now = microtime(true);
        if ($now - self::$lastShouldStopCheck < 2.0) {
            return self::$lastShouldStopResult;
        }

        try {
            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM sync_status WHERE last_sync_status = 'stopping'"
            );
            $result = ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            $state = $this->getLegacyJsonState();
            $result = ($state['stop_requested'] ?? false) === true;
        }

        self::$lastShouldStopCheck = $now;
        self::$lastShouldStopResult = $result;
        return $result;
    }

    private function isQueueWorkerActive(): bool {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM queue_jobs WHERE status = 'running'");
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Marca la sincronización como detenida (estado final) en BD y JSON
     * Llamado por el orquestador después de ejecutar graceful shutdown
     */
    public function markAsStopped(): void {
        // Actualizar BD
        $this->db->exec(
            "UPDATE sync_status SET last_sync_status = 'stopped', last_sync_end = NOW() 
             WHERE last_sync_status IN ('running', 'stopping')"
        );

        // Actualizar JSON legacy
        $state = $this->getLegacyJsonState();
        $state['status'] = 'stopped';
        $state['stop_requested'] = false;
        $state['message'] = 'Sincronización detenida por el usuario';
        $state['end_time'] = time();
        $this->updateLegacyJsonState($state);

        // Log
        $this->logSync(
            $state['type'] ?? 'all', 
            'warning', 
            'stopped', 
            'Sincronización detenida por solicitud del usuario'
        );
    }

    /**
     * Obtiene el progreso actual
     */
    public function getProgress(): int {
        $state = $this->getLegacyJsonState();
        return (int)($state['progress'] ?? 0);
    }

    /**
     * Obtiene el estado completo actual
     */
    public function getStatus(?string $entityType = null): array {
        $type = $entityType ?? 'all';

        $stmt = $this->db->prepare(
            "SELECT * FROM sync_status WHERE entity_type = ?"
        );
        $stmt->execute([$type]);
        $dbStatus = $stmt->fetch(PDO::FETCH_ASSOC);

        // Combinar con JSON legacy para progreso en tiempo real
        $jsonState = $this->getLegacyJsonState();
        $status = $dbStatus['last_sync_status'] ?? ($jsonState['status'] ?? 'idle');

        if ($status === 'stopping' && !$this->isQueueWorkerActive()) {
            LoggerService::warning("SyncStateDb: estado 'stopping' sin worker activo, finalizando como 'stopped'", ['type' => $type]);
            $this->markAsStopped();
            $status = 'stopped';
            $jsonState = $this->getLegacyJsonState();
        }

        return [
            'status' => $status,
            'progress' => $jsonState['progress'] ?? 0,
            'message' => $jsonState['message'] ?? 'Listo',
            'type' => $jsonState['type'] ?? $type,
            'start_time' => $dbStatus['last_sync_start'] ?? ($jsonState['start_time'] ?? null),
            'end_time' => $dbStatus['last_sync_end'] ?? ($jsonState['end_time'] ?? null),
            'total_processed' => $jsonState['total_processed'] ?? ($dbStatus['total_processed'] ?? 0),
            'total_updated' => $jsonState['total_updated'] ?? 0,
            'total_errors' => $jsonState['total_errors'] ?? ($dbStatus['total_errors'] ?? 0),
            'last_error' => $dbStatus['last_error_message'] ?? null,
            'stop_requested' => $status === 'stopping' || ($jsonState['stop_requested'] ?? false),
            'batch_id' => $this->currentBatchId,
            'estimated_remaining' => $this->calculateRemainingTime(
                $jsonState['progress'] ?? 0, 
                $dbStatus['last_sync_start'] ?? ($jsonState['start_time'] ?? null),
                $status
            )
        ];
    }

    /**
     * Calcula tiempo estimando restante
     */
    private function calculateRemainingTime(int $progress, $startTime, string $status): string {
        if ($status !== 'running' || $progress <= 0 || empty($startTime)) {
            return 'N/A';
        }

        try {
            $start = is_numeric($startTime) ? $startTime : strtotime($startTime);
            $elapsed = time() - $start;
            
            if ($elapsed <= 0) return 'Calculando...';

            $totalEstimate = ($elapsed * 100) / $progress;
            $remaining = $totalEstimate - $elapsed;

            if ($remaining < 60) {
                return round($remaining) . ' seg';
            }
            return round($remaining / 60, 1) . ' min';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Obtiene la última sincronización exitosa
     */
    public function getLastSuccessfulSync(?string $entityType = null): ?array {
        $sql = "SELECT * FROM sync_status 
                WHERE last_sync_status = 'completed'";
        
        if ($entityType) {
            $sql .= " AND entity_type = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$entityType]);
        } else {
            $sql .= " ORDER BY last_sync_end DESC LIMIT 1";
            $stmt = $this->db->query($sql);
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Registra un evento en el log de sincronización
     */
    public function logSync(
        string $entityType, 
        string $estado, 
        string $action, 
        string $mensaje,
        ?int $entityId = null
    ): void {
        try {
            $sql = "INSERT INTO sync_logs 
                    (batch_id, entidad, estado, mensaje, created_at)
                    VALUES (:batch, :entity, :estado, :msg, NOW())";

            // Concatenar acción e ID al mensaje ya que no hay columnas específicas
            $fullMessage = "[$action] " . ($entityId ? "(ID: $entityId) " : "") . $mensaje;

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'batch' => $this->currentBatchId ?? 'system', // Valor por defecto si es null
                'entity' => $entityType,
                'estado' => $estado,
                'msg' => substr($fullMessage, 0, 65535) // Asegurar que cabe en TEXT
            ]);
        } catch (\Exception $e) {
            // No fallar si el log falla
            error_log("Error en sync log: " . $e->getMessage());
        }
    }

    /**
     * Obtiene estadísticas históricas de sincronización
     */
    public function getHistoricalStats(int $days = 7): array {
        $sql = "SELECT 
                    DATE(created_at) as fecha,
                    entidad,
                    'Sincronización' as action,
                    estado,
                    COUNT(*) as cantidad
                FROM sync_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at), entidad, estado
                ORDER BY fecha DESC, entidad";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Limpia logs antiguos
     */
    public function cleanOldLogs(int $daysToKeep = 30): int {
        $sql = "DELETE FROM sync_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$daysToKeep]);
        
        return $stmt->rowCount();
    }

    /**
     * Incrementa contador de procesados
     */
    public function incrementProcessed(string $entityType, int $count = 1): void {
        $sql = "UPDATE sync_status 
                SET total_processed = total_processed + ?
                WHERE entity_type = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$count, $entityType]);
    }

    /**
     * Incrementa contador de errores
     */
    public function incrementErrors(string $entityType, int $count = 1): void {
        $sql = "UPDATE sync_status 
                SET total_errors = total_errors + ?
                WHERE entity_type = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$count, $entityType]);
    }

    // ============================================================
    // MÉTODOS PRIVADOS - Compatibilidad con JSON legacy
    // ============================================================

    private function getLegacyJsonFilePath(): string {
        return dirname(__DIR__, 2) . '/storage/sync_state.json';
    }

    private function getLegacyJsonState(): array {
        $path = $this->getLegacyJsonFilePath();
        if (!file_exists($path)) {
            return [
                'status' => 'idle',
                'progress' => 0,
                'message' => 'Listo',
                'stop_requested' => false
            ];
        }
        $content = file_get_contents($path);
        return json_decode($content, true) ?? [];
    }

    private function updateLegacyJsonState(array $newState): void {
        $path = $this->getLegacyJsonFilePath();
        $dir = dirname($path);
        
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $current = $this->getLegacyJsonState();
        $merged = array_merge($current, $newState);
        
        file_put_contents($path, json_encode($merged, JSON_PRETTY_PRINT), LOCK_EX);
    }

    // ============================================================
    // GESTIÓN DE CHECKPOINTS (Reanudación)
    // ============================================================

    public function saveCheckpoint(string $phase, array $context = []): void {
        $state = $this->getLegacyJsonState();
        $state['checkpoint'] = [
            'phase' => $phase,
            'context' => $context,
            'timestamp' => time()
        ];
        $this->updateLegacyJsonState($state);
    }

    public function getCheckpoint(): ?array {
        $state = $this->getLegacyJsonState();
        if (!isset($state['checkpoint']) || !is_array($state['checkpoint'])) {
            return null;
        }
        $cp = $state['checkpoint'];
        if (!isset($cp['phase']) || !isset($cp['context'])) {
            return null;
        }
        return $cp;
    }

    public function clearCheckpoint(): void {
        $state = $this->getLegacyJsonState();
        if (isset($state['checkpoint'])) {
            unset($state['checkpoint']);
            $this->updateLegacyJsonState($state);
        }
    }

    private function getCurrentEntityType(): ?string {
        $state = $this->getLegacyJsonState();
        return $state['type'] ?? null;
    }

    /**
     * ============================================================
     * TRAZABILIDAD AVANZADA (Nuevas Tablas)
     * ============================================================
     */

    /**
     * Inicia un registro formal en sync_batches (UUID persistente)
     */
    public function startBatch(string $type, string $triggeredBy = 'system'): void {
        try {
            $sql = "INSERT INTO sync_batches (batch_id, sync_type, status, started_at, triggered_by, created_at)
                    VALUES (:batch_id, :type, 'running', NOW(), :triggered_by, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'batch_id' => $this->currentBatchId,
                'type' => $type,
                'triggered_by' => $triggeredBy
            ]);
        } catch (\Exception $e) {
            error_log("Error en startBatch: " . $e->getMessage());
        }
    }

    /**
     * Registra métricas de rendimiento de una fase específica
     */
    public function recordMetric(string $phase, string $name, $value, string $unit = 'count'): void {
        try {
            $sql = "INSERT INTO sync_metrics (batch_id, phase, metric_name, metric_value, metric_unit, recorded_at)
                    VALUES (:batch_id, :phase, :name, :value, :unit, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'batch_id' => $this->currentBatchId,
                'phase' => $phase,
                'name' => $name,
                'value' => (string)$value,
                'unit' => $unit
            ]);
        } catch (\Exception $e) {
            error_log("Error en recordMetric: " . $e->getMessage());
        }
    }

    /**
     * Registra un resumen de error agrupado por tipo y entidad
     */
    public function recordErrorSummary(string $entity, string $errorType, string $sampleData = ''): void {
        try {
            $sql = "INSERT INTO sync_error_summary (sync_id, entity, error_type, error_count, first_occurrence, last_occurrence, sample_data)
                    VALUES (:sync_id, :entity, :error_type, 1, NOW(), NOW(), :sample)
                    ON DUPLICATE KEY UPDATE
                    error_count = error_count + 1,
                    last_occurrence = NOW(),
                    sample_data = VALUES(sample_data)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'sync_id' => $this->currentBatchId,
                'entity' => $entity,
                'error_type' => $errorType,
                'sample' => substr($sampleData, 0, 1024)
            ]);
        } catch (\Exception $e) {
            error_log("Error en recordErrorSummary: " . $e->getMessage());
        }
    }

    /**
     * Finaliza el batch con estadísticas completas
     */
    public function finishBatch(string $status, array $stats = []): void {
        try {
            $sql = "UPDATE sync_batches 
                    SET status = :status,
                        completed_at = NOW(),
                        total_items = :total,
                        processed_items = :processed,
                        error_items = :errors,
                        skipped_items = :skipped,
                        stats_json = :stats,
                        updated_at = NOW()
                    WHERE batch_id = :batch_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'status' => $status,
                'total' => $stats['total_from_moodle'] ?? ($stats['processed'] ?? 0),
                'processed' => $stats['processed'] ?? 0,
                'errors' => $stats['errors'] ?? 0,
                'skipped' => $stats['skipped'] ?? 0,
                'stats' => json_encode($stats),
                'batch_id' => $this->currentBatchId
            ]);
        } catch (\Exception $e) {
            error_log("Error en finishBatch: " . $e->getMessage());
        }
    }
}
