<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

class SessionManagerService extends BaseService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new \Config\Database())->getConnection();
    }

    /**
     * Obtiene todas las sesiones activas de un usuario.
     */
    public function getUserSessions(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, ip_address, user_agent, last_activity, created_at 
            FROM user_sessions 
            WHERE user_id = ? 
            ORDER BY last_activity DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene TODAS las sesiones activas del sistema (Para Admins).
     */
    public function getAllActiveSessions(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                s.id, s.ip_address, s.user_agent, s.last_activity, s.created_at,
                u.username, u.nombre, u.apellido, u.email, u.id as user_id
            FROM user_sessions s
            JOIN usuarios u ON s.user_id = u.id
            ORDER BY s.last_activity DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina una sesión específica (Admin bypass de user_id check).
     */
    public function forceKillSession(string $sessionId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE id = ?");
        return $stmt->execute([$sessionId]);
    }

    /**
     * Elimina una sesión específica.
     */
    public function killSession(string $sessionId, int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE id = ? AND user_id = ?");
        return $stmt->execute([$sessionId, $userId]);
    }

    /**
     * Elimina todas las sesiones de un usuario excepto la actual.
     */
    public function killOtherSessions(int $userId, string $currentSessionId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ? AND id != ?");
        return $stmt->execute([$userId, $currentSessionId]);
    }
}
