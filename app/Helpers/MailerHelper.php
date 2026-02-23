<?php
declare(strict_types=1);

namespace App\Helpers;

use Config\Env;

class MailerHelper {
    
    /**
     * Envía un correo electrónico simple (HTML).
     */
    public static function send(string $to, string $subject, string $htmlBody): bool {
        $fromEmail = Env::get('MAIL_FROM_ADDRESS', 'noreply@eduma.local');
        $fromName = Env::get('MAIL_FROM_NAME', 'EDUMA Platform');
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$fromName} <{$fromEmail}>" . "\r\n";
        $headers .= "Reply-To: {$fromEmail}" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // En entorno local (Windows/XAMPP) mail() requiere configuración SMTP en php.ini
        // Si falla, retornamos false o logueamos.
        // Para desarrollo, podemos simular el envío logueando el contenido.
        
        if (Env::get('APP_ENV') === 'local') {
            // Log para debug local
            $logDir = __DIR__ . '/../../writable/logs/mail';
            if (!is_dir($logDir)) mkdir($logDir, 0777, true);
            
            $filename = $logDir . '/mail_' . date('Y-m-d_H-i-s') . '_' . md5($to) . '.html';
            $content = "To: $to\nSubject: $subject\n\n$htmlBody";
            file_put_contents($filename, $content);
            return true; // Simulamos éxito
        }

        return mail($to, $subject, $htmlBody, $headers);
    }
}
