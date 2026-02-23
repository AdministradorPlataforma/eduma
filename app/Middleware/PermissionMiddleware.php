<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\SessionHelper;

class PermissionMiddleware {
    /**
     * Verifica si el usuario tiene el permiso requerido.
     *
     * @param string|null $permission Slug del permiso (ej: 'ver_usuario')
     * @return void
     */
    /**
     * Verifica si el usuario tiene los permisos requeridos.
     * Soporta múltiples permisos:
     * - 'slug1|slug2' -> Requiere uno de los dos (OR)
     * - 'slug1&slug2' -> Requiere ambos (AND)
     *
     * @param string|null $permission String de permisos
     * @return void
     */
    public function handle(?string $permission = null): void {
        $session = new SessionHelper();

        // 1. Verificar autenticación primero
        if (!$session->isLoggedIn()) {
            $this->unauthorized();
        }

        // ============================================================
        // RBAC REAL: Sin bypass hardcodeado (2026-02-23)
        // ============================================================
        // El Super Admin obtiene TODOS los permisos vía rol_permisos 
        // (asignados por RbacSetupService), NO por bypass.
        // Esto permite auditar cada acceso y crear admins con scope limitado.
        // ============================================================

        // 3. Si no se define el permiso en la ruta y no es admin, bloqueamos por seguridad
        if (empty($permission)) {
            $this->forbidden("Ruta protegida sin permiso asignado.");
        }

        // 4. Lógica de permisos múltiples (AND / OR)
        $hasPermission = false;
        
        if (strpos($permission, '|') !== false) {
            // Lógica OR: con que tenga uno basta
            $perms = explode('|', $permission);
            foreach ($perms as $p) {
                if ($session->hasPermission(trim($p))) {
                    $hasPermission = true;
                    break;
                }
            }
        } elseif (strpos($permission, '&') !== false) {
            // Lógica AND: debe tener todos
            $perms = explode('&', $permission);
            $hasPermission = true;
            foreach ($perms as $p) {
                if (!$session->hasPermission(trim($p))) {
                    $hasPermission = false;
                    break;
                }
            }
        } else {
            // Permiso único
            $hasPermission = $session->hasPermission($permission);
        }

        if (!$hasPermission) {
            error_log("[RBAC] Acceso denegado. Usuario: " . $session->getUserId() . " intentó acceder a: $permission");
            $this->forbidden("No tienes permisos suficientes para realizar esta acción.");
        }
    }

    private function unauthorized() {
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Sesión expirada o no iniciada.']);
            exit;
        }
        
        $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
        header('Location: ' . $baseUrl . 'login');
        exit;
    }

    private function forbidden(string $message = "Acceso Denegado") {
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => $message]);
            exit;
        }
        
        $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
        header('Location: ' . $baseUrl . 'errors/403');
        exit;
    }

    private function isAjax(): bool {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }
}
