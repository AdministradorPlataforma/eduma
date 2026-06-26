<?php
declare(strict_types=1);

namespace App\Models;

class QueueJobModel extends BaseModel {
    protected string $table = 'queue_jobs';
    protected string $primaryKey = 'id';
    
    public function createJob(string $handler, string $queue = 'default', int $priority = 5): int {
        $sql = "INSERT INTO {$this->table} (handler, queue, priority, status, created_at) 
                VALUES (:handler, :queue, :priority, 'pending', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'handler' => $handler,
            'queue' => $queue,
            'priority' => $priority
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Obtiene el siguiente job pendiente y lo bloquea para el worker actual.
     */
    public function getNextPending(int $maxAttempts = 3) {
        $workerId = 'W' . getmypid();
        
        // 1. Intentar reservar un job atómicamente
        $sql = "UPDATE {$this->table} 
                SET status = 'running', 
                    reserved_at = NOW(), 
                    worker_id = :worker,
                    attempts = attempts + 1
                WHERE status IN ('pending', 'failed') 
                AND attempts < :max_attempts 
                AND (reserved_at IS NULL OR reserved_at < DATE_SUB(NOW(), INTERVAL 1 HOUR))
                ORDER BY priority DESC, created_at ASC 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'worker' => $workerId,
            'max_attempts' => $maxAttempts
        ]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        // 2. Recuperar el job que acabamos de reservar
        $sql = "SELECT * FROM {$this->table} WHERE worker_id = :worker AND status = 'running' LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['worker' => $workerId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function markAsRunning(int $id) {
        $workerId = 'W' . getmypid();
        $sql = "UPDATE {$this->table} 
                SET status = 'running', 
                    reserved_at = NOW(), 
                    worker_id = :worker,
                    attempts = attempts + 1
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'worker' => $workerId
        ]);
    }

    public function markAsCompleted(int $id) {
        $sql = "UPDATE {$this->table} SET status = 'completed', processed_at = NOW(), reserved_at = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    public function markAsFailed(int $id, string $error) {
        $sql = "UPDATE {$this->table} SET status = 'failed', last_error = :error, reserved_at = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'error' => $error]);
    }

    public function getRecentJobs(int $limit = 10): array {
        $sql = "SELECT id, handler, queue, status, attempts, created_at, processed_at, last_error FROM {$this->table} ORDER BY id DESC LIMIT " . (int)$limit;
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
