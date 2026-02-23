<?php
/**
 * Test del endpoint datatable con búsqueda
 */
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
require 'config/Env.php';
\Config\Env::load();

echo "Probando endpoint datatable...\n\n";

use App\Models\Usuario\UsuarioModel;

$model = new UsuarioModel();

// Simular parámetros de DataTables
$search = 'juan';
$start = 0;
$length = 25;
$orderColumn = 'id';
$orderDir = 'DESC';

try {
    echo "1. getPaginated con búsqueda '$search':\n";
    $usuarios = $model->getPaginated($start, $length, $search, $orderColumn, $orderDir);
    echo "   Resultados: " . count($usuarios) . "\n";
    
    echo "\n2. countAll:\n";
    $total = $model->countAll();
    echo "   Total: $total\n";
    
    echo "\n3. countFiltered con búsqueda '$search':\n";
    $filtered = $model->countFiltered($search);
    echo "   Filtrados: $filtered\n";
    
    // Formatear datos como lo hace el controlador
    echo "\n4. Formateando datos para DataTables:\n";
    $data = [];
    foreach ($usuarios as $u) {
        $rol = strtolower($u['rol'] ?? 'user');
        $isAdmin = strpos($rol, 'admin') !== false;
        
        $data[] = [
            'id' => $u['id'],
            'nombre' => $u['nombre'],
            'apellido' => $u['apellido'],
            'email' => $u['email'],
            'rol' => $u['rol'],
            'isAdmin' => $isAdmin,
            'activo' => $u['activo'] ?? 1,
            'initials' => strtoupper(substr($u['nombre'] ?? '', 0, 1) . substr($u['apellido'] ?? '', 0, 1))
        ];
    }
    
    $response = [
        'draw' => 1,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtered,
        'data' => $data
    ];
    
    echo "   Primer registro: " . json_encode($data[0] ?? 'ninguno', JSON_PRETTY_PRINT) . "\n";
    
    echo "\n5. JSON Response (primeros 500 chars):\n";
    $json = json_encode($response);
    if ($json === false) {
        echo "   ERROR JSON: " . json_last_error_msg() . "\n";
    } else {
        echo "   " . substr($json, 0, 500) . "...\n";
        echo "\n   ✓ JSON válido generado correctamente\n";
    }

} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
