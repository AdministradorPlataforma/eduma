<?php
/**
 * Test directo del endpoint
 */

// Simular que el usuario está autenticado para probar
session_start();
$_SESSION['usuario_id'] = 1;
$_SESSION['permisos'] = ['sincronizar_moodle' => true];

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';

// Simular request
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_ACCEPT'] = 'application/json';

use App\Services\SyncStateDbService;

header('Content-Type: application/json');

$stateService = new SyncStateDbService();
$status = $stateService->getStatus();

if (empty($status) || !isset($status['status'])) {
    $status = [
        'status' => 'idle', 
        'progress' => 0, 
        'message' => 'Listo para sincronizar',
        'total_processed' => 0,
        'total_errors' => 0
    ];
}

echo json_encode([
    'success' => true,
    'data' => $status
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
