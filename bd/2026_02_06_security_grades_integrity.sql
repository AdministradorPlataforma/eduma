-- =============================================================================
-- Migración: Mejoras de Seguridad para Calificaciones v3.2
-- Versión: 2026_02_06_security_grades_integrity
-- Descripción: Agrega columna de integridad para auditoría de calificaciones
-- Compatible con: MySQL 5.7+ / 8.0+ / 9.x
-- =============================================================================

-- 1. Agregar columna data_hash a calificaciones para verificación de integridad
-- =============================================================================
DELIMITER //
DROP PROCEDURE IF EXISTS add_grade_security_columns //
CREATE PROCEDURE add_grade_security_columns()
BEGIN
    -- Columna data_hash para verificación de integridad
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'calificaciones' 
                   AND COLUMN_NAME = 'data_hash') THEN
        ALTER TABLE `calificaciones` 
        ADD COLUMN `data_hash` VARCHAR(64) NULL 
            COMMENT 'SHA-256 hash para verificación de integridad de datos' 
            AFTER `feedback`;
        
        SELECT 'Columna data_hash agregada a calificaciones' AS resultado;
    ELSE
        SELECT 'Columna data_hash ya existe en calificaciones' AS resultado;
    END IF;

    -- Columna source_timestamp para auditoría de Moodle
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'calificaciones' 
                   AND COLUMN_NAME = 'source_timestamp') THEN
        ALTER TABLE `calificaciones` 
        ADD COLUMN `source_timestamp` DATETIME NULL 
            COMMENT 'Timestamp original de Moodle para auditoría' 
            AFTER `data_hash`;
        
        SELECT 'Columna source_timestamp agregada a calificaciones' AS resultado;
    ELSE
        SELECT 'Columna source_timestamp ya existe en calificaciones' AS resultado;
    END IF;

    -- Columna sync_batch_id para trazabilidad
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'calificaciones' 
                   AND COLUMN_NAME = 'sync_batch_id') THEN
        ALTER TABLE `calificaciones` 
        ADD COLUMN `sync_batch_id` VARCHAR(36) NULL 
            COMMENT 'UUID del batch de sincronización que trajo este dato' 
            AFTER `source_timestamp`;
        
        SELECT 'Columna sync_batch_id agregada a calificaciones' AS resultado;
    ELSE
        SELECT 'Columna sync_batch_id ya existe en calificaciones' AS resultado;
    END IF;
END //
DELIMITER ;

CALL add_grade_security_columns();
DROP PROCEDURE IF EXISTS add_grade_security_columns;


-- 2. Crear tabla de auditoría de calificaciones
-- =============================================================================
CREATE TABLE IF NOT EXISTS `audit_calificaciones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `calificacion_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID de la calificación afectada',
    `matricula_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID de matrícula para referencia rápida',
    `campo_modificado` VARCHAR(50) NOT NULL COMMENT 'Nombre del campo que cambió',
    `valor_anterior` TEXT NULL COMMENT 'Valor antes del cambio',
    `valor_nuevo` TEXT NULL COMMENT 'Valor después del cambio',
    `fuente` ENUM('moodle_sync', 'manual', 'api', 'import', 'system') NOT NULL DEFAULT 'moodle_sync' 
        COMMENT 'Origen del cambio',
    `usuario_modificacion` INT UNSIGNED NULL COMMENT 'ID de usuario que realizó el cambio (si aplica)',
    `ip_origen` VARCHAR(45) NULL COMMENT 'IP desde donde se realizó el cambio',
    `sync_batch_id` VARCHAR(36) NULL COMMENT 'UUID del batch para trazabilidad',
    `data_hash_anterior` VARCHAR(64) NULL COMMENT 'Hash de integridad antes del cambio',
    `data_hash_nuevo` VARCHAR(64) NULL COMMENT 'Hash de integridad después del cambio',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_audit_calificacion` (`calificacion_id`),
    INDEX `idx_audit_matricula` (`matricula_id`),
    INDEX `idx_audit_fecha` (`created_at`),
    INDEX `idx_audit_fuente` (`fuente`),
    INDEX `idx_audit_batch` (`sync_batch_id`),
    INDEX `idx_audit_usuario` (`usuario_modificacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de auditoría para cambios en calificaciones - Cumplimiento académico';


-- 3. Trigger para auditoría automática de UPDATE en calificaciones
-- =============================================================================
DROP TRIGGER IF EXISTS `trg_audit_calificaciones_update`;

DELIMITER //
CREATE TRIGGER `trg_audit_calificaciones_update` 
AFTER UPDATE ON `calificaciones` 
FOR EACH ROW
BEGIN
    -- Solo auditar si cambió la calificación (usando <=> para comparación NULL-safe)
    -- NOT (OLD.x <=> NEW.x) es equivalente a IS DISTINCT FROM
    IF NOT (OLD.calificacion_final <=> NEW.calificacion_final) THEN
        INSERT INTO audit_calificaciones 
            (calificacion_id, matricula_id, campo_modificado, valor_anterior, valor_nuevo, 
             fuente, data_hash_anterior, data_hash_nuevo, created_at)
        VALUES 
            (NEW.id, NEW.matricula_id, 'calificacion_final', 
             CAST(OLD.calificacion_final AS CHAR), CAST(NEW.calificacion_final AS CHAR),
             'moodle_sync', OLD.data_hash, NEW.data_hash, NOW());
    END IF;
    
    -- Auditar cambios en feedback
    IF NOT (OLD.feedback <=> NEW.feedback) THEN
        INSERT INTO audit_calificaciones 
            (calificacion_id, matricula_id, campo_modificado, valor_anterior, valor_nuevo, 
             fuente, data_hash_anterior, data_hash_nuevo, created_at)
        VALUES 
            (NEW.id, NEW.matricula_id, 'feedback', 
             LEFT(COALESCE(OLD.feedback, ''), 500), LEFT(COALESCE(NEW.feedback, ''), 500),
             'moodle_sync', OLD.data_hash, NEW.data_hash, NOW());
    END IF;
END //
DELIMITER ;


-- 4. Agregar índice para verificación de integridad
-- =============================================================================
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND INDEX_NAME = 'idx_calificaciones_hash') = 0,
    'CREATE INDEX idx_calificaciones_hash ON calificaciones (data_hash)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- 5. Vista para detectar posibles inconsistencias de integridad
-- =============================================================================
DROP VIEW IF EXISTS `v_calificaciones_integridad`;
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
FROM calificaciones c;


-- 6. Procedimiento para verificar integridad de calificaciones
-- =============================================================================
DROP PROCEDURE IF EXISTS `verificar_integridad_calificaciones`;
DELIMITER //
CREATE PROCEDURE `verificar_integridad_calificaciones`()
BEGIN
    SELECT 
        estado_integridad,
        COUNT(*) as cantidad,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM calificaciones), 2) as porcentaje
    FROM v_calificaciones_integridad
    GROUP BY estado_integridad
    ORDER BY cantidad DESC;
END //
DELIMITER ;


-- 7. Procedimiento de limpieza de auditoría antigua
-- =============================================================================
DROP PROCEDURE IF EXISTS `cleanup_audit_calificaciones`;
DELIMITER //
CREATE PROCEDURE `cleanup_audit_calificaciones`(IN days_to_keep INT)
BEGIN
    DECLARE deleted_count INT DEFAULT 0;
    
    -- Mantener mínimo 365 días por requisitos de auditoría académica
    IF days_to_keep < 365 THEN
        SET days_to_keep = 365;
    END IF;
    
    DELETE FROM audit_calificaciones 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    SET deleted_count = ROW_COUNT();
    
    SELECT deleted_count as registros_eliminados, 
           days_to_keep as dias_retencion;
END //
DELIMITER ;


-- =============================================================================
-- FIN DE MIGRACIÓN
-- =============================================================================
SELECT 'Migración de seguridad para calificaciones v3.2 completada' AS resultado;
