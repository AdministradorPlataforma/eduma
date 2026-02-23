<?php
declare(strict_types=1);

namespace App\Core\Scheduler;

class Scheduler {
    protected array $tasks = [];
    protected $logger;
    
    /**
     * Registrar una nueva tarea
     */
    public function schedule(string $name, callable $callback): Task {
        $task = new Task($name, $callback);
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * Ejecutar las tareas pendientes
     */
    public function run() {
        echo "Scheduler running at " . date('Y-m-d H:i:s') . "\n";
        
        foreach ($this->tasks as $task) {
            try {
                if ($task->isDue()) {
                    echo " -> Running task: {$task->name}...\n";
                    $start = microtime(true);
                    
                    // Ejecutar (podríamos loguear en DB aquí)
                    $task->run();
                    
                    $duration = microtime(true) - $start;
                    echo " -> Done in " . number_format($duration, 4) . "s\n";
                }
            } catch (\Exception $e) {
                echo " [ERROR] Task {$task->name} failed: " . $e->getMessage() . "\n";
            }
        }
    }
}
