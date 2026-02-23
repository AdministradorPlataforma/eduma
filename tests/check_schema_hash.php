<?php
require_once __DIR__ . '/config/Env.php';
require_once __DIR__ . '/config/Database.php';

use Config\Database;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'calificaciones' 
        AND COLUMN_NAME = 'data_hash'
    ");
    $stmt->execute();
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($col) {
        echo "Column found:\n";
        print_r($col);
        
        // Check if length is sufficient
        $length = $col['CHARACTER_MAXIMUM_LENGTH'];
        if ($length < 100) {
             echo "WARNING: Column length ($length) might be too short for versioned hash (v1$...). Consider increasing to 100 or 255.\n";
        } else {
             echo "SUCCESS: Column length ($length) is sufficient.\n";
        }

    } else {
        echo "Column 'data_hash' not found in table 'calificaciones'.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
