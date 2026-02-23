<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Estandarización de respuestas de la API
 */
class ApiResponse
{
    /**
     * Envía una respuesta de éxito.
     */
    public static function success(array $data = [], string $message = 'Operación exitosa', int $code = 200): void
    {
        self::send([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'metadata' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION
            ]
        ], $code);
    }

    /**
     * Envía una respuesta de error.
     */
    public static function error(string $message = 'Ha ocurrido un error', int $code = 400, array $errors = []): void
    {
        self::send([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
            'metadata' => [
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ], $code);
    }

    /**
     * Envía la respuesta JSON y termina la ejecución.
     */
    private static function send(array $payload, int $code): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
