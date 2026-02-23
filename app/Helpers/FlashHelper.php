<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Helper para gestión de mensajes flash (sesiones temporales).
 * Permite mostrar notificaciones de éxito/error que desaparecen tras un refresh.
 */
class FlashHelper {
    
    private const SESSION_KEY = 'flash';

    /**
     * Inicia la sesión si no está iniciada (por seguridad).
     */
    private static function ensureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Establece un mensaje flash.
     * @param string $type Tipo de mensaje ('success', 'error', 'info', 'warning')
     * @param string $message Contenido del mensaje
     */
    public static function set(string $type, string $message): void {
        self::ensureSession();
        $_SESSION[self::SESSION_KEY][$type] = $message;
    }

    /**
     * Obtiene y elimina un mensaje flash.
     * @param string $type Tipo de mensaje a recuperar
     * @return string|null Mensaje o null si no existe
     */
    public static function get(string $type): ?string {
        self::ensureSession();
        if (isset($_SESSION[self::SESSION_KEY][$type])) {
            $message = $_SESSION[self::SESSION_KEY][$type];
            unset($_SESSION[self::SESSION_KEY][$type]);
            return $message;
        }
        return null;
    }

    /**
     * Verifica si existe un mensaje flash.
     * @param string $type Tipo de mensaje
     * @return bool
     */
    public static function has(string $type): bool {
        self::ensureSession();
        return isset($_SESSION[self::SESSION_KEY][$type]);
    }

    /**
     * Renderiza un bloque de alerta HTML estándar si existe el mensaje.
     * @param string $type Tipo ('success' o 'error')
     * @return string HTML de la alerta o string vacío
     */
    public static function alert(string $type): string {
        if ($type === 'all') {
            $output = '';
            $types = ['success', 'error', 'warning', 'info'];
            foreach ($types as $t) {
                if (self::has($t)) {
                    $output .= self::alert($t);
                }
            }
            return $output;
        }

        $msg = self::get($type);
        if (!$msg) return '';

        $cssClass = ($type === 'error') ? 'danger' : $type;
        $icon = ($type === 'success') ? '<i class="bi bi-check-circle-fill me-2"></i>' : ($type === 'error' ? '<i class="bi bi-exclamation-triangle-fill me-2"></i>' : '');
        
        // Uso de clases utilitarias definidas en css/main.css o auth.css
        return sprintf(
            '<div class="alert alert-%s alert-dismissible fade show shadow-sm border-0 alert-rounded animate-fade-in mb-3" role="alert">
                %s %s
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>',
            $cssClass,
            $icon,
            htmlspecialchars($msg)
        );
    }
}
