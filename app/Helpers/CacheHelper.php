<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Gestor de caché simple basado en archivos (File Cache)
 */
class CacheHelper
{
    private static string $cacheDir = __DIR__ . '/../../storage/cache/';

    /**
     * Obtiene un valor de la caché.
     */
    public static function get(string $key)
    {
        $file = self::getFilePath($key);
        
        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        $data = unserialize($content);

        // Verificar expiración
        if ($data['expires'] !== 0 && time() > $data['expires']) {
            self::forget($key);
            return null;
        }

        return $data['value'];
    }

    /**
     * Guarda un valor en la caché.
     * @param int $ttl Tiempo de vida en segundos (0 para infinito)
     */
    public static function set(string $key, $value, int $ttl = 3600): bool
    {
        self::ensureCacheDir();
        $file = self::getFilePath($key);
        
        $data = [
            'value' => $value,
            'expires' => $ttl === 0 ? 0 : time() + $ttl
        ];

        return file_put_contents($file, serialize($data)) !== false;
    }

    /**
     * Elimina una entrada de la caché.
     */
    public static function forget(string $key): bool
    {
        $file = self::getFilePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    /**
     * Limpia toda la caché o por prefijo.
     */
    public static function clear(?string $prefix = null): void
    {
        self::ensureCacheDir();
        foreach (glob(self::$cacheDir . ($prefix ? $prefix . '*' : '*')) as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private static function getFilePath(string $key): string
    {
        return self::$cacheDir . md5($key) . '.cache';
    }

    private static function ensureCacheDir(): void
    {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
}
