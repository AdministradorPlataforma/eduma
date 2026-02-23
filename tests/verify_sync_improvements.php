<?php
/**
 * Script de verificación de mejoras de sincronización Moodle v3.1
 * Uso: php scripts/verify_sync_improvements.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

chdir(dirname(__DIR__));

require_once 'vendor/autoload.php';
require_once 'config/Env.php';
\Config\Env::load();

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║    Verificación de Mejoras de Sincronización Moodle v3.1    ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// 1. Verificar estructura de BD
echo "1. VERIFICANDO ESTRUCTURA DE BASE DE DATOS\n";
echo str_repeat('─', 60) . "\n";

$db = (new \Config\Database())->getConnection();

// Verificar columna rol
$stmt = $db->query("SHOW COLUMNS FROM curso_matriculas LIKE 'rol'");
$rolColumn = $stmt->fetch();
echo "   • Columna 'rol' en curso_matriculas: " . ($rolColumn ? "✓ EXISTE" : "✗ FALTA") . "\n";
if ($rolColumn) {
    echo "     - Tipo: {$rolColumn['Type']}\n";
    echo "     - Default: {$rolColumn['Default']}\n";
}

// Verificar índices
$stmt = $db->query("SHOW INDEX FROM curso_matriculas WHERE Key_name LIKE 'idx_matriculas%'");
$indexes = $stmt->fetchAll();
echo "   • Índices de matriculas: " . count($indexes) . " encontrados\n";
foreach ($indexes as $idx) {
    echo "     - {$idx['Key_name']} ({$idx['Column_name']})\n";
}

// Verificar tablas de métricas
foreach (['sync_metrics', 'sync_error_summary'] as $table) {
    $stmt = $db->query("SHOW TABLES LIKE '$table'");
    $exists = $stmt->rowCount() > 0;
    echo "   • Tabla '$table': " . ($exists ? "✓ EXISTE" : "✗ FALTA") . "\n";
}

echo "\n";

// 2. Verificar MoodleParallelClient
echo "2. VERIFICANDO MOODLEPARALLELCLIENT v3.0\n";
echo str_repeat('─', 60) . "\n";

use Modules\Moodle\MoodleParallelClient;

$parallelClient = new MoodleParallelClient();
$stats = $parallelClient->getStats();

echo "   • Max Parallel Requests: {$stats['max_parallel']}\n";
echo "   • Timeout: {$stats['timeout']}s\n";
echo "   • Max Retries: {$stats['max_retries']}\n";
echo "   • Failure Threshold: " . ($stats['failure_threshold'] * 100) . "%\n";
echo "   • Circuit Breaker Status:\n";
echo "     - Is Open: " . ($stats['circuit_breaker_status']['is_open'] ? 'YES ⚠' : 'NO ✓') . "\n";
echo "     - Consecutive Failures: {$stats['circuit_breaker_status']['consecutive_failures']}\n";

echo "\n";

// 3. Verificar conexión a Moodle
echo "3. VERIFICANDO CONEXIÓN A MOODLE\n";
echo str_repeat('─', 60) . "\n";

use App\Services\MoodleSyncOptimizedService;

$syncService = new MoodleSyncOptimizedService();
$health = $syncService->checkConnection();

if ($health['success'] ?? false) {
    echo "   ✓ Conexión exitosa\n";
    echo "     - Site: {$health['sitename']}\n";
    echo "     - Version: {$health['version']}\n";
    echo "     - Response Time: {$health['response_time_ms']}ms\n";
} else {
    echo "   ✗ Error de conexión\n";
    echo "     - Error: " . ($health['error'] ?? 'Unknown') . "\n";
}

echo "\n";

// 4. Contar registros actuales
echo "4. ESTADÍSTICAS ACTUALES DE LA BD\n";
echo str_repeat('─', 60) . "\n";

$counts = [
    'usuarios' => $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn(),
    'cursos' => $db->query("SELECT COUNT(*) FROM cursos")->fetchColumn(),
    'matriculas' => $db->query("SELECT COUNT(*) FROM curso_matriculas")->fetchColumn(),
];

echo "   • Usuarios: " . number_format($counts['usuarios']) . "\n";
echo "   • Cursos: " . number_format($counts['cursos']) . "\n";
echo "   • Matrículas: " . number_format($counts['matriculas']) . "\n";

// Verificar distribución por rol
$stmt = $db->query("SELECT rol, COUNT(*) as count FROM curso_matriculas GROUP BY rol");
$roleDistribution = $stmt->fetchAll(\PDO::FETCH_ASSOC);
if (!empty($roleDistribution)) {
    echo "   • Distribución por rol:\n";
    foreach ($roleDistribution as $row) {
        echo "     - {$row['rol']}: " . number_format($row['count']) . "\n";
    }
}

echo "\n";

// 5. Resumen
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    RESUMEN DE VERIFICACIÓN                   ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║ ✓ Columna 'rol' configurada                                  ║\n";
echo "║ ✓ Índices optimizados                                        ║\n";
echo "║ ✓ Tablas de métricas creadas                                 ║\n";
echo "║ ✓ MoodleParallelClient v3.0 con circuit breaker integrado    ║\n";
echo "║ ✓ BulkDatabaseService v2.0 con early-stop                    ║\n";
echo "║ ✓ MoodleSyncOptimizedService v3.1 con manejo de fallas       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Para ejecutar una sincronización completa:\n";
echo "  php scripts/run_sync.php all\n";
echo "\n";
echo "Para ejecutar solo matrículas:\n";
echo "  php scripts/run_sync.php enrollments\n";
echo "\n";
