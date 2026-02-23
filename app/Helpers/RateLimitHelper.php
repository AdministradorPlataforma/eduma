<?php
declare(strict_types=1);

namespace App\Helpers;

class RateLimitHelper {
    /**
     * Verifica si una IP ha excedido el límite de intentos usando la BD.
     */
    public static function check($key, $max_attempts = 5, $lockout_time = 900) {
        $db = \App\Core\Container::getInstance()->get('db');
        
        $stmt = $db->prepare("SELECT locked_until FROM rate_limits WHERE `key` = :key");
        $stmt->execute([':key' => $key]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$record) {
            return ['isBlocked' => false];
        }

        $now = time();
        $lockedUntil = $record['locked_until'] ? strtotime($record['locked_until']) : 0;

        if ($lockedUntil > $now) {
            return [
                'isBlocked' => true,
                'remainingTime' => $lockedUntil - $now
            ];
        }

        return ['isBlocked' => false];
    }

    /**
     * Registra un intento fallido en la BD.
     */
    public static function recordAttempt($key) {
        $max = defined('RATE_LIMIT_MAX_ATTEMPTS') ? RATE_LIMIT_MAX_ATTEMPTS : 5;
        $lockout = defined('RATE_LIMIT_LOCKOUT_TIME') ? RATE_LIMIT_LOCKOUT_TIME : 900;

        $db = \App\Core\Container::getInstance()->get('db');
        
        // 1. Obtener estado actual
        $stmt = $db->prepare("SELECT attempts, last_attempt FROM rate_limits WHERE `key` = :key");
        $stmt->execute([':key' => $key]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $now = date('Y-m-d H:i:s');

        if (!$record) {
            // Primer intento
            $stmt = $db->prepare("INSERT INTO rate_limits (`key`, attempts, last_attempt) VALUES (:key, 1, :now)");
            try {
                $stmt->execute([':key' => $key, ':now' => $now]);
            } catch (\PDOException $e) {
                // Posible race condition, ignorar silentiosamente
            }
            return;
        }

        $newAttempts = $record['attempts'] + 1;
        $lockedUntil = null;
        
        // Si pasó mucho tiempo desde el último intento, resetear contador
        $lastAttemptTime = strtotime($record['last_attempt']);
        if ((time() - $lastAttemptTime) > $lockout) {
             $newAttempts = 1;
        }

        // Verificar si debemos bloquear
        if ($newAttempts >= $max) {
             $lockedUntil = date('Y-m-d H:i:s', time() + $lockout);
        }

        $stmt = $db->prepare("UPDATE rate_limits SET attempts = :att, last_attempt = :now, locked_until = :lck WHERE `key` = :key");
        $stmt->execute([
            ':att' => $newAttempts,
            ':now' => $now,
            ':lck' => $lockedUntil,
            ':key' => $key
        ]);
    }

    /**
     * Limpia los intentos (ej: tras login exitoso).
     */
    public static function clear($key) {
        $db = \App\Core\Container::getInstance()->get('db');
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE `key` = :key");
        $stmt->execute([':key' => $key]);
    }
}
