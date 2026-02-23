<?php
declare(strict_types=1);

namespace App\Models\System;

use App\Models\BaseModel;

class TaskLogModel extends BaseModel {
    protected $table = 'task_logs';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'task_name', 'status', 'output', 'started_at', 'finished_at', 'duration_ms'
    ];

    /**
     * Obtiene los logs recientes con paginación
     */
    public function getRecentLogs(int $limit = 50): array {
        return $this->builder
            ->orderBy('started_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtiene estadísticas de ejecución
     */
    public function getStats(): array {
        $total = $this->builder->count();
        $failed = $this->builder->where('status', '=', 'failure')->count();
        $running = $this->builder->where('status', '=', 'running')->count();
        
        return [
            'total' => $total,
            'failed' => $failed,
            'running' => $running,
            'success' => $total - $failed - $running
        ];
    }
}
