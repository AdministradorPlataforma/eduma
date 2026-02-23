<?php
/**
 * Script para verificar el estado de la migración de seguridad
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Config\Database;

echo "=== Verificación de Migración de Seguridad v3.2 ===\n\n";

try {
    $db = (new Database())->getConnection();
    
    // 1. Verificar columnas en calificaciones
    echo "1. Columnas en tabla calificaciones:\n";
    $stmt = $db->query("SHOW COLUMNS FROM calificaciones");
    $columns = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    
    $requiredColumns = ['data_hash', 'source_timestamp', 'sync_batch_id'];
    foreach ($requiredColumns as $col) {
        $status = in_array($col, $columns) ? "✓" : "✗";
        echo "   $status $col\n";
    }
    
    // 2. Verificar tabla de auditoría
    echo "\n2. Tabla audit_calificaciones:\n";
    $stmt = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_calificaciones'");
    $exists = $stmt->fetchColumn() > 0;
    echo "   " . ($exists ? "✓ Existe" : "✗ No existe") . "\n";
    
    // 3. Verificar trigger
    echo "\n3. Trigger de auditoría:\n";
    $stmt = $db->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'trg_audit_calificaciones_update'");
    $exists = $stmt->fetchColumn() > 0;
    echo "   " . ($exists ? "✓ Existe" : "✗ No existe") . "\n";
    
    // 4. Verificar vista
    echo "\n4. Vista de integridad:\n";
    $stmt = $db->query("SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_calificaciones_integridad'");
    $exists = $stmt->fetchColumn() > 0;
    echo "   " . ($exists ? "✓ Existe" : "✗ No existe") . "\n";
    
    // 5. Verificar procedimientos
    echo "\n5. Procedimientos:\n";
    $procedures = ['verificar_integridad_calificaciones', 'cleanup_audit_calificaciones'];
    foreach ($procedures as $proc) {
        $stmt = $db->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = '$proc'");
        $exists = $stmt->fetchColumn() > 0;
        echo "   " . ($exists ? "✓" : "✗") . " $proc\n";
    }
    
    // 6. Ejecutar verificación de integridad
    echo "\n6. Estado de integridad de calificaciones:\n";
    $stmt = $db->query("CALL verificar_integridad_calificaciones()");
    $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    if (empty($results)) {
        echo "   (No hay calificaciones en la tabla)\n";
    } else {
        foreach ($results as $row) {
            echo "   - {$row['estado_integridad']}: {$row['cantidad']} ({$row['porcentaje']}%)\n";
        }
    }
    
    echo "\n=== Verificación completada ===\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
