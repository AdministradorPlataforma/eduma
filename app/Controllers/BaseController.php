<?php
/**
 * /App/Controllers/BaseController.php
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\SessionHelper;
use Exception;

class BaseController
{
    /**
     * @var SessionHelper
     */
    protected $session;

    public function __construct()
    {
        $this->session = new SessionHelper();
    }

    /**
     * Verifica si el usuario tiene un permiso específico.
     * @param string $permission
     * @return void
     */
    protected function requirePermission(string $permission)
    {
        $this->requireLogin();

        // Bypass para Súper Administradores
        if ($this->session->isAdmin()) {
            return;
        }
        
        if (!$this->session->hasPermission($permission)) {
            $this->logSecurity("Acceso denegado: falta permiso [$permission]");
            
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Acceso denegado: falta permiso requerido (' . $permission . ')'
                ], 403);
            }

            $this->flash('error', 'No tiene permiso para acceder a esta sección.');
            $this->redirect('errors/403');
        }
    }

    /**
     * Verifica si hay una sesión iniciada.
     * @return void
     */
    protected function requireLogin(): void
    {
        if (!$this->session->isLoggedIn()) {
            if ($this->isAjax()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => 'Sesión expirada'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $this->flash('error', 'Debe iniciar sesión.');
            $this->redirect('login');
        }
    }

    protected function requireRole(int $roleId)
    {
        $this->requireRoles([$roleId]);
    }

    /**
     * Verifica si el usuario pertenece a uno de los roles permitidos.
     * @param int[] $allowedRoles
     * @return void
     */
    protected function requireRoles(array $allowedRoles)
    {
        $this->requireLogin();

        if (!$this->session->hasRole($allowedRoles)) {
            $this->logSecurity("Acceso denegado: rol no permitido");
            $this->flash('error', 'No tiene permisos para acceder.');
            $this->redirect('errors/403');
        }
    }

    /**
     * Devuelve los datos del usuario actual en sesión.
     * @return array<string, mixed>|null
     */
    protected function getUser()
    {
        return $this->session->getUserData();
    }
    
    /**
     * Registra un evento de seguridad en formato estructurado (JSON).
     * @param string $message Mensaje descriptivo
     * @param array $context Contexto adicional (opcional)
     * @return void
     */
    protected function logSecurity(string $message, array $context = [])
    {
        \App\Helpers\LoggerHelper::security($message, $context);
    }

    /**
     * Obtiene el ID del usuario actual o null.
     * @return int|null
     */
    protected function getUserId(): ?int
    {
        return $this->session->getUserId();
    }

    /**
     * Obtiene el rol del usuario actual o null.
     * @return int|null
     */
    protected function getUserRole(): ?int
    {
        return $this->session->getUserRole();
    }

    /**
     * Establece un mensaje flash.
     * @param string $type
     * @param string $message
     * @return void
     */
    protected function flash(string $type, string $message): void
    {
        \App\Helpers\FlashHelper::set($type, $message);
    }

    /**
     * Obtiene mensajes flash.
     * @param string|null $type
     * @return mixed
     */
    protected function getFlashes(?string $type = null): mixed
    {
        return $this->session->getFlashMessage($type);
    }

    /**
     * Verifica si existen mensajes flash.
     * @param string|null $type
     * @return bool
     */
    protected function hasFlashes(?string $type = null): bool
    {
        return $this->session->hasFlashMessage($type);
    }

    /**
     * Renderiza una vista, inyectando variables.
     * @param string $viewPath
     * @param array<string, mixed> $data
     * @return void
     * @throws Exception
     */
    protected function render(string $viewPath, array $data = []): void
    {
        $data['userData'] = $this->getUser();
        $base_url = defined('BASE_URL') ? BASE_URL : '/';
        $data['base_url'] = $base_url;

        // --- Global Data Injection (Notifications) ---
        if ($this->session->isLoggedIn()) {
            $userId = $this->getUserId();
            $cacheKey = "user_notifs_{$userId}";
            
            $cachedData = \App\Helpers\CacheHelper::get($cacheKey);
            
            if ($cachedData !== null) {
                $data['globalUnreadNotifs'] = $cachedData['notifs'] ?? [];
                $data['globalUnreadCount'] = $cachedData['count'] ?? 0;
            } else {
                $notifService = \App\Core\Container::getInstance()->get(\App\Services\NotificationService::class);
                $data['globalUnreadNotifs'] = $notifService->getUnread($userId, 5);
                $data['globalUnreadCount'] = $notifService->countUnread($userId);
                
                \App\Helpers\CacheHelper::set($cacheKey, [
                    'notifs' => $data['globalUnreadNotifs'],
                    'count' => $data['globalUnreadCount']
                ], 300); // 5 minutos de caché
            }
        } else {
            $data['globalUnreadNotifs'] = [];
            $data['globalUnreadCount'] = 0;
        }

        extract($data, EXTR_SKIP);

        $viewPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $viewPath);
        $pathParts = explode(DIRECTORY_SEPARATOR, $viewPath);

        // Try to load from a module first
        if (count($pathParts) > 1) {
            $moduleName = $pathParts[0];
            $moduleViewPath = implode(DIRECTORY_SEPARATOR, array_slice($pathParts, 1));
            // Actualización para estructura con Mayúsculas
            $moduleViewFile = __DIR__ . '/../../Modules/' . $moduleName . '/Views/' . $moduleViewPath . '.php';

            if (file_exists($moduleViewFile)) {
                require_once $moduleViewFile;
                return;
            }
        }

        // Fallback to default app views
        $appViewFile = __DIR__ . '/../Views/' . $viewPath . '.php';

        if (file_exists($appViewFile)) {
             require_once $appViewFile;
             return;
        }

        throw new Exception("Vista no encontrada [$viewPath]. Rutas intentadas en Modules y Views.");
    }

    /**
     * Envía una respuesta JSON y termina la ejecución (Usa ApiResponse para estandarización).
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        if (isset($data['status']) && $data['status'] === 'success') {
            \App\Helpers\ApiResponse::success($data['data'] ?? [], $data['message'] ?? 'OK', $statusCode);
        } elseif (isset($data['status']) && $data['status'] === 'error') {
            \App\Helpers\ApiResponse::error($data['message'] ?? 'Error', $statusCode, $data['extra'] ?? []);
        } else {
            // Fallback para datos crudos
            \App\Helpers\ApiResponse::success($data, 'OK', $statusCode);
        }
    }

    /**
     * Retorna respuesta JSON exitosa estandarizada.
     */
    protected function jsonSuccess(array $data = [], int $statusCode = 200, string $message = 'Operación exitosa'): void
    {
        \App\Helpers\ApiResponse::success($data, $message, $statusCode);
    }

    /**
     * Retorna respuesta JSON de error estandarizada.
     */
    protected function jsonError(string $message, int $statusCode = 400, array $extra = []): void
    {
        \App\Helpers\ApiResponse::error($message, $statusCode, $extra);
    }

    /**
     * Verifica si la solicitud es AJAX.
     * @return bool
     */
    protected function isAjax(): bool
    {
        // Detectar por X-Requested-With (fetch con header)
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        
        // Detectar por Accept header (fetch que espera JSON)
        if (isset($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }
        
        return false;
    }

    /**
     * Redirige a otra ruta del sistema.
     * @param string $page
     * @return void
     */
    protected function redirect(string $page): void
    {
        $baseUrl = rtrim(BASE_URL, '/');
        $location = $baseUrl . '/' . ltrim($page, '/');
        header("Location: $location");
        exit;
    }
}
