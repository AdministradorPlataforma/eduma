<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';
\Config\Env::load();

use App\Services\MoodleSyncOptimizedService;

// Obtener 5 IDs de cursos visibles
$database = new \Config\Database();
$db = $database->getConnection();
$stmt = $db->query("SELECT id_moodle FROM cursos WHERE visible = 1 LIMIT 200"); // Tomamos 200 para llenar los chunks
$courseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($courseIds)) {
    die("No hay cursos visibles para probar.\n");
}

$sample = array_slice($courseIds, 0, 10); // Probar con 10 cursos para resultado rápido

echo "=== BENCHMARK DE CALIFICACIONES (OPTIMIZADO) ===\n";
echo "Cursos a procesar: " . count($sample) . "\n";
echo "Hilos paralelos configurados: " . \Config\MoodleWS::getParallelRequests() . "\n";
echo "Inicio: " . date('H:i:s') . "\n";

$start = microtime(true);
$service = new MoodleSyncOptimizedService();

// Hack: Usar reflexión para llamar al método protegido o simplemente pasarle los IDs si el método público lo permite
// El método sincronizarCalificacionesOptimizado(?array $courseIds = null) es público y acepta IDs.
$stats = $service->sincronizarCalificacionesOptimizado($sample);

$duration = microtime(true) - $start;

echo "\n=== RESULTADOS ===\n";
echo "Tiempo total: " . round($duration, 2) . " segundos\n";
echo "Promedio por curso: " . round($duration / count($sample), 2) . " segundos\n";
print_r($stats);
