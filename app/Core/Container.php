<?php
declare(strict_types=1);

namespace App\Core;

use Exception;

/**
 * Contenedor de Inyección de Dependencias Simple (EDUMA Container)
 */
class Container
{
    private static ?Container $instance = null;
    private array $bindings = [];
    private array $instances = [];

    private function __construct() {}

    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registra un servicio o valor.
     */
    public function bind(string $key, $resolver, bool $singleton = true): void
    {
        $this->bindings[$key] = [
            'resolver' => $resolver,
            'singleton' => $singleton
        ];
    }

    /**
     * Resuelve una instancia del contenedor (Auto-Resolución mediante Reflection).
     */
    public function get(string $key)
    {
        // 1. Si es un singleton y ya existe, devolverlo
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        // 2. Si existe un binding explícito (closure o valor)
        if (isset($this->bindings[$key])) {
            $resolver = $this->bindings[$key]['resolver'];
            $singleton = $this->bindings[$key]['singleton'];

            $instance = is_callable($resolver) ? $resolver($this) : $resolver;

            if ($singleton) {
                $this->instances[$key] = $instance;
            }

            return $instance;
        }

        // 3. Auto-resolución avanzada usando Reflection
        if (!class_exists($key)) {
            throw new Exception("No se pudo resolver [$key]: Clase no encontrada.");
        }

        $reflection = new \ReflectionClass($key);

        // Si no es instanciable (interfaz o clase abstracta) y no tenía binding
        if (!$reflection->isInstantiable()) {
            throw new Exception("La clase [$key] no es instanciable y no tiene binding.");
        }

        $constructor = $reflection->getConstructor();

        // Si no tiene constructor, instanciar directamente
        if (is_null($constructor)) {
            return new $key();
        }

        // Resolver parámetros del constructor
        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if (!$type || $type->isBuiltin()) {
                // Si tiene valor por defecto
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }
                throw new Exception("No se puede auto-resolver parámetro escalar [{$parameter->getName()}] en [$key].");
            }

            $dependentClass = $type->getName();
            
            // Caso especial: Si pide PDO, intentamos darselo desde el binding 'db'
            if ($dependentClass === 'PDO') {
                $dependencies[] = $this->get('db');
            } else {
                $dependencies[] = $this->get($dependentClass);
            }
        }

        $instance = $reflection->newInstanceArgs($dependencies);

        return $instance;
    }

    /**
     * Alias de get()
     */
    public function make(string $key)
    {
        return $this->get($key);
    }
}
