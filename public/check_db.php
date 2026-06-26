<?php
require '../config/Env.php';
require '../config/Database.php';
Config\Env::load();
$db = (new Config\Database())->getConnection();
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$result = [];
foreach($tables as $t) {
    $count = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    $result[$t] = $count;
}
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
