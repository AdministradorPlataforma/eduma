<?php
declare(strict_types=1);

/**
 * API de validación de TFG — Servidor EDUMA
 * 
 * Endpoint:
 *   POST /api_tfg.php
 *   Body JSON: { "codigo": "TFG-2026-001" }
 *   
 * Respuestas:
 *   200 OK: { "success": true,  "data": { "codigo": "...", "titulo": "...", ... } }
 *   200 OK: { "success": false, "message": "Código TFG no encontrado." }
 *   400:    { "success": false, "message": "..." }  (validación)
 *   405:    { "success": false, "message": "Método no permitido." }
 *   429:    { "success": false, "message": "Demasiadas solicitudes..." }
 *   500:    { "success": false, "message": "Error interno del servidor." }
 */

// ── Bootstrap: Reutilizar infraestructura del proyecto ──────────────
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';

\Config\Env::load();

// ── Configuración de errores ────────────────────────────────────────
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$logPath = __DIR__ . '/../logs/php_error.log';
if (file_exists($logPath)) {
    ini_set('error_log', $logPath);
}

// ── CORS: Orígenes permitidos (configurable desde .env) ─────────────
$allowedOrigins = array_filter(array_map('trim', explode(
    ',',
    \Config\Env::get('API_TFG_ALLOWED_ORIGINS', '')
)));

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (!empty($allowedOrigins) && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} elseif (empty($allowedOrigins)) {
    // Fallback: Si no hay orígenes configurados, aceptar solo el mismo servidor
    header('Access-Control-Allow-Origin: ' . (\Config\Env::get('APP_URL', 'http://localhost')));
    header('Vary: Origin');
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');

// ── Preflight CORS ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Seguridad: Solo aceptar POST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// ── Rate Limiting simple (basado en archivos temporales) ────────────
$clientIp    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitDir = sys_get_temp_dir() . '/eduma_api_rate';
$maxRequests = 30;   // máximo de peticiones
$windowSecs  = 60;   // por ventana de tiempo (segundos)

if (!is_dir($rateLimitDir)) {
    @mkdir($rateLimitDir, 0700, true);
}

$rateFile = $rateLimitDir . '/' . md5($clientIp) . '.json';

$rateData = ['count' => 0, 'window_start' => time()];
if (file_exists($rateFile)) {
    $raw = @file_get_contents($rateFile);
    if ($raw !== false) {
        $parsed = json_decode($raw, true);
        if (is_array($parsed)) {
            $rateData = $parsed;
        }
    }
}

// Resetear ventana si expiró
if ((time() - ($rateData['window_start'] ?? 0)) > $windowSecs) {
    $rateData = ['count' => 0, 'window_start' => time()];
}

$rateData['count']++;
@file_put_contents($rateFile, json_encode($rateData), LOCK_EX);

if ($rateData['count'] > $maxRequests) {
    http_response_code(429);
    header('Retry-After: ' . $windowSecs);
    echo json_encode([
        'success' => false,
        'message' => 'Demasiadas solicitudes. Intente nuevamente en ' . $windowSecs . ' segundos.'
    ]);
    exit;
}

// ── Leer y validar la petición JSON ─────────────────────────────────
$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El cuerpo de la petición debe ser JSON válido.']);
    exit;
}

$codigo = isset($input['codigo']) ? trim((string)$input['codigo']) : '';

if (empty($codigo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Código TFG requerido.']);
    exit;
}

// Validación de formato: solo alfanumérico + guiones, máx 50 caracteres
if (strlen($codigo) > 50 || !preg_match('/^[A-Za-z0-9\-]+$/', $codigo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de código TFG inválido.']);
    exit;
}

// ── Consultar la BD (reutilizando Config\Database) ──────────────────
try {
    $db   = (new \Config\Database())->getConnection();
    $stmt = $db->prepare(
        'SELECT codigo, titulo, descripcion, estado FROM tesis WHERE codigo = :codigo LIMIT 1'
    );
    $stmt->execute([':codigo' => $codigo]);
    $tfg = $stmt->fetch();

    if ($tfg) {
        echo json_encode(['success' => true, 'data' => $tfg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Código TFG no encontrado.']);
    }
} catch (\PDOException $e) {
    error_log('[API_TFG] Error BD: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
} catch (\RuntimeException $e) {
    error_log('[API_TFG] Error conexión: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
