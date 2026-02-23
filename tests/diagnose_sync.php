<?php
/**
 * Script de diagnóstico para la sincronización Moodle
 * 
 * Uso: php scripts/diagnose_sync.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';

use Config\Database;

echo "\n=== DIAGNÓSTICO DE SINCRONIZACIÓN MOODLE ===\n\n";

// 1. Verificar conexión a BD
echo "1. Verificando conexión a BD...\n";
try {
    $db = (new Database())->getConnection();
    echo "   ✓ Conexión exitosa\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Verificar tablas necesarias
echo "\n2. Verificando tablas necesarias...\n";
$requiredTables = ['sync_status', 'sync_logs', 'queue_jobs', 'usuarios', 'cursos', 'cohortes'];
foreach ($requiredTables as $table) {
    $result = $db->query("SHOW TABLES LIKE '{$table}'")->fetch();
    if ($result) {
        echo "   ✓ {$table} existe\n";
    } else {
        echo "   ✗ {$table} NO EXISTE - Ejecutar migración\n";
    }
}

// 3. Verificar estado de sync_status
echo "\n3. Verificando sync_status...\n";
try {
    $stmt = $db->query("SELECT * FROM sync_status ORDER BY last_sync_start DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "   - No hay registros de sincronización\n";
    } else {
        foreach ($rows as $row) {
            echo "   - {$row['entity_type']}: {$row['last_sync_status']} ";
            echo "(Último: " . ($row['last_sync_start'] ?? 'nunca') . ")\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// 4. Verificar jobs en cola
echo "\n4. Verificando queue_jobs...\n";
try {
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM queue_jobs GROUP BY status");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "   - No hay jobs en cola\n";
    } else {
        foreach ($rows as $row) {
            echo "   - {$row['status']}: {$row['count']} jobs\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// 5. Verificar estado JSON legacy
echo "\n5. Verificando archivo sync_state.json...\n";
$jsonPath = __DIR__ . '/../storage/sync_state.json';
if (file_exists($jsonPath)) {
    $content = json_decode(file_get_contents($jsonPath), true);
    echo "   - status: " . ($content['status'] ?? 'no definido') . "\n";
    echo "   - progress: " . ($content['progress'] ?? '0') . "%\n";
    echo "   - message: " . ($content['message'] ?? 'sin mensaje') . "\n";
    echo "   - stop_requested: " . (($content['stop_requested'] ?? false) ? 'true' : 'false') . "\n";
} else {
    echo "   - Archivo no existe (se creará al iniciar sync)\n";
}

// 6. Verificar conexión Moodle
echo "\n6. Verificando conexión a Moodle API...\n";
try {
    $moodleUrl = getenv('MOODLE_URL') ?: (defined('MOODLE_URL') ? MOODLE_URL : null);
    $moodleToken = getenv('MOODLE_TOKEN') ?: (defined('MOODLE_TOKEN') ? MOODLE_TOKEN : null);
    
    if (!$moodleUrl || !$moodleToken) {
        // Cargar desde .env
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                list($name, $value) = explode('=', $line, 2);
                putenv(trim($name) . '=' . trim($value));
            }
        }
        $moodleUrl = getenv('MOODLE_URL');
        $moodleToken = getenv('MOODLE_TOKEN');
    }
    
    echo "   URL: {$moodleUrl}\n";
    echo "   Token: " . substr($moodleToken ?: 'NO CONFIGURADO', 0, 10) . "...\n";
    
    // Hacer petición de prueba
    if ($moodleUrl && $moodleToken) {
        $testUrl = rtrim($moodleUrl, '/') . '/webservice/rest/server.php?' . http_build_query([
            'wstoken' => $moodleToken,
            'wsfunction' => 'core_webservice_get_site_info',
            'moodlewsrestformat' => 'json'
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $testUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "   ✗ Error cURL: {$error}\n";
        } elseif ($httpCode !== 200) {
            echo "   ✗ HTTP {$httpCode}\n";
        } else {
            // Verificar si es JSON o HTML
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "   ✗ Respuesta NO es JSON - probablemente Token inválido\n";
                echo "   Respuesta (primeros 200 chars): " . substr($response, 0, 200) . "\n";
            } elseif (isset($data['exception'])) {
                echo "   ✗ Error Moodle: " . ($data['message'] ?? 'Sin mensaje') . "\n";
            } else {
                echo "   ✓ Conexión exitosa\n";
                echo "   Sitio: " . ($data['sitename'] ?? 'N/A') . "\n";
                echo "   Usuario: " . ($data['username'] ?? 'N/A') . "\n";
                echo "   Funciones: " . count($data['functions'] ?? []) . " disponibles\n";
            }
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// 7. Limitar jobs pendientes (limpieza)
echo "\n7. Opciones de mantenimiento:\n";
echo "   - Para limpiar jobs atascados: php scripts/diagnose_sync.php --clean-jobs\n";
echo "   - Para resetear estado: php scripts/diagnose_sync.php --reset-state\n";

if (in_array('--clean-jobs', $argv ?? [])) {
    $db->exec("UPDATE queue_jobs SET status = 'failed', last_error = 'Limpiado manualmente' WHERE status IN ('pending', 'running')");
    echo "   ✓ Jobs limpiados\n";
}

if (in_array('--reset-state', $argv ?? [])) {
    // Resetear tabla sync_status
    $db->exec("UPDATE sync_status SET last_sync_status = 'idle', last_error_message = NULL");
    
    // Resetear archivo JSON
    if (file_exists($jsonPath)) {
        file_put_contents($jsonPath, json_encode([
            'status' => 'idle',
            'progress' => 0,
            'message' => 'Reseteado manualmente',
            'stop_requested' => false
        ], JSON_PRETTY_PRINT));
    }
    echo "   ✓ Estado reseteado (BD + JSON)\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n\n";
