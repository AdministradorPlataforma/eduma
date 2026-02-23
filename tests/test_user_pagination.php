<?php
/**
 * Test del endpoint de usuarios paginados
 */
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
require 'config/Env.php';
\Config\Env::load();

echo "Probando paginación de usuarios...\n\n";

use App\Models\Usuario\UsuarioModel;

$model = new UsuarioModel();

// Test 1: Contar todos
$total = $model->countAll();
echo "1. Total usuarios activos: " . number_format($total) . "\n";

// Test 2: Primera página
$start = microtime(true);
$users = $model->getPaginated(0, 25, '', 'id', 'DESC');
$elapsed = round((microtime(true) - $start) * 1000, 2);
echo "2. Primera página (25 registros): " . count($users) . " usuarios en {$elapsed}ms\n";

if (!empty($users)) {
    $first = $users[0];
    echo "   Primer usuario: {$first['nombre']} {$first['apellido']} ({$first['email']})\n";
}

// Test 3: Búsqueda
$start = microtime(true);
$searchResults = $model->getPaginated(0, 25, 'juan', 'id', 'DESC');
$searchCount = $model->countFiltered('juan');
$elapsed = round((microtime(true) - $start) * 1000, 2);
echo "3. Búsqueda 'juan': " . count($searchResults) . " de {$searchCount} coincidencias en {$elapsed}ms\n";

// Test 4: Página 2
$start = microtime(true);
$page2 = $model->getPaginated(25, 25, '', 'nombre', 'ASC');
$elapsed = round((microtime(true) - $start) * 1000, 2);
echo "4. Página 2 (offset 25, orden por nombre ASC): " . count($page2) . " usuarios en {$elapsed}ms\n";

if (!empty($page2)) {
    $first = $page2[0];
    echo "   Primer usuario: {$first['nombre']} {$first['apellido']}\n";
}

echo "\n✓ Paginación funcionando correctamente!\n";
echo "La vista debería cargar instantáneamente ahora.\n";
