<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\SessionHelper;

class AuthMiddleware {
    /**
     * Ejecuta la verificación de autenticación.
     * Si el usuario no está logueado, redirige al login.
     *
     * @return void
     */
    public function handle($param = null): void {
        // Usamos la instancia estática o new según convenga. 
        // SessionHelper tiene métodos estáticos wrappers como isLoggedIn() pero
        // internamente BaseController usaba $this->session->isLoggedIn().
        // SessionHelper::isLoggedIn() no es estático en el archivo que vi antes, 
        // pero tiene métodos estáticos mixtos. Revisemos Step 15.
        
        // En Step 15: isLoggedIn() NO es estático.
        // public function isLoggedIn(): bool { return self::has('user_id'); }
        // Pero `has` ES estático.
        // Así que podemos usar `SessionHelper::has('user_id')` o instanciar.
        
        $session = new SessionHelper();
        if (!$session->isLoggedIn()) {
            // Redirigir al login
            // Asumimos que BASE_URL está definida en Config/constants.php o similar
            // Si no, usamos ruta relativa.
            $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
            header('Location: ' . $baseUrl . 'login');
            exit;
        }
    }
}
