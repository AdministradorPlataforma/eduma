<?php
/**
 * CLI Script para ejecutar el Janitor de EDUMA
 * Uso: php scripts/run_janitor.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Cargar Entorno
\Config\Env::load();

echo "--- EDUMA Janitor Tool ---\n";
echo "Iniciando limpieza del sistema...\n";

try {
    // Inyectar dependencias manualmente
    $db = (new \Config\Database())->getConnection();
    $syncState = new \App\Services\SyncStateDbService($db);
    $janitor = new \App\Services\JanitorService($syncState, $db);
    $results = $janitor->runAll();

    foreach ($results as $task => $count) {
        echo "[+] $task: $count\n";
    }

    echo "--- Mantenimiento Finalizado ---\n";
} catch (\Exception $e) {
    echo "[!] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
