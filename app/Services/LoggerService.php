<?php
declare(strict_types=1);

namespace App\Services;

use Config\Database;
use PDO;
use Exception;

class LoggerService {
    private static $logDir = __DIR__ . '/../../logs/';
    private static $db = null;

    /**
     * Escribe un log de información en archivo.
     */
    public static function info(string $message, array $context = []) {
        self::writeLog('INFO', $message, $context);
    }

    /**
     * Escribe un log de error en archivo.
     */
    public static function error(string $message, array $context = []) {
        self::writeLog('ERROR', $message, $context);
    }

    /**
     * Escribe un log de advertencia en archivo.
     */
    public static function warning(string $message, array $context = []) {
        self::writeLog('WARNING', $message, $context);
    }

    /**
     * Escribe un log de debug en archivo.
     */
    public static function debug(string $message, array $context = []) {
        self::writeLog('DEBUG', $message, $context);
    }

    /**
     * Registra una acción de auditoría en la base de datos.
     * 
     * @param int|null $userId ID del usuario que realiza la acción (null si es sistema o login fallido)
     * @param string $action Código de la acción (ej: LOGIN_SUCCESS, USER_CREATE)
     * @param string|null $resource Recurso afectado (ej: "Usuario:5")
     * @param array|string $details Detalles adicionales (array será convertido a JSON)
     */
    public static function audit(?int $userId, string $action, ?string $resource = null, $details = null) {
        try {
            $db = self::getDbConnection();

            $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, resource, details, ip_address, user_agent, created_at) VALUES (:uid, :act, :res, :det, :ip, :ua, NOW())");
            
            $detailsStr = is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details;
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';

            $stmt->bindValue(':uid', $userId, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':act', $action);
            $stmt->bindValue(':res', $resource);
            $stmt->bindValue(':det', $detailsStr);
            $stmt->bindValue(':ip', $ip);
            $stmt->bindValue(':ua', substr($ua, 0, 255)); // Truncar si es muy largo

            $stmt->execute();
        } catch (Exception $e) {
            // Si falla la auditoría DB (por ejemplo, tabla no existe o conexión perdida), log in archivo como fallback
            self::error("FALLO AUDITORIA DB: " . $e->getMessage(), ['original_action' => $action]);
        }
    }

    private static function writeLog(string $level, string $message, array $context) {
        $date = date('Y-m-d H:i:s');
        $logFile = self::$logDir . 'app-' . date('Y-m-d') . '.log';
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logLine = "[$date] [$level] $message $contextStr" . PHP_EOL;
        
        // Asegurar que el directorio logs existe
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }

        file_put_contents($logFile, $logLine, FILE_APPEND);
    }

    private static function getDbConnection(): PDO {
        if (self::$db === null) {
            $config = new Database();
            self::$db = $config->getConnection();
        }
        return self::$db;
    }
}
