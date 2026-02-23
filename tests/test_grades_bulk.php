<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';
\Config\Env::load();

use Modules\Moodle\MoodleClient;
use Modules\Moodle\MoodleParallelClient;
use App\Services\LoggerService;

// Configurar ID de curso para pruebas (buscar uno activo)
$courseId = 110; 
// Nota: Si no existe el 110, fallará, pero el log nos dirá si Moodle acepta la función.

echo "=== PRUEBA DE CAPACIDADES DE API MOODLE ===\n";
echo "Probando función: core_grades_get_grades (Bulk)\n";

try {
    // 1. Obtener usuarios del curso para tener IDs reales
    $client = new MoodleClient();
    echo "Obteniendo usuarios del curso $courseId...\n";
    $enrolled = $client->getEnrolledUsers($courseId);
    
    if (empty($enrolled)) {
        die("No se encontraron usuarios en el curso $courseId o el curso no existe.\n");
    }
    
    $userIds = array_column(array_slice($enrolled, 0, 5), 'id'); // Tomar 5 usuarios de muestra
    echo "Usuarios muestra: " . implode(', ', $userIds) . "\n";
    
    // 2. Probar llamada Bulk
    $parallelClient = new MoodleParallelClient();
    $batches = [
        [
            'courseId' => $courseId,
            'userIds' => $userIds
        ]
    ];
    
    echo "Ejecutando getGradesBulkParallel...\n";
    $response = $parallelClient->getGradesBulkParallel($batches);
    
    echo "\n=== RESPUESTA (Estructura) ===\n";
    if (!empty($response['errors'])) {
        echo "❌ ERRORES DETECTADOS:\n";
        print_r($response['errors']);
    } else {
        echo "✅ RESPUESTA EXITOSA\n";
        $results = array_values($response['results'])[0] ?? [];
        
        // Mostrar estructura del primer elemento para ver qué datos trae
        if (!empty($results)) {
            echo "Estructura de datos retornada:\n";
            print_r($results); // Dump completo para analizar campos
        } else {
            echo "Respuesta vacía o formato inesperado.\n";
            var_dump($response);
        }
    }

} catch (Exception $e) {
    echo "Excepción fatal: " . $e->getMessage() . "\n";
}
