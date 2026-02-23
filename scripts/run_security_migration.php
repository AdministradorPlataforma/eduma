<?php
/**
 * Script para ejecutar las partes restantes de la migración de seguridad de calificaciones
 * Solo ejecuta las partes que fallaron anteriormente
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Config\Database;

echo "=== Migración de Seguridad de Calificaciones v3.2 ===\n\n";

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    
    echo "Conectado a la base de datos.\n";
    
    // 1. Verificar si las columnas ya existen (ya fueron creadas)
    $stmt = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND COLUMN_NAME = 'data_hash'");
    $hasDataHash = $stmt->fetchColumn() > 0;
    echo "- Columna data_hash: " . ($hasDataHash ? "✓ existe" : "× no existe") . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND COLUMN_NAME = 'source_timestamp'");
    $hasSourceTimestamp = $stmt->fetchColumn() > 0;
    echo "- Columna source_timestamp: " . ($hasSourceTimestamp ? "✓ existe" : "× no existe") . "\n";
    
    // 2. Crear tabla de auditoría si no existe
    echo "\nCreando tabla audit_calificaciones...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS `audit_calificaciones` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `calificacion_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID de la calificación afectada',
            `matricula_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID de matrícula para referencia rápida',
            `campo_modificado` VARCHAR(50) NOT NULL COMMENT 'Nombre del campo que cambió',
            `valor_anterior` TEXT NULL COMMENT 'Valor antes del cambio',
            `valor_nuevo` TEXT NULL COMMENT 'Valor después del cambio',
            `fuente` ENUM('moodle_sync', 'manual', 'api', 'import', 'system') NOT NULL DEFAULT 'moodle_sync',
            `usuario_modificacion` INT UNSIGNED NULL,
            `ip_origen` VARCHAR(45) NULL,
            `sync_batch_id` VARCHAR(36) NULL,
            `data_hash_anterior` VARCHAR(64) NULL,
            `data_hash_nuevo` VARCHAR(64) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_audit_calificacion` (`calificacion_id`),
            INDEX `idx_audit_matricula` (`matricula_id`),
            INDEX `idx_audit_fecha` (`created_at`),
            INDEX `idx_audit_fuente` (`fuente`),
            INDEX `idx_audit_batch` (`sync_batch_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Tabla audit_calificaciones creada/verificada\n";
    
    // 3. Crear trigger de auditoría
    echo "\nCreando trigger de auditoría...\n";
    $db->exec("DROP TRIGGER IF EXISTS `trg_audit_calificaciones_update`");
    
    $db->exec("
        CREATE TRIGGER `trg_audit_calificaciones_update` 
        AFTER UPDATE ON `calificaciones` 
        FOR EACH ROW
        BEGIN
            IF NOT (OLD.calificacion_final <=> NEW.calificacion_final) THEN
                INSERT INTO audit_calificaciones 
                    (calificacion_id, matricula_id, campo_modificado, valor_anterior, valor_nuevo, 
                     fuente, data_hash_anterior, data_hash_nuevo, created_at)
                VALUES 
                    (NEW.id, NEW.matricula_id, 'calificacion_final', 
                     CAST(OLD.calificacion_final AS CHAR), CAST(NEW.calificacion_final AS CHAR),
                     'moodle_sync', OLD.data_hash, NEW.data_hash, NOW());
            END IF;
            
            IF NOT (OLD.feedback <=> NEW.feedback) THEN
                INSERT INTO audit_calificaciones 
                    (calificacion_id, matricula_id, campo_modificado, valor_anterior, valor_nuevo, 
                     fuente, data_hash_anterior, data_hash_nuevo, created_at)
                VALUES 
                    (NEW.id, NEW.matricula_id, 'feedback', 
                     LEFT(COALESCE(OLD.feedback, ''), 500), LEFT(COALESCE(NEW.feedback, ''), 500),
                     'moodle_sync', OLD.data_hash, NEW.data_hash, NOW());
            END IF;
        END
    ");
    echo "✓ Trigger trg_audit_calificaciones_update creado\n";
    
    // 4. Crear índice para hash
    echo "\nVerificando índices...\n";
    $stmt = $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND INDEX_NAME = 'idx_calificaciones_hash'");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("CREATE INDEX idx_calificaciones_hash ON calificaciones (data_hash)");
        echo "✓ Índice idx_calificaciones_hash creado\n";
    } else {
        echo "- Índice idx_calificaciones_hash ya existe\n";
    }
    
    // 5. Crear vista de integridad
    echo "\nCreando vista de integridad...\n";
    $db->exec("DROP VIEW IF EXISTS `v_calificaciones_integridad`");
    $db->exec("
        CREATE VIEW `v_calificaciones_integridad` AS
        SELECT 
            c.id,
            c.matricula_id,
            c.id_moodle_item,
            c.calificacion_final,
            c.calificacion_maxima,
            c.data_hash,
            c.updated_at,
            CASE 
                WHEN c.data_hash IS NULL THEN 'sin_hash'
                WHEN c.calificacion_final < 0 THEN 'nota_negativa'
                WHEN c.calificacion_final > c.calificacion_maxima THEN 'nota_excede_maximo'
                WHEN c.calificacion_maxima <= 0 THEN 'maximo_invalido'
                ELSE 'ok'
            END as estado_integridad
        FROM calificaciones c
    ");
    echo "✓ Vista v_calificaciones_integridad creada\n";
    
    // 6. Crear procedimiento de verificación
    echo "\nCreando procedimientos...\n";
    $db->exec("DROP PROCEDURE IF EXISTS `verificar_integridad_calificaciones`");
    $db->exec("
        CREATE PROCEDURE `verificar_integridad_calificaciones`()
        BEGIN
            SELECT 
                estado_integridad,
                COUNT(*) as cantidad,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM calificaciones), 2) as porcentaje
            FROM v_calificaciones_integridad
            GROUP BY estado_integridad
            ORDER BY cantidad DESC;
        END
    ");
    echo "✓ Procedimiento verificar_integridad_calificaciones creado\n";
    
    // 7. Crear procedimiento de limpieza
    $db->exec("DROP PROCEDURE IF EXISTS `cleanup_audit_calificaciones`");
    $db->exec("
        CREATE PROCEDURE `cleanup_audit_calificaciones`(IN days_to_keep INT)
        BEGIN
            DECLARE deleted_count INT DEFAULT 0;
            
            IF days_to_keep < 365 THEN
                SET days_to_keep = 365;
            END IF;
            
            DELETE FROM audit_calificaciones 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
            
            SET deleted_count = ROW_COUNT();
            
            SELECT deleted_count as registros_eliminados, days_to_keep as dias_retencion;
        END
    ");
    echo "✓ Procedimiento cleanup_audit_calificaciones creado\n";
    
    echo "\n=== Migración completada exitosamente ===\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Error PDO: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
