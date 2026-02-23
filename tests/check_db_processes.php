<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';
\Config\Env::load();

try {
    $db = (new \Config\Database())->getConnection();
    $stmt = $db->query("SHOW FULL PROCESSLIST");
    $processes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== PROCESOS ACTIVOS EN MYSQL ===\n";
    $count = 0;
    foreach ($processes as $proc) {
        // Ignorar mi propia conexión y procesos dormidos
        if ($proc['Command'] === 'Sleep' && $proc['Time'] < 5) continue; 
        if (strpos($proc['Info'], 'SHOW FULL PROCESSLIST') !== false) continue;

        $count++;
        echo "ID: {$proc['Id']} | User: {$proc['User']} | Time: {$proc['Time']}s | State: {$proc['State']}\n";
        echo "Query: " . substr($proc['Info'] ?? 'NULL', 0, 100) . "\n";
        echo "--------------------------------------------------\n";
    }

    if ($count === 0) {
        echo "No hay consultas pesadas corriendo actualmente.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
