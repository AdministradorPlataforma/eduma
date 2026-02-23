<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\PasswordResetService;
use App\Helpers\SessionHelper;
use App\Helpers\CSRFHelper;

class PasswordResetController extends BaseController {
    
    // Inyectaremos el servicio manualmente desde el contenedor por ahora
    // o usaremos constructor dependency injection si el router lo soporta
    
    // Router simple usa new Controller(), no DI Container automágico completo aún.
    // Usaremos un factory o getter interno en BaseController o manual aquí.
    
    private function getService(): PasswordResetService {
        // Factory manual rápida
        $db = \App\Core\Container::getInstance()->get('db');
        $userModel = new \App\Models\Usuario\UsuarioModel($db);
        return new PasswordResetService($userModel, $db);
    }

    /**
     * Muestra el formulario de solicitud de enlace (Olvide mi contraseña)
     */
    public function showLinkRequestForm() {
        $this->render('Auth/passwords/email', [
            'pageTitle' => 'Recuperar Contraseña',
            'csrf_token' => CSRFHelper::getToken()
        ]);
    }

    /**
     * Procesa el envío del correo de recuperación
     */
    public function sendResetLinkEmail() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        
        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->session->setFlash('error', 'Sesión expirada. Intente nuevamente.');
            return $this->redirect('password/forgot');
        }

        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        
        try {
            $service = $this->getService();
            // Siempre retornamos éxito por seguridad (Enumeration prevention)
            // A menos que sea error técnico grave
            $service->sendResetLink($email);
            
            $this->session->setFlash('success', 'Si el correo existe en nuestro sistema, recibirás un enlace de recuperación en breve.');
        } catch (\Exception $e) {
            $this->session->setFlash('error', 'Ocurrió un error al procesar tu solicitud. Intenta más tarde.');
        }

        return $this->redirect('password/forgot');
    }

    /**
     * Muestra el formulario de restablecimiento (Nueva contraseña)
     */
    public function showResetForm($token) {
        $email = $_GET['email'] ?? '';
        
        $this->render('Auth/passwords/reset', [
            'pageTitle' => 'Crear Nueva Contraseña',
            'token' => $token,
            'email' => $email,
            'csrf_token' => CSRFHelper::getToken()
        ]);
    }

    /**
     * Procesa el cambio de contraseña
     */
    public function reset() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->session->setFlash('error', 'Sesión expirada.');
            return $this->redirect('login');
        }

        $token = $_POST['token'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';

        try {
            $service = $this->getService();
            $result = $service->reset($email, $password, $passwordConfirmation, $token);

            if ($result['status'] === 'success') {
                $this->session->setFlash('success', $result['message']);
                return $this->redirect('login');
            } else {
                $this->session->setFlash('error', $result['message']);
                // Redirigir de vuelta al form con token/email para reintentar
                return $this->redirect("password/reset/$token?email=" . urlencode($email));
            }

        } catch (\Exception $e) {
            $this->session->setFlash('error', 'Error del sistema: ' . $e->getMessage());
            return $this->redirect('login');
        }
    }
}
