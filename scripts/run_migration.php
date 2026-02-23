<?php
/**
 * Script para ejecutar migraciones de forma segura
 * Uso: php scripts/run_migration.php [nombre_migracion]
 */

// Configurar error handling
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Cambiar al directorio raíz
chdir(dirname(__DIR__));

// Cargar autoloader y configuración
require_once 'vendor/autoload.php';
require_once 'config/Env.php';
require_once 'config/Database.php';

// Inicializar Env
\Config\Env::load();


$migrationName = $argv[1] ?? '2026_02_06_sync_improvements.sql';
$migrationPath = "database/migrations/{$migrationName}";

if (!file_exists($migrationPath)) {
    die("Error: Migración no encontrada: {$migrationPath}\n");
}

echo "=== Ejecutando migración: {$migrationName} ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

try {
    $db = (new \Config\Database())->getConnection();
    
    // Parte 1: Agregar columna 'rol' a curso_matriculas
    echo "1. Verificando columna 'rol' en curso_matriculas...\n";
    
    $checkCol = $db->query("SHOW COLUMNS FROM curso_matriculas LIKE 'rol'");
    if ($checkCol->rowCount() === 0) {
        $db->exec("ALTER TABLE curso_matriculas ADD COLUMN `rol` VARCHAR(50) DEFAULT 'student' AFTER `usuario_id`");
        echo "   ✓ Columna 'rol' agregada exitosamente\n";
    } else {
        echo "   ✓ Columna 'rol' ya existe\n";
    }
    
    // Parte 2: Agregar índice por rol
    echo "2. Verificando índice idx_matriculas_rol...\n";
    
    $checkIdx = $db->query("SHOW INDEX FROM curso_matriculas WHERE Key_name = 'idx_matriculas_rol'");
    if ($checkIdx->rowCount() === 0) {
        $db->exec("ALTER TABLE curso_matriculas ADD INDEX `idx_matriculas_rol` (`rol`)");
        echo "   ✓ Índice idx_matriculas_rol agregado\n";
    } else {
        echo "   ✓ Índice idx_matriculas_rol ya existe\n";
    }
    
    // Parte 3: Agregar índice compuesto usuario+rol
    echo "3. Verificando índice idx_matriculas_usuario_rol...\n";
    
    $checkIdx = $db->query("SHOW INDEX FROM curso_matriculas WHERE Key_name = 'idx_matriculas_usuario_rol'");
    if ($checkIdx->rowCount() === 0) {
        $db->exec("ALTER TABLE curso_matriculas ADD INDEX `idx_matriculas_usuario_rol` (`usuario_id`, `rol`)");
        echo "   ✓ Índice idx_matriculas_usuario_rol agregado\n";
    } else {
        echo "   ✓ Índice idx_matriculas_usuario_rol ya existe\n";
    }
    
    // Parte 4: Crear tabla sync_metrics si no existe
    echo "4. Verificando tabla sync_metrics...\n";
    
    $db->exec("CREATE TABLE IF NOT EXISTS `sync_metrics` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `sync_id` VARCHAR(50) NOT NULL COMMENT 'ID de la sincronización',
        `entity` VARCHAR(50) NOT NULL COMMENT 'Entidad sincronizada (users, courses, etc.)',
        `phase` VARCHAR(50) NOT NULL COMMENT 'Fase del proceso',
        `metric_name` VARCHAR(100) NOT NULL COMMENT 'Nombre de la métrica',
        `metric_value` DECIMAL(15,4) NOT NULL COMMENT 'Valor de la métrica',
        `metric_unit` VARCHAR(20) DEFAULT NULL COMMENT 'Unidad (segundos, registros, bytes, etc.)',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_sync_metrics_sync_id` (`sync_id`),
        INDEX `idx_sync_metrics_entity` (`entity`),
        INDEX `idx_sync_metrics_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Métricas detalladas de sincronización'");
    echo "   ✓ Tabla sync_metrics verificada/creada\n";
    
    // Parte 5: Crear tabla sync_error_summary si no existe
    echo "5. Verificando tabla sync_error_summary...\n";
    
    $db->exec("CREATE TABLE IF NOT EXISTS `sync_error_summary` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `sync_id` VARCHAR(50) NOT NULL COMMENT 'ID de la sincronización',
        `entity` VARCHAR(50) NOT NULL COMMENT 'Entidad con error',
        `error_type` VARCHAR(200) NOT NULL COMMENT 'Tipo de error',
        `error_count` INT UNSIGNED DEFAULT 1 COMMENT 'Cantidad de ocurrencias',
        `first_occurrence` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `last_occurrence` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `sample_data` JSON DEFAULT NULL COMMENT 'Muestra de datos del error',
        UNIQUE KEY `uk_sync_error_type` (`sync_id`, `entity`, `error_type`),
        INDEX `idx_sync_error_sync_id` (`sync_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Resumen agrupado de errores de sincronización'");
    echo "   ✓ Tabla sync_error_summary verificada/creada\n";
    
    // Parte 6: Actualizar sync_status con nuevas columnas
    echo "6. Verificando columnas adicionales en sync_status...\n";
    
    $checkCol = $db->query("SHOW COLUMNS FROM sync_status LIKE 'parallel_requests_active'");
    if ($checkCol->rowCount() === 0) {
        $db->exec("ALTER TABLE sync_status ADD COLUMN `parallel_requests_active` INT UNSIGNED DEFAULT 0");
        echo "   ✓ Columna parallel_requests_active agregada\n";
    } else {
        echo "   ✓ Columna parallel_requests_active ya existe\n";
    }
    
    $checkCol = $db->query("SHOW COLUMNS FROM sync_status LIKE 'circuit_breaker_open'");
    if ($checkCol->rowCount() === 0) {
        $db->exec("ALTER TABLE sync_status ADD COLUMN `circuit_breaker_open` TINYINT(1) DEFAULT 0");
        echo "   ✓ Columna circuit_breaker_open agregada\n";
    } else {
        echo "   ✓ Columna circuit_breaker_open ya existe\n";
    }
    
    echo "\n=== ✓ Migración completada exitosamente ===\n";
    echo "Columna 'rol' está lista para la sincronización de matrículas.\n";
    
} catch (PDOException $e) {
    echo "\n=== ✗ Error de BD ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n=== ✗ Error General ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
