<?php
declare(strict_types=1);

// ============================================================================
// EDUMA - Single Entry Point
// ============================================================================

// 1. Cargar Autoload (Composer)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
// Fallback manual Autoloader
require_once __DIR__ . '/../app/Core/Autoloader.php';

// 2. Cargar variables de entorno primero
\Config\Env::load();

// 2.5 Configuración de Errores (según .env)
if (\Config\Env::get('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
$logPath = __DIR__ . '/../logs/php_error.log';
ini_set('error_log', $logPath);

// Manejador global de excepciones para un cierre robusto
set_exception_handler(function ($e) {
    error_log("[FATAL_EXCEPTION] " . $e->getMessage());
    
    if (\Config\Env::get('APP_DEBUG', false)) {
        echo "<div style='background: #fee; border: 1px solid #f99; padding: 20px; font-family: sans-serif; border-radius: 8px; margin: 20px;'>";
        echo "<h2 style='color: #c00; margin-top: 0;'>Excepción No Capturada</h2>";
        echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Archivo:</strong> " . $e->getFile() . " en línea " . $e->getLine() . "</p>";
        echo "<pre style='background: #fff; padding: 10px; border: 1px solid #ddd; overflow: auto;'>" . $e->getTraceAsString() . "</pre>";
        echo "</div>";
    } else {
        http_response_code(500);
        include __DIR__ . '/../app/Views/errors/500.php';
    }
    exit;
});

// 3. Cargar Constantes base (que usan Env)
require_once __DIR__ . '/../config/Constants.php';

// Asegurar que el archivo existe y es escribible
if (!file_exists($logPath)) {
    touch($logPath);
}
error_log("[EDUMA_INIT] Sistema de logs iniciado correctamente.");

// 3. Configuración de Sesión y Seguridad (Headers, Ini sets)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    
    // Conectar Handler de Base de Datos
    $dbForSession = (new \Config\Database())->getConnection();
    $sessionHandler = new \App\Core\Session\DatabaseSessionHandler($dbForSession);
    session_set_save_handler($sessionHandler, true);
    
    session_start();
}

// ============================================================================
// 3.5 Blindaje de Seguridad (Headers & CSP)
// ============================================================================
$csp = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline'", // Permite los scripts inline actuales del sistema
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com", 
    "font-src 'self' https://fonts.gstatic.com",
    "img-src 'self' data: https://ui-avatars.com",
    "connect-src 'self'",
    "frame-ancestors 'none'",
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'"
];

header("Content-Security-Policy: " . implode("; ", $csp));
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()");

// HSTS (Solo si se detecta HTTPS o está configurado en el env)
if (\Config\Env::get('COOKIE_SECURE', false)) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

header("Content-Type: text/html; charset=UTF-8");

// 4. Inicializar Contenedor de Dependencias
$container = \App\Core\Container::getInstance();

// Registrar Conexión de Base de Datos
$container->bind('db', function() {
    return (new \Config\Database())->getConnection();
}, true);

// Binding para la clase PDO para facilitar Autowiring
$container->bind(PDO::class, function($c) {
    return $c->get('db');
});

// Registrar Mapper de Modelos (Ejemplo para Usuario)
$container->bind(\App\Models\Usuario\UsuarioModel::class, function($c) {
    return new \App\Models\Usuario\UsuarioModel($c->get('db'));
});

// 5. Inicializar Sistema de Eventos
require_once __DIR__ . '/../config/events.php';

// 6. Instanciar Router
$router = new \App\Core\Router();

// 6. Cargar Rutas
// El archivo Routes/web.php ahora espera una variable $router
require_once __DIR__ . '/../routes/web.php';

// 7. Despachar Solicitud
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$router->dispatch($uri, $method);
