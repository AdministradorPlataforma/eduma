<?php
/**
 * Script para probar el reset de procesos y ver el error
 */
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
require 'config/Env.php';
\Config\Env::load();

echo "Probando resetProcesses directamente...\n\n";

try {
    $db = (new \Config\Database())->getConnection();

    echo "1. Verificando registros en sync_status:\n";
    $stmt = $db->query("SELECT entity_type, last_sync_status FROM sync_status");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        echo "   - {$row['entity_type']}: {$row['last_sync_status']}\n";
    }

    echo "\n2. Ejecutando UPDATE en sync_status...\n";
    $result = $db->exec("UPDATE sync_status SET last_sync_status = 'error', last_error_message = 'Reset manual por usuario' WHERE last_sync_status IN ('running', 'stopping')");
    echo "   Filas afectadas: $result\n";

    echo "\n3. Verificando queue_jobs...\n";
    $stmt = $db->query("SELECT id, status, queue FROM queue_jobs WHERE status = 'running' LIMIT 5");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Jobs corriendo: " . count($jobs) . "\n";

    echo "\n4. Ejecutando UPDATE en queue_jobs...\n";
    $result = $db->exec("UPDATE queue_jobs SET status = 'failed', last_error = 'Reset manual por sistema' WHERE status = 'running'");
    echo "   Filas afectadas: $result\n";

    echo "\n5. Verificando archivo JSON legacy...\n";
    $jsonPath = dirname(__DIR__) . '/storage/sync_state.json';
    if (file_exists($jsonPath)) {
        echo "   Archivo existe: $jsonPath\n";
        echo "   Contenido: " . file_get_contents($jsonPath) . "\n";
    } else {
        echo "   Archivo no existe (OK)\n";
    }

    echo "\n6. Reseteando Circuit Breaker...\n";
    \Modules\Moodle\MoodleClient::resetCircuitBreaker();
    echo "   ✓ Circuit breaker reseteado\n";

    echo "\n✓ TODOS LOS PASOS EJECUTADOS CORRECTAMENTE\n";
    echo "El resetProcesses debería funcionar sin problemas.\n";

} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
