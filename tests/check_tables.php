<?php
require 'vendor/autoload.php';
require 'config/Env.php';
\Config\Env::load();

$db = (new \Config\Database())->getConnection();

echo "Verificando tablas necesarias para reset...\n\n";

$tables = ['queue_jobs', 'sync_status', 'sync_logs', 'audit_logs'];
foreach ($tables as $table) {
    try {
        $r = $db->query("SHOW TABLES LIKE '$table'");
        $exists = $r->rowCount() > 0;
        echo "$table: " . ($exists ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";
        
        if ($exists) {
            $cols = $db->query("SHOW COLUMNS FROM $table");
            echo "  Columns: " . implode(', ', array_column($cols->fetchAll(), 'Field')) . "\n";
        }
    } catch(Exception $e) {
        echo "$table: ERROR - " . $e->getMessage() . "\n";
    }
}
