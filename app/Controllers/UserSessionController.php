<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SessionManagerService;
use App\Helpers\ApiResponse;

class UserSessionController extends BaseController
{
    private SessionManagerService $sessionManager;

    public function __construct(SessionManagerService $sessionManager)
    {
        parent::__construct();
        $this->sessionManager = $sessionManager;
    }

    /**
     * Lista las sesiones activas en el perfil del usuario.
     */
    public function index()
    {
        $this->requireLogin();
        $userId = $this->getUserId();
        
        $sessions = $this->sessionManager->getUserSessions($userId);
        $currentSid = session_id();

        return $this->render('perfil/sesiones', [
            'title' => 'Gestión de Sesiones y Dispositivos',
            'sessions' => $sessions,
            'current_sid' => $currentSid
        ]);
    }

    /**
     * Monitor global de sesiones (Admin).
     */
    public function admin()
    {
        $this->requirePermission('ver_usuario'); // Sincronizado con nomenclatura del sistema
        
        $sessions = $this->sessionManager->getAllActiveSessions();
        $currentSid = session_id();

        return $this->render('admin/sesiones', [
            'pageTitle' => 'Monitor de Sesiones',
            'sessions' => $sessions,
            'current_sid' => $currentSid,
            'extraCSS' => 'AdminSesiones',
            'extraJS' => 'AdminSesiones'
        ]);
    }

    /**
     * API para cerrar cualquier sesión (Admin).
     */
    public function forceRevoke()
    {
        $this->requirePermission('ver_usuario');
        $sessionId = $_POST['session_id'] ?? null;
        
        if (!$sessionId) {
            return ApiResponse::error('ID de sesión no proporcionado');
        }

        $success = $this->sessionManager->forceKillSession($sessionId);

        if ($success) {
            return ApiResponse::success([], 'Sesión terminada forzosamente');
        }

        return ApiResponse::error('No se pudo terminar la sesión');
    }

    /**
     * API para cerrar una sesión.
     */
    public function revoke()
    {
        $this->requireLogin();
        $sessionId = $_POST['session_id'] ?? null;
        
        if (!$sessionId) {
            return ApiResponse::error('ID de sesión no proporcionado');
        }

        $userId = $this->getUserId();
        $success = $this->sessionManager->killSession($sessionId, $userId);

        if ($success) {
            return ApiResponse::success([], 'Sesión cerrada correctamente');
        }

        return ApiResponse::error('No se pudo cerrar la sesión');
    }
}
