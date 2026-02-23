<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario\UsuarioModel;
use App\Helpers\MailerHelper;
use App\Helpers\PasswordValidator;
use PDO;

class PasswordResetService extends BaseService {
    
    private UsuarioModel $userModel;
    private PDO $db;

    public function __construct(UsuarioModel $userModel, PDO $db) {
        $this->userModel = $userModel;
        $this->db = $db;
    }

    /**
     * Envía el enlace de recuperación al usuario si existe.
     */
    public function sendResetLink(string $email): string {
        // 1. Buscar usuario
        $user = $this->userModel->findByEmail($email);
        
        if (!$user) {
            // Por seguridad, no decimos si el email existe o no, pero retornamos estado genérico
            return 'pending'; 
        }

        // 2. Generar Token
        $token = bin2hex(random_bytes(32));
        
        // 3. Guardar Token (Limpiamos anteriores del mismo email)
        $this->db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        
        $stmt = $this->db->prepare("INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$email, $token]);

        // 4. Enviar Email
        $resetLink = BASE_URL . "password/reset/$token?email=" . urlencode($email);
        
        // Template básico HTML
        $subject = "Restablecer Contraseña - EDUMA";
        $body = "
        <h2>Hola, {$user['nombre']}</h2>
        <p>Recibimos una solicitud para restablecer tu contraseña en la plataforma EDUMA.</p>
        <p>Haz clic en el siguiente enlace para crear una nueva contraseña:</p>
        <p><a href='$resetLink' style='padding: 10px 20px; background-color: #6366f1; color: white; text-decoration: none; border-radius: 5px;'>Restablecer Contraseña</a></p>
        <p>Si no solicitaste este cambio, puedes ignorar este correo.</p>
        <p><small>Este enlace expirará en 60 minutos.</small></p>
        ";

        MailerHelper::send($email, $subject, $body);

        return 'sent';
    }

    /**
     * Restablece la contraseña usando el token
     */
    public function reset(string $email, string $password, string $passwordConfirmation, string $token): array {
        // Valida inputs básicos
        if ($password !== $passwordConfirmation) {
            return ['status' => 'error', 'message' => 'Las contraseñas no coinciden.'];
        }

        $validation = PasswordValidator::validate($password);
        if (!$validation['isValid']) {
            return ['status' => 'error', 'message' => implode(' ', $validation['errors'])];
        }

        // Valida Token en BD
        $stmt = $this->db->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ?");
        $stmt->execute([$email, $token]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return ['status' => 'error', 'message' => 'El token es inválido o el email es incorrecto.'];
        }

        // Valida Expiración (1 hora)
        $createdAt = strtotime($record['created_at']);
        if (time() - $createdAt > 3600) {
            $this->db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            return ['status' => 'error', 'message' => 'El enlace ha expirado. Solicita uno nuevo.'];
        }

        // Todo OK: Actualizar Usuario
        $user = $this->userModel->findByEmail($email);
        if (!$user) {
             return ['status' => 'error', 'message' => 'Usuario no encontrado.'];
        }

        $newHash = PasswordValidator::hash($password);
        $this->userModel->update((int)$user['id'], ['password' => $newHash]);

        // Borrar el token usado
        $this->db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

        return ['status' => 'success', 'message' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.'];
    }
}
