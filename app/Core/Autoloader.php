<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Autoloader manual de respaldo (EDUMA V2)
 * Soporta namespaces: App, Config, Modules
 */
class Autoloader {
    public static function register() {
        spl_autoload_register(function ($class) {
            // Mapeo de namespaces a carpetas
            $map = [
                'App\\'    => __DIR__ . '/../../app/',
                'Config\\' => __DIR__ . '/../../config/',
                'Modules\\'=> __DIR__ . '/../../Modules/'
            ];

            foreach ($map as $prefix => $base_dir) {
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    continue;
                }

                $relative_class = substr($class, $len);
                // Convertir namespace a ruta de archivo con la capitalización correcta
                $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
            return false;
        });
    }
}

Autoloader::register();
