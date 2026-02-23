<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database\QueryBuilder;
use Config\Database;
use PDO;

class NotificationService {
    private $db;
    private $builder;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->builder = new \App\Core\Database\QueryBuilder($this->db, 'notifications');
    }

    /**
     * Envía una notificación a un usuario.
     * 
     * @param int $userId
     * @param string $title
     * @param string $message
     * @param string $type (info, success, warning, danger)
     * @return int Notification ID
     */
    public function send(int $userId, string $title, string $message, string $type = 'info'): int {
        return $this->builder->insert([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obtiene notificaciones no leídas de un usuario.
     */
    public function getUnread(int $userId, int $limit = 5): array {
        return $this->builder
            ->select(['*'])
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 0)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Cuenta las notificaciones no leídas.
     */
    public function countUnread(int $userId): int {
        return $this->builder
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 0)
            ->count();
    }

    /**
     * Marca una notificación como leída.
     */
    public function markAsRead(int $id, int $userId): bool {
        return $this->builder
            ->where('id', '=', $id)
            ->where('user_id', '=', $userId) // Seguridad: solo el dueño puede marcar
            ->update(['is_read' => 1]);
    }

    /**
     * Marca todas como leídas.
     */
    public function markAllAsRead(int $userId): bool {
        return $this->builder
            ->where('user_id', '=', $userId)
            ->where('is_read', '=', 0)
            ->update(['is_read' => 1]);
    }
}
