<?php
/**
 * Test del endpoint getAsyncStatus
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';

use App\Services\SyncStateDbService;

$stateService = new SyncStateDbService();
$status = $stateService->getStatus();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $status
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
