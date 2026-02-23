<?php
/**
 * Worker de Cola de Sincronización EDUMA v2.1
 * 
 * Script robusto para ejecutar jobs de la cola de forma continua.
 * Soporta reentrabilidad y sobrevive a fallos temporales de DB.
 * 
 * Uso:
 *   php scripts/sync_worker.php [--daemon] [--sleep=5]
 */

// Configuración inicial
set_time_limit(0);
ini_set('memory_limit', '512M');

// Cargar Entorno EDUMA
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';
\Config\Env::load();

use App\Core\Container;
use App\Services\QueueService;
use App\Services\LoggerService;

// Parsear argumentos
$options = getopt('', ['daemon', 'sleep::']);
$isDaemon = isset($options['daemon']);
$sleepSeconds = isset($options['sleep']) ? (int)$options['sleep'] : 5;
$workerId = 'W' . getmypid();

echo "=============================================\n";
echo "  WORKER DE COLAS EDUMA - v2.1\n";
echo "=============================================\n";
echo "[{$workerId}] Iniciado: " . date('Y-m-d H:i:s') . "\n";
echo "[{$workerId}] Modo: " . ($isDaemon ? 'Continuo (Daemon)' : 'Ejecución única') . "\n";
echo "---------------------------------------------\n\n";

// Inicializar Contenedor y dependencias
$container = Container::getInstance();

// Asegurar que la DB esté bindeada (como en public/index.php)
$container->bind('db', function() {
    return (new \Config\Database())->getConnection();
}, true);
$container->bind(PDO::class, function($c) {
    return $c->get('db');
});

/** @var QueueService $queue */
$queue = $container->get(QueueService::class);

$jobsProcessed = 0;
$startTime = time();

// Bucle de trabajo
do {
    try {
        $hasWork = $queue->work();
        
        if ($hasWork) {
            $jobsProcessed++;
            echo "[{$workerId}] [" . date('H:i:s') . "] Job procesado exitosamente.\n";
            // Pequeño descanso si hubo trabajo para no saturar CPU
            usleep(100000); // 0.1s
        } else {
            if ($isDaemon) {
                // No hay trabajo, esperar silenciosamente
                // echo "."; 
                sleep($sleepSeconds);
            }
        }
    } catch (\PDOException $e) {
        // Error de BD: Probablemente pérdida de conexión
        echo "[{$workerId}] [" . date('H:i:s') . "] ERROR DB: " . $e->getMessage() . "\n";
        echo "[{$workerId}] Reintentando conexión en 10 segundos...\n";
        sleep(10);
        // Intentar restablecer conexión si el contenedor permite rebinding o simplemente dejar que falle y se reinicie
    } catch (\Throwable $e) {
        echo "[{$workerId}] [" . date('H:i:s') . "] ERROR FATAL: " . $e->getMessage() . "\n";
        LoggerService::error("Worker Critical Failure", [
            'worker' => $workerId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        if ($isDaemon) {
            sleep($sleepSeconds * 2);
        } else {
            break;
        }
    }

    // Limpieza periódica de memoria
    if ($jobsProcessed % 50 === 0 && $jobsProcessed > 0) {
        gc_collect_cycles();
    }

} while ($isDaemon);

echo "\n[{$workerId}] Worker finalizado. Total: {$jobsProcessed} jobs.\n";
