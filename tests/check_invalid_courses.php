<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';
\Config\Env::load();

$db = (new \Config\Database())->getConnection();
$stmt = $db->query("SELECT id, id_moodle, fullname FROM cursos WHERE id_moodle = 0 OR id_moodle IS NULL");
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($courses) > 0) {
    echo "¡ALERTA! Se encontraron " . count($courses) . " cursos con ID Moodle inválido (0 o NULL):\n";
    foreach ($courses as $c) {
        echo "- ID Local: {$c['id']}, ID Moodle: " . var_export($c['id_moodle'], true) . ", Nombre: {$c['fullname']}\n";
    }
} else {
    echo "Correcto: No hay cursos con id_moodle = 0\n";
}
