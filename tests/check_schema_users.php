<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Core/Autoloader.php'; // Ensure autoloader catches Config

use Config\Database;

try {
    $db = (new Database())->getConnection();
    
    // Check tables: tesis_estudiantes, tesis_docentes
    $tables = ['usuarios', 'estudiantes', 'docentes'];
    
    foreach ($tables as $table) {
        echo "Table: $table\n";
        $stmt = $db->query("DESCRIBE $table");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo " - {$col['Field']} ({$col['Type']})\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
