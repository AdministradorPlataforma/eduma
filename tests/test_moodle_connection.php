<?php
/**
 * Script de Diagnóstico de Conexión Moodle
 * Ejecuta una prueba de concurrencia para verificar estabilidad.
 */

// Configuración inicial
set_time_limit(60);

// Cargar autoloader
require_once __DIR__ . '/../vendor/autoload.php';
// Intentar cargar autoloader de la app si existe
if (file_exists(__DIR__ . '/../app/Core/Autoloader.php')) {
    require_once __DIR__ . '/../app/Core/Autoloader.php';
}

// Cargar entorno
if (class_exists('Config\Env')) {
    \Config\Env::load();
} elseif (file_exists(__DIR__ . '/../config/Env.php')) {
    require_once __DIR__ . '/../config/Env.php';
    \Config\Env::load();
}

use Modules\Moodle\MoodleParallelClient;
use Config\MoodleWS;

echo "=============================================\n";
echo "  TEST DE CONEXIÓN PARALELA A MOODLE\n";
echo "=============================================\n";

$parallelLimit = MoodleWS::getParallelRequests();
echo "Configuración actual (MOODLE_PARALLEL_REQUESTS): $parallelLimit\n";

if ($parallelLimit > 10) {
    echo "ADVERTENCIA: El límite de concurrencia es alto ($parallelLimit). Podría saturar Moodle.\n";
} else {
    echo "Información: Límite conservador, ideal para estabilidad.\n";
}

try {
    $client = new MoodleParallelClient();
    
    // Probar con 5 llamadas simples de 'site_info'
    echo "\nEjecutando 5 peticiones simultáneas de 'core_webservice_get_site_info'...\n";
    
    $requests = [];
    for($i=1; $i<=5; $i++) {
        $requests[] = [
            'key' => "req_$i",
            'function' => 'core_webservice_get_site_info',
            'params' => []
        ];
    }
    
    $startTime = microtime(true);
    $results = $client->executeParallel($requests);
    $elapsed = round((microtime(true) - $startTime) * 1000, 2);
    
    echo "\nRESULTADOS:\n";
    echo "Tiempo total: {$elapsed}ms\n";
    echo "Éxitos: " . count($results['results']) . "\n";
    echo "Errores: " . count($results['errors']) . "\n";
    
    if (!empty($results['errors'])) {
        echo "\nDETALLE DE ERRORES:\n";
        foreach ($results['errors'] as $key => $error) {
            echo "[$key] " . ($error['error'] ?? 'Unknown error') . "\n";
            if (isset($error['response_preview'])) {
                echo "PREVIEW RESPUESTA:\n" . $error['response_preview'] . "\n\n";
            }
        }
    } else {
        echo "\n¡PRUEBA EXITOSA! La conexión paralela funciona correctamente.\n";
    }

} catch (\Exception $e) {
    echo "\nERROR FATAL: " . $e->getMessage() . "\n";
}
