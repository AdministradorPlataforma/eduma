<?php
require '../config/Env.php';
require '../config/Database.php';
Config\Env::load();
$db = (new Config\Database())->getConnection();
$batches = $db->query("SELECT * FROM sync_batches ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($batches, JSON_PRETTY_PRINT);
