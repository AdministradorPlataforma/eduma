<?php
/**
 * Ejecutor de Sincronización Directa (sin cola)
 * 
 * Script para ejecutar sincronización de Moodle directamente,
 * útil para cron jobs o pruebas.
 * 
 * Uso:
 *   php scripts/run_sync.php [tipo] [--force]
 * 
 * Tipos válidos:
 *   all        - Sincronización completa (default)
 *   delta      - Solo cambios recientes
 *   categories - Solo categorías
 *   courses    - Solo cursos
 *   users      - Solo usuarios
 *   enrollments - Solo matrículas
 *   cohorts    - Solo cohortes
 *   grades     - Solo calificaciones
 * 
 * @version 1.0
 */

// Configuración inicial
set_time_limit(0);
ini_set('memory_limit', '1G'); // Más memoria para sync completo

// Cargar autoloader
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';
\Config\Env::load();

use App\Services\MoodleSyncOptimizedService;
use App\Services\LoggerService;

// Parsear argumentos
$type = $argv[1] ?? 'all';
$force = in_array('--force', $argv);

$validTypes = ['all', 'delta', 'categories', 'courses', 'users', 'enrollments', 'cohorts', 'grades'];

if (!in_array($type, $validTypes)) {
    echo "ERROR: Tipo de sincronización inválido: {$type}\n";
    echo "Tipos válidos: " . implode(', ', $validTypes) . "\n";
    exit(1);
}

echo "=============================================\n";
echo "  SINCRONIZACIÓN MOODLE - EDUMA V2\n";
echo "=============================================\n";
echo "Tipo: {$type}\n";
echo "Force: " . ($force ? 'Sí' : 'No') . "\n";
echo "Inicio: " . date('Y-m-d H:i:s') . "\n";
echo "---------------------------------------------\n\n";

try {
    $service = new MoodleSyncOptimizedService();
    
    // Verificar conexión primero
    echo "Verificando conexión con Moodle...\n";
    $health = $service->checkConnection();
    
    if (!($health['success'] ?? false)) {
        echo "ERROR: No se puede conectar con Moodle\n";
        echo "Detalle: " . ($health['error'] ?? 'Desconocido') . "\n";
        exit(1);
    }
    
    echo "✓ Conexión OK - Tiempo respuesta: " . ($health['response_time_ms'] ?? '?') . "ms\n\n";
    
    // Ejecutar sincronización
    $startTime = microtime(true);
    
    switch ($type) {
        case 'all':
            echo "Ejecutando sincronización COMPLETA...\n\n";
            $result = $service->sincronizarTodo($force);
            break;
            
        case 'delta':
            echo "Ejecutando sincronización INCREMENTAL...\n\n";
            $result = $service->sincronizarDelta();
            break;
            
        case 'categories':
            echo "Sincronizando CATEGORÍAS...\n\n";
            $result = $service->sincronizarCategorias();
            break;
            
        case 'courses':
            echo "Sincronizando CURSOS...\n\n";
            $result = $service->sincronizarCursosOptimizado();
            break;
            
        case 'users':
            echo "Sincronizando USUARIOS...\n\n";
            $result = $service->sincronizarUsuariosOptimizado($force);
            break;
            
        case 'enrollments':
            echo "Sincronizando MATRÍCULAS...\n\n";
            $result = $service->sincronizarMatriculasOptimizado();
            break;
            
        case 'cohorts':
            echo "Sincronizando COHORTES...\n\n";
            $result = $service->sincronizarCohortesOptimizado();
            break;
            
        case 'grades':
            echo "Sincronizando CALIFICACIONES...\n\n";
            $result = $service->sincronizarCalificacionesOptimizado();
            break;
    }
    
    $elapsed = round(microtime(true) - $startTime, 2);
    
    echo "\n---------------------------------------------\n";
    echo "  RESULTADO\n";
    echo "---------------------------------------------\n";
    
    // Mostrar resultado
    if (is_array($result)) {
        foreach ($result as $key => $value) {
            if (is_array($value)) {
                echo "{$key}:\n";
                foreach ($value as $k => $v) {
                    if (!is_array($v)) {
                        echo "  {$k}: {$v}\n";
                    }
                }
            } else {
                echo "{$key}: {$value}\n";
            }
        }
    }
    
    echo "\nTiempo total: {$elapsed} segundos\n";
    echo "Finalizado: " . date('Y-m-d H:i:s') . "\n";
    echo "\n✓ SINCRONIZACIÓN COMPLETADA CON ÉXITO\n";
    
    exit(0);
    
} catch (\Exception $e) {
    echo "\n=============================================\n";
    echo "  ERROR EN SINCRONIZACIÓN\n";
    echo "=============================================\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    LoggerService::error("run_sync.php failed", [
        'type' => $type,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    exit(1);
}
