<?php
declare(strict_types=1);

namespace App\Core\Scheduler;

class Task {
    public string $name;
    protected $callback;
    
    protected ?int $intervalMinutes = null;
    protected ?string $dailyAt = null; // 'HH:MM'
    protected bool $forceRun = false;

    public function __construct(string $name, callable $callback) {
        $this->name = $name;
        $this->callback = $callback;
    }

    /**
     * Ejecuta cada minuto (default si no se configura nada más)
     */
    public function everyMinute(): self {
        $this->intervalMinutes = 1;
        return $this;
    }

    /**
     * Ejecuta cada 5 minutos
     */
    public function everyFiveMinutes(): self {
        $this->intervalMinutes = 5;
        return $this;
    }

    /**
     * Ejecuta cada 10 minutos
     */
    public function everyTenMinutes(): self {
        $this->intervalMinutes = 10;
        return $this;
    }

        /**
     * Ejecuta cada 30 minutos
     */
     public function everyThirtyMinutes(): self {
        $this->intervalMinutes = 30;
        return $this;
    }

    /**
     * Ejecuta cada hora en punto
     */
    public function hourly(): self {
        $this->intervalMinutes = 60;
        return $this;
    }

    /**
     * Ejecuta diariamente a una hora específica (24h format HH:MM)
     */
    public function dailyAt(string $time): self {
        $this->dailyAt = $time;
        return $this;
    }

    public function isDue(): bool {
        $now = time();
        $currentHour = (int)date('H', $now);
        $currentMinute = (int)date('i', $now);

        // 1. Daily At (Priority)
        if ($this->dailyAt) {
            list($targetHour, $targetMin) = explode(':', $this->dailyAt);
            return ($currentHour == $targetHour && $currentMinute == $targetMin);
        }

        // 2. Intervalo en Minutos
        if ($this->intervalMinutes) {
            if ($this->intervalMinutes == 60) {
                return $currentMinute === 0; // Hora en punto
            }
            // Para minutos simples (reset cada hora)
            return ($currentMinute % $this->intervalMinutes) === 0;
        }

        // Default: true (cada minuto)
        return true; 
    }

    public function run() {
        if (is_callable($this->callback)) {
            call_user_func($this->callback);
        }
    }
}
