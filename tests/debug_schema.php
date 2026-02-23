<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php'; 

use Config\Database;
use Config\Env;

Env::load();

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "=== SCHEMA DE SYNC_LOGS ===\n";
    $stmt = $db->query("DESCRIBE sync_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "{$col['Field']} ({$col['Type']})\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
