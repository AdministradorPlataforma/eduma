<?php
declare(strict_types=1);

namespace App\Commands;

use App\Services\QueueService;
use App\Services\LoggerService;

class QueueWorkerCommand {
    
    private QueueService $queue;
    private bool $running = true;

    public function __construct() {
        $this->queue = new QueueService();
        // Manejo básico de señales para parada segura (en sistemas *nix, en Windows es limitado pero útil estructura)
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'stop']);
            pcntl_signal(SIGINT, [$this, 'stop']);
        }
    }

    public function stop(): void {
        echo "[Worker] Deteniendo limpieza...\n";
        $this->running = false;
    }

    public function execute(): void {
        echo "\n[EDUMA V2 Worker] Iniciando procesador de colas.\n";
        echo "=================================================\n";
        echo "Logs: c:/wamp64/www/eduma2/logs/app.log\n";
        echo "Presiona Ctrl+C para detener.\n\n";

        LoggerService::info("Queue Worker iniciado.");

        while ($this->running) {
            try {
                // Procesar el siguiente trabajo
                $processed = $this->queue->work();

                if ($processed) {
                    echo "[" . date('H:i:s') . "] Trabajo procesado.\n";
                    // Pequeña pausa para liberar CPU
                    usleep(100000); 
                } else {
                    // Si no hay trabajos, dormimos 2 segundos para no hacer polling agresivo
                    sleep(2);
                }

                // Checkeo de señales pendiente
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

            } catch (\Throwable $e) {
                $msg = "Error crítico en worker: " . $e->getMessage();
                echo "[" . date('H:i:s') . "] $msg\n";
                LoggerService::error($msg, ['trace' => $e->getTraceAsString()]);
                sleep(5); // Pausa de seguridad en caso de error repetitivo
            }
        }

        echo "[Worker] Detenido correctamente.\n";
    }
}
