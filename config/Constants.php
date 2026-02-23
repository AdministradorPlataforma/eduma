<?php
// ====================
// Cargar variables de entorno (Usando la nueva estructura PSR-4)
// ====================
use Config\Env;

// Asegurarse de que el Autoloader ya ha cargado la clase antes de usarla
// El Entry Point (index.php) ya carga el vendor/autoload.php

// ====================
// Configuración general
// ====================
define('APP_NAME', Env::get('APP_NAME', 'EDUMA'));
define('APP_ENV', Env::get('APP_ENV', 'production'));
define('APP_DEBUG', Env::get('APP_DEBUG', false));

// Cambia a HTTPS y dominio real en producción
define('BASE_URL', Env::get('BASE_URL', 'http://192.168.1.173/eduma/'));
define('URLROOT', BASE_URL); // Alias for backward compatibility

// ====================
// Configuración de zona horaria
// ====================
date_default_timezone_set(Env::get('TIMEZONE', 'America/Asuncion'));

// ====================
// Configuración de sesión
// ====================
define('SESSION_TIMEOUT', Env::get('SESSION_TIMEOUT', 3600)); // 1 hora en segundos
define('SESSION_NAME', Env::get('SESSION_NAME', 'EDUMA_SESSION'));

// ====================
// Configuración de cookies
// ====================
// Cambia COOKIE_DOMAIN a tu dominio real en producción, sin "http://"
define('COOKIE_DOMAIN', Env::get('COOKIE_DOMAIN', 'localhost'));
// Usar true en producción si usas HTTPS
define('COOKIE_SECURE', Env::get('COOKIE_SECURE', false));
define('COOKIE_HTTPONLY', Env::get('COOKIE_HTTPONLY', true));

// Para versiones de PHP < 7.3, 'SameSite' no está soportado nativamente en setcookie()
// Si tu PHP es >= 7.3, puedes usar opciones en setcookie()
// Valores posibles: 'Lax', 'Strict', 'None' (usa None solo con Secure=true)
define('COOKIE_SAMESITE', Env::get('COOKIE_SAMESITE', 'Strict'));

// ====================
// Configuración de seguridad
// ====================
define('CSRF_TOKEN_NAME', Env::get('CSRF_TOKEN_NAME', 'csrf_token'));
define('RATE_LIMIT_MAX_ATTEMPTS', Env::get('RATE_LIMIT_MAX_ATTEMPTS', 5));
define('RATE_LIMIT_LOCKOUT_TIME', Env::get('RATE_LIMIT_LOCKOUT_TIME', 900)); // 15 minutos

// ====================
// Configuración de archivos
// ====================
//define('UPLOAD_PATH', realpath(__DIR__ . '/../uploads/') . DIRECTORY_SEPARATOR);
//define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB

// ====================
// Rutas internas de la aplicación
// ====================
define('APPROOT', realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR);
define('APP_PATH', realpath(__DIR__ . '/../app/') . DIRECTORY_SEPARATOR);
define('VIEW_PATH', APP_PATH . 'Views' . DIRECTORY_SEPARATOR);
define('CONTROLLER_PATH', APP_PATH . 'Controllers' . DIRECTORY_SEPARATOR);
define('MODEL_PATH', APP_PATH . 'Models' . DIRECTORY_SEPARATOR);
