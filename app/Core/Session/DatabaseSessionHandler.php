<?php
declare(strict_types=1);

namespace App\Core\Session;

use PDO;
use SessionHandlerInterface;

/**
 * Manejador de Sesiones en Base de Datos
 * 
 * Permite que las sesiones sean persistentes en DB, lo que habilita
 * el monitoreo en tiempo real, control de dispositivos y mayor seguridad.
 */
class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function open($path, $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        $stmt = $this->db->prepare("SELECT data FROM user_sessions WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['data'] : '';
    }

    public function write($id, $data): bool
    {
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $now = time();

        // Usamos REPLICA/INSERT UPDATE para mantener el registro fresco
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (id, user_id, data, ip_address, user_agent, last_activity, created_at)
            VALUES (:id, :user_id, :data, :ip, :ua, :last, NOW())
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                data = VALUES(data),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                last_activity = VALUES(last_activity)
        ");

        return $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'data' => $data,
            'ip' => $ip,
            'ua' => $ua,
            'last' => $now
        ]);
    }

    public function destroy($id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function gc($max_lifetime): bool
    {
        $past = time() - $max_lifetime;
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE last_activity < ?");
        $stmt->execute([$past]);
        
        return true;
    }
}
