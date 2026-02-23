<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php'; 

use Config\Database;
use Config\Env;

// Cargar variables de entorno usando la clase nativa del proyecto
Env::load();

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "\n=== 5 JOBS FALLIDOS MÁS RECIENTES ===\n";
    $stmt = $db->query("SELECT id, handler, last_error, created_at FROM queue_jobs WHERE status = 'failed' ORDER BY created_at DESC LIMIT 5");
    $failedJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($failedJobs)) {
        echo "✅ No se encontraron jobs fallidos recientes.\n";
    } else {
        foreach ($failedJobs as $job) {
            echo "ID: {$job['id']} | Fecha: {$job['created_at']}\n";
            echo "Handler: " . substr($job['handler'], 0, 100) . "...\n";
            
            // Extraer mensaje de error principal
            $exception = $job['last_error'];
            if (strlen($exception) > 500) {
                $exception = substr($exception, 0, 500) . "... (truncado)";
            }
            echo "Error: {$exception}\n";
            echo str_repeat("-", 80) . "\n";
        }
    }

    echo "\n=== LOGS DE CALIFICACIONES (INFO/WARN/ERROR) ===\n";
    $stmt = $db->query("SELECT entidad, estado, mensaje, created_at FROM sync_logs WHERE entidad = 'grades' ORDER BY created_at DESC LIMIT 20");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($logs)) {
        echo "✅ No hay logs de calificaciones recientes.\n";
    } else {
        foreach ($logs as $log) {
            echo "[{$log['created_at']}] [{$log['estado']}] [{$log['entidad']}]\n   {$log['mensaje']}\n";
            echo str_repeat(".", 80) . "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error al inspeccionar BD: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
