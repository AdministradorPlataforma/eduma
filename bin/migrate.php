<?php
// bin/migrate.php
if (php_sapi_name() !== 'cli') {
    exit('Solo CLI');
}

// 1. Autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../app/Core/Autoloader.php';

// 2. Env
\Config\Env::load();

// 3. Constantes Básicas (Path overrides para CLI si necesario)
if (!defined('APP_PATH')) define('APP_PATH', __DIR__ . '/../app/');
if (!defined('BASE_URL')) define('BASE_URL', \Config\Env::get('APP_URL') . '/');

// 4. Container & DB
$container = \App\Core\Container::getInstance();
$container->bind('db', function() {
    return (new \Config\Database())->getConnection();
}, true);

use App\Core\Database\MigrationRunner;

echo "==========================================\n";
echo "   EDUMA Database Migration Tool CLTv1    \n";
echo "==========================================\n";

try {
    $runner = new MigrationRunner();
    echo "Verificando migraciones en: " . realpath(__DIR__ . '/../bd/migrations') . "\n";
    
    $processed = $runner->run();
    
    if (empty($processed)) {
        echo "[INFO] No hay migraciones pendientes. Todo actualizado.\n";
    } else {
        echo "[SUCCESS] Se ejecutaron " . count($processed) . " migraciones:\n";
        foreach ($processed as $m) {
            echo " [+ OK] $m\n";
        }
    }
} catch (Exception $e) {
    echo "[ERROR] Falló la migración: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nProceso finalizado.\n";
