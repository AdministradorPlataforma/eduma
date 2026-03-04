<?php
/**
 * API de validación de TFG — Servidor eduma (UMA)
 * 
 * DESPLEGAR ESTE ARCHIVO EN EL SERVIDOR DE EDUMA (190.128.232.94)
 * Ejemplo: http://190.128.232.94/api_tfg.php
 * 
 * Endpoint:
 *   POST /api_tfg.php
 *   Body JSON: { "codigo": "TFG-2026-001" }
 *   
 * Respuestas:
 *   200 OK: { "success": true, "data": { "codigo": "...", "titulo": "...", ... } }
 *   200 OK: { "success": false, "message": "Código TFG no encontrado." }
 *   405:    { "success": false, "message": "Método no permitido." }
 */

// ── Seguridad: Solo aceptar POST ────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// ── Configuración de BD local (en el mismo servidor eduma) ──────────
$DB_HOST = 'localhost'; 
$DB_NAME = 'eduma';
$DB_USER = 'root';
$DB_PASS = 'UMA2025';

// ── Leer la petición ────────────────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true);
$codigo = trim($input['codigo'] ?? '');

if (empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Código TFG requerido.']);
    exit;
}

// ── Consultar la BD ─────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $stmt = $pdo->prepare('SELECT codigo, titulo, descripcion, estado FROM tesis WHERE codigo = ? LIMIT 1');
    $stmt->execute([$codigo]);
    $tfg = $stmt->fetch();

    if ($tfg) {
        echo json_encode(['success' => true, 'data' => $tfg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Código TFG no encontrado.']);
    }

} catch (PDOException $e) {
    error_log('[API TFG] Error BD: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
