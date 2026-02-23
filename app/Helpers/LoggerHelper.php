<?php
declare(strict_types=1);

namespace App\Helpers;

use Config\Env;

class LoggerHelper
{
    /**
     * Registra un error estructurado en el log del sistema.
     * 
     * @param \Throwable $exception La excepción capturada.
     * @param array $context Contexto adicional para debugging.
     */
    public static function error(\Throwable $exception, array $context = []): void
    {
        $logPath = __DIR__ . '/../../logs/app_error.json';
        
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'ERROR',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'trace' => Env::get('APP_DEBUG', true) ? $exception->getTraceAsString() : 'Hidden in production',
            'request' => [
                'uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ],
            'context' => $context
        ];

        // Escribir en un archivo dedicado en formato JSON para herramientas de análisis
        file_put_contents(
            $logPath, 
            json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, 
            FILE_APPEND
        );

        // También registrar en el error_log estándar de PHP para compatibilidad con WAMP/Apache
        error_log("[EDUMA_EXCEPTION] " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
    }

    /**
     * Registra eventos de seguridad.
     */
    public static function security(string $message, array $context = []): void
    {
        self::log('SECURITY', $message, $context);
    }

    /**
     * Registra eventos de información.
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        $logPath = __DIR__ . '/../../logs/app_events.json';
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'context' => $context
        ];

        file_put_contents(
            $logPath, 
            json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, 
            FILE_APPEND
        );
    }
}
