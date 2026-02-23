<?php
/**
 * Script de diagnóstico para conexión Moodle
 * 
 * Uso: php scripts/moodle_health_check.php
 * 
 * Verifica:
 * 1. Configuración de ambiente (.env)
 * 2. Conectividad con el servidor Moodle
 * 3. Validez del token
 * 4. Funciones de API disponibles
 */

declare(strict_types=1);

// Bootstrap básico
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Cargar configuración
use Config\Env;
use Config\MoodleWS;
use Modules\Moodle\MoodleClient;

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║       DIAGNÓSTICO DE CONEXIÓN MOODLE - EDUMA V2         ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// 1. Verificar configuración
echo "1. VERIFICANDO CONFIGURACIÓN...\n";
echo str_repeat("-", 50) . "\n";

$moodleUrl = MoodleWS::getUrl();
$moodleToken = MoodleWS::getToken();
$tokenPreview = substr($moodleToken, 0, 10) . "..." . substr($moodleToken, -5);

echo "   URL:     $moodleUrl\n";
echo "   Token:   $tokenPreview\n";
echo "   Timeout: " . MoodleWS::TIMEOUT . " segundos\n";
echo "   Batch:   " . MoodleWS::USER_BATCH_SIZE . " usuarios\n";

// Validaciones básicas
$issues = [];

if (empty($moodleUrl)) {
    $issues[] = "❌ MOODLE_URL no está configurada en .env";
}

if ($moodleToken === 'TU_TOKEN_WS_MOODLE' || empty($moodleToken)) {
    $issues[] = "❌ MOODLE_TOKEN no está configurada en .env (usando valor por defecto)";
}

if (strpos($moodleUrl, '/webservice/rest/server.php') === false) {
    $issues[] = "⚠️  La URL no parece incluir /webservice/rest/server.php";
}

if (count($issues) > 0) {
    echo "\n   PROBLEMAS DETECTADOS:\n";
    foreach ($issues as $issue) {
        echo "   $issue\n";
    }
    if (strpos($issues[0], '❌') !== false) {
        echo "\n   Por favor corrija estos problemas antes de continuar.\n\n";
        exit(1);
    }
}

echo "   ✅ Configuración básica OK\n\n";

// 2. Verificar conectividad
echo "2. PROBANDO CONECTIVIDAD...\n";
echo str_repeat("-", 50) . "\n";

try {
    $client = new MoodleClient();
    
    // Health check
    $result = $client->healthCheck();
    
    if ($result['success'] ?? false) {
        echo "   ✅ Conexión exitosa\n";
        echo "   Sitio:     " . ($result['sitename'] ?? 'N/A') . "\n";
        echo "   Versión:   " . ($result['version'] ?? 'N/A') . "\n";
        echo "   Usuario:   " . ($result['username'] ?? 'N/A') . "\n";
        echo "   Funciones: " . ($result['functions_count'] ?? 'N/A') . " disponibles\n";
    } else {
        echo "   ❌ Error de conexión: " . ($result['error'] ?? 'Error desconocido') . "\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "   ❌ Excepción: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// 3. Probar funciones principales
echo "3. PROBANDO FUNCIONES DE API...\n";
echo str_repeat("-", 50) . "\n";

$functions = [
    'Categorías'   => fn() => $client->getCategories(),
    'Cursos (3)'   => fn() => $client->getCoursesByIds([1, 2, 3]),
    'Usuarios (2)' => fn() => $client->getUsersByIds([2, 3]),
];

foreach ($functions as $name => $callable) {
    echo "   Probando $name... ";
    try {
        $start = microtime(true);
        $data = $callable();
        $elapsed = round((microtime(true) - $start) * 1000);
        $count = is_array($data) ? count($data) : '?';
        echo "✅ OK ($count items, {$elapsed}ms)\n";
    } catch (\Exception $e) {
        echo "❌ " . substr($e->getMessage(), 0, 60) . "...\n";
    }
}

echo "\n";

// 4. Estado del Circuit Breaker
echo "4. ESTADO DEL CIRCUIT BREAKER\n";
echo str_repeat("-", 50) . "\n";

$cbStatus = MoodleClient::getCircuitBreakerStatus();
$cbState = $cbStatus['is_open'] ? '🔴 ABIERTO' : '🟢 CERRADO';
echo "   Estado:              $cbState\n";
echo "   Fallos consecutivos: " . $cbStatus['consecutive_failures'] . "/" . $cbStatus['threshold'] . "\n";

if ($cbStatus['is_open']) {
    $remaining = $cbStatus['reset_time'] - (time() - $cbStatus['open_since']);
    echo "   Reset automático en: " . max(0, $remaining) . " segundos\n";
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║              DIAGNÓSTICO COMPLETADO                      ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";
