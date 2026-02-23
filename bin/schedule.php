<?php
// bin/scheduler.php (Entry Point para Cron)

if (php_sapi_name() !== 'cli') {
    exit('Solo CLI');
}

// 1. Bootstrapping
require_once __DIR__ . '/../app/Core/Autoloader.php';
\Config\Env::load();

// Constantes
if (!defined('APP_PATH')) define('APP_PATH', __DIR__ . '/../app/');
if (!defined('BASE_URL')) define('BASE_URL', \Config\Env::get('APP_URL') . '/');

// Container & DB
$container = \App\Core\Container::getInstance();
$container->bind('db', function() {
    return (new \Config\Database())->getConnection();
}, true);


// 2. Scheduler
$scheduler = new \App\Core\Scheduler\Scheduler();

// ------------ DEFINICIÓN DE TAREAS ------------ //

// 1. Limpieza de Temporales (Janitor)
$scheduler->schedule('System Janitor', function() {
    echo "Running Janitor cleanup...\n";
    $janitor = new \App\Services\JanitorService();
    $result = $janitor->run();
    echo "Files deleted: " . ($result['details']['temp_files'] ?? 0) . "\n";
})->dailyAt('03:00'); // 3 AM

// 2. Moodle Sync (Ejemplo: Sync Académico cada hora)
$scheduler->schedule('Moodle Academic Sync', function() use ($container) {
    echo "Syncing Moodle Academic Data...\n";
    if (class_exists('\App\Services\MoodleSyncService')) {
         $sync = new \App\Services\MoodleSyncService($container->get('db'));
         // $sync->syncAll(); // Implementar cuando exista
         echo "Sync Service Found (Simulated Run)\n";
    } else {
        echo "MoodleSyncService not found. Skipping.\n";
    }
})->hourly(); // Cada hora en punto

// 3. Heartbeat (Prueba cada minuto)
$scheduler->schedule('Heartbeat', function() {
    echo "Biip...\n";
})->everyFiveMinutes();

// ---------------------------------------------- //

// 3. Ejecutar
$scheduler->run();
