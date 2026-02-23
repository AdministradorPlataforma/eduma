<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\InputSanitizerHelper;
use App\Helpers\CSRFHelper;

class UsuarioPerfilController extends BaseController {
    
    private \App\Models\Usuario\UsuarioModel $usuarioModel;

    public function __construct(\App\Models\Usuario\UsuarioModel $usuarioModel) {
        parent::__construct();
        $this->usuarioModel = $usuarioModel;
    }

    
    /**
     * Muestra el perfil del usuario autenticado.
     */
    public function index() {
        $this->requireLogin();

        $this->render('Perfil/index', [
            'pageTitle' => 'Mi Perfil',
            'user' => $this->getUser()
        ]);
    }

    /**
     * Procesa la actualización de datos del perfil.
     */
    public function actualizarPerfil() {
        $this->requireLogin();

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->flash('error', 'Error de sesión. Intente de nuevo.');
            $this->redirect('perfil');
        }

        $userId = $this->getUserId();
        $clean = \App\Helpers\InputSanitizerHelper::sanitizeArray($_POST);
        
        $nombre = $clean['nombre'] ?? '';
        $apellido = $clean['apellido'] ?? '';
        $email = $clean['email'] ?? '';
        $passActual = $_POST['password_actual'] ?? '';
        $passNueva = $_POST['password_nueva'] ?? '';
        $passConfirmar = $_POST['password_confirmar'] ?? '';

        if (empty($nombre) || empty($apellido) || empty($email)) {
            $this->flash('error', 'Nombre, apellido y email son obligatorios.');
            $this->redirect('perfil');
        }

        $userDB = $this->usuarioModel->findById($userId);

        // 1. Verificar si se desea cambiar la contraseña
        $actualizarPass = false;
        if (!empty($passNueva)) {
            // Validar contraseña actual
            if (empty($passActual)) {
                $this->flash('error', 'Debe ingresar su contraseña actual para realizar cambios de seguridad.');
                $this->redirect('perfil');
            }

            if (!\App\Helpers\PasswordValidator::verify($passActual, $userDB['password']) && $passActual !== $userDB['password']) {
                $this->flash('error', 'La contraseña actual es incorrecta.');
                $this->redirect('perfil');
            }

            if ($passNueva !== $passConfirmar) {
                $this->flash('error', 'Las nuevas contraseñas no coinciden.');
                $this->redirect('perfil');
            }

            // Validar fortaleza (Validator solicitado)
            $valResult = \App\Helpers\PasswordValidator::validate($passNueva);
            if (!$valResult['isValid']) {
                $this->flash('error', $valResult['errors'][0]);
                $this->redirect('perfil');
            }

            $actualizarPass = true;
        }

        // 2. Verificar email duplicado
        $existente = $this->usuarioModel->findByEmail($email);
        if ($existente && (int)$existente['id'] !== $userId) {
            $this->flash('error', 'El correo electrónico ya está en uso.');
            $this->redirect('perfil');
        }

        $updateData = [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email
        ];

        if ($actualizarPass) {
            $updateData['password'] = \App\Helpers\PasswordValidator::hash($passNueva);
        }

        try {
            if ($this->usuarioModel->update($userId, $updateData)) {
                // Actualizar datos en sesión
                $currentData = $this->getUser();
                $currentData['nombre'] = $nombre;
                $currentData['apellido'] = $apellido;
                $currentData['email'] = $email;
                $this->session->set('user_data', $currentData);

                $this->flash('success', 'Perfil y seguridad actualizados con éxito.');
            } else {
                $this->flash('error', 'No se pudieron guardar los cambios.');
            }
        } catch (\Exception $e) {
            $this->flash('error', 'Error crítico al actualizar: ' . $e->getMessage());
        }

        $this->redirect('perfil');
    }
}
