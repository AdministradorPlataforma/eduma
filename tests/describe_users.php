<?php
require_once __DIR__ . '/../vendor/autoload.php';

$db = (new Config\Database())->getConnection();
$stmt = $db->query('DESCRIBE usuarios');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== COLUMNAS DE TABLA usuarios ===\n\n";
foreach ($columns as $col) {
    echo sprintf("%-20s %-20s\n", $col['Field'], $col['Type']);
}
