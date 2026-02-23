<?php
require 'vendor/autoload.php';
require 'Config/env.php';
Env::load();
require 'Config/database.php';
$db = (new \Config\Database())->getConnection();
$stmt = $db->prepare('SELECT * FROM usuarios WHERE username = ?');
$stmt->execute(['5141441']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user) {
    echo "USER_FOUND: " . json_encode($user);
} else {
    echo "USER_NOT_FOUND";
}
unlink(__FILE__);
