<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\CSRFHelper;
use App\Helpers\InputSanitizerHelper;
use App\Helpers\ValidationHelper;
use App\Helpers\RateLimitHelper;
use App\Helpers\PasswordValidator;
use App\Helpers\CaptchaHelper;
use App\Models\Usuario\UsuarioModel;
use Config\Env;

class AuthController extends BaseController {
    
    private $authService;

    public function __construct(\App\Services\AuthService $authService) {
        parent::__construct();
        $this->authService = $authService;
    }
    
    /**
     * Muestra el formulario de inicio de sesión.
     */
    public function showLogin() {
        // Si ya está logueado, redirigir al escritorio
        if ($this->session->isLoggedIn()) {
            $this->redirect('escritorio');
        }

        $this->render('Auth/login', [
            'pageTitle' => 'Iniciar Sesión',
            'extraCSS' => 'Auth',
            'extraJS' => 'Auth'
        ]);
    }

    /**
     * Procesa la solicitud de inicio de sesión.
     */
    public function login() {
        // 1. Validaciones HTTP (Controllers Layer)
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!CSRFHelper::validateToken($csrfToken)) {
            $this->logSecurity("CSRF Inválido en Login.");
            $this->flash('error', 'Sesión de formulario inválida. Intente de nuevo.');
            $this->redirect('login');
        }

        $captcha = $_POST['captcha'] ?? '';
        if (!CaptchaHelper::validate($captcha)) {
            $this->flash('error', 'El código de seguridad es incorrecto.');
            $this->redirect('login');
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $rateCheck = RateLimitHelper::check($ip);
        if ($rateCheck['isBlocked']) {
            $this->flash('error', 'Demasiados intentos. Bloqueado temporalmente (' . ceil($rateCheck['remainingTime']/60) . ' min).');
            $this->redirect('login');
        }

        $val = ValidationHelper::make($_POST);
        $val->rule('username', 'required')->rule('password', 'required');
        
        if ($val->fails()) {
            $this->flash('error', $val->firstError());
            $this->redirect('login');
        }

        // 2. Delegación a Servicio (Service Layer)
        try {
            $username = InputSanitizerHelper::sanitizeString($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $authService = $this->authService;
            $user = $authService->authenticate($username, $password);
            $authService->loginUser($user);

            $this->logSecurity("Login exitoso usuario: $username");
            $this->flash('success', 'Bienvenido, ' . $user['nombre']);
            $this->redirect('escritorio');

        } catch (\Exception $e) {
            // Manejo de errores de negocio
            RateLimitHelper::recordAttempt($ip);
            $this->logSecurity("Login fallido: $username. " . $e->getMessage());
            $this->flash('error', 'Usuario o contraseña incorrectos.'); // Mensaje genérico al usuario
            $this->redirect('login');
        }
    }

    /**
     * Auxiliar para centralizar el registro de sesión tras éxito.
     */
    private function logInUser(array $user) {
        $ip = $_SERVER['REMOTE_ADDR'];
        RateLimitHelper::clear($ip);

        $this->session->set('user_id', $user['id']);
        $this->session->set('user_data', [
            'nombre' => $user['nombre'],
            'apellido' => $user['apellido'],
            'email' => $user['email']
        ]);

        // Cargar premisos básicos según banderas
        $permissions = ['ver_escritorio'];
        if ($user['es_admin'] == 1) $permissions[] = 'ver_usuario';
        if ($user['es_docente'] == 1) $permissions[] = 'ver_cursos';
        
        $this->session->set('user_permissions', $permissions);
        
        $this->flash('success', 'Bienvenido, ' . $user['nombre']);
        $this->redirect('escritorio');
    }

    /**
     * Cierra la sesión del usuario.
     */
    public function logout() {
        $userId = $this->getUserId();
        if ($userId) {
            $this->logSecurity("Usuario $userId cerró sesión correctamente.");
        }
        $this->session->destroy();
        $this->redirect('login');
    }

    /**
     * Genera la imagen del captcha.
     */
    public function captchaImage() {
        // Dimensiones ajustadas al diseño
        CaptchaHelper::render(100, 38, 4);
    }
}
