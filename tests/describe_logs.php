<?php
require_once __DIR__ . '/../vendor/autoload.php';

$db = (new Config\Database())->getConnection();
$stmt = $db->query('DESCRIBE sync_logs');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== COLUMNAS DE TABLA sync_logs ===\n\n";
foreach ($columns as $col) {
    echo sprintf("%-20s %-20s\n", $col['Field'], $col['Type']);
}
