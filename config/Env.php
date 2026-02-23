<?php
declare(strict_types=1);
// Config/Env.php
namespace Config;

class Env {
    protected static $data = [];

    public static function load($path = null) {
        $path = $path ?: __DIR__ . '/../.env';
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line || strpos($line, '#') === 0) continue;
            
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                static::$data[$name] = $value;
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
            }
        }
    }

    public static function get($key, $default = null) {
        if (empty(static::$data)) {
            static::load();
        }
        
        // SEGURIDAD v3.2: Priorizar variables del sistema real (Environment > .env file)
        // Esto permite inyectar secretos desde el servidor/container sin tocar el archivo .env
        $sysValue = getenv($key);
        
        if ($sysValue !== false) {
            $value = $sysValue;
        } else {
            $value = static::$data[$key] ?? $default;
        }

        if ($value === null) return $default;

        // Convertir strings booleanos y nulos a tipos reales de PHP
        switch (strtolower((string)$value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        return $value;
    }
}
