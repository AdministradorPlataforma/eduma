<?php
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;

class EventDispatcher {
    private static ?EventDispatcher $instance = null;
    private array $listeners = [];

    private function __construct() {}

    public static function getInstance(): EventDispatcher {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registra un listener para un evento.
     */
    public function listen(string $eventName, $listener): void {
        $this->listeners[$eventName][] = $listener;
    }

    /**
     * Dispara un evento.
     */
    public function dispatch(object $event): void {
        $eventName = get_class($event);
        
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            if (is_callable($listener)) {
                $listener($event);
            } elseif (is_array($listener) && count($listener) === 2) {
                // Caso [Class, Method]
                $class = $listener[0];
                $method = $listener[1];
                
                $instance = Container::getInstance()->get($class);
                $instance->$method($event);
            }
        }
    }
}
