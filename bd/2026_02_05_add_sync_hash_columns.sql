-- =============================================================================
-- Migración: Agregar columnas data_hash para optimización de sincronización
-- Versión: 2026_02_05_add_sync_hash_columns  
-- Descripción: Agrega columnas de hash para detección de cambios y evitar
--              actualizaciones innecesarias durante la sincronización con Moodle
-- Compatible con: MySQL 5.7+ / 8.0+ / 9.x
-- =============================================================================

-- Desactivar verificación de FK temporalmente
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabla de usuarios - hash de datos de Moodle
-- Usamos procedimiento almacenado para verificar existencia de columnas

DELIMITER //

DROP PROCEDURE IF EXISTS add_column_if_not_exists //

CREATE PROCEDURE add_column_if_not_exists()
BEGIN
    -- Columna data_hash en usuarios
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'usuarios' 
                   AND COLUMN_NAME = 'data_hash') THEN
        ALTER TABLE `usuarios` ADD COLUMN `data_hash` VARCHAR(64) NULL DEFAULT NULL 
            COMMENT 'Hash MD5/SHA256 de los datos de Moodle para detectar cambios';
    END IF;

    -- Columna last_sync_at en usuarios
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'usuarios' 
                   AND COLUMN_NAME = 'last_sync_at') THEN
        ALTER TABLE `usuarios` ADD COLUMN `last_sync_at` DATETIME NULL DEFAULT NULL 
            COMMENT 'Última sincronización exitosa con Moodle';
    END IF;

    -- Columna data_hash en cursos
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'cursos' 
                   AND COLUMN_NAME = 'data_hash') THEN
        ALTER TABLE `cursos` ADD COLUMN `data_hash` VARCHAR(64) NULL DEFAULT NULL 
            COMMENT 'Hash de datos de Moodle';
    END IF;

    -- Columna last_sync_at en cursos
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'cursos' 
                   AND COLUMN_NAME = 'last_sync_at') THEN
        ALTER TABLE `cursos` ADD COLUMN `last_sync_at` DATETIME NULL DEFAULT NULL 
            COMMENT 'Última sincronización';
    END IF;

    -- Columna data_hash en raw_moodle_categorias (si existe la tabla)
    IF EXISTS (SELECT 1 FROM information_schema.TABLES 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raw_moodle_categorias') THEN
        IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                       WHERE TABLE_SCHEMA = DATABASE() 
                       AND TABLE_NAME = 'raw_moodle_categorias' 
                       AND COLUMN_NAME = 'data_hash') THEN
            ALTER TABLE `raw_moodle_categorias` ADD COLUMN `data_hash` VARCHAR(64) NULL DEFAULT NULL 
                COMMENT 'Hash de datos originales de Moodle';
        END IF;
    END IF;

END //

DELIMITER ;

-- Ejecutar procedimiento
CALL add_column_if_not_exists();

-- Limpiar
DROP PROCEDURE IF EXISTS add_column_if_not_exists;

-- 2. Crear índices (ignorar errores si ya existen)
-- Para usuarios
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'idx_usuarios_data_hash') = 0,
    'CREATE INDEX idx_usuarios_data_hash ON usuarios (data_hash)',
    'SELECT "Index idx_usuarios_data_hash already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'idx_usuarios_id_moodle') = 0,
    'CREATE INDEX idx_usuarios_id_moodle ON usuarios (id_moodle)',
    'SELECT "Index idx_usuarios_id_moodle already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Para cursos
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos' AND INDEX_NAME = 'idx_cursos_data_hash') = 0,
    'CREATE INDEX idx_cursos_data_hash ON cursos (data_hash)',
    'SELECT "Index idx_cursos_data_hash already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos' AND INDEX_NAME = 'idx_cursos_id_moodle') = 0,
    'CREATE INDEX idx_cursos_id_moodle ON cursos (id_moodle)',
    'SELECT "Index idx_cursos_id_moodle already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Tabla de logs de sincronización (crear si no existe)
CREATE TABLE IF NOT EXISTS `sync_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `batch_id` VARCHAR(36) NOT NULL COMMENT 'UUID del batch de sincronización',
    `entity_type` ENUM('users', 'courses', 'categories', 'cohorts', 'grades', 'enrollments') NOT NULL,
    `entity_id` INT UNSIGNED NULL COMMENT 'ID de la entidad procesada',
    `action` ENUM('insert', 'update', 'skip', 'error') NOT NULL,
    `estado` ENUM('success', 'error', 'info', 'warning') NOT NULL DEFAULT 'success',
    `mensaje` TEXT NULL,
    `data_before_hash` VARCHAR(64) NULL COMMENT 'Hash antes de la operación',
    `data_after_hash` VARCHAR(64) NULL COMMENT 'Hash después de la operación',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sync_logs_batch` (`batch_id`),
    INDEX `idx_sync_logs_entity` (`entity_type`, `entity_id`),
    INDEX `idx_sync_logs_estado` (`estado`),
    INDEX `idx_sync_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de operaciones de sincronización con Moodle';

-- 4. Tabla de estado de sincronización global
CREATE TABLE IF NOT EXISTS `sync_status` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `entity_type` VARCHAR(50) NOT NULL,
    `last_sync_start` DATETIME NULL,
    `last_sync_end` DATETIME NULL,
    `last_sync_status` ENUM('running', 'completed', 'error', 'stopped') DEFAULT 'completed',
    `total_processed` INT UNSIGNED DEFAULT 0,
    `total_errors` INT UNSIGNED DEFAULT 0,
    `last_error_message` TEXT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_entity_type` (`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Estado de última sincronización por entidad';

-- Insertar registros iniciales de estado (ignorar si ya existen)
INSERT IGNORE INTO `sync_status` (`entity_type`) VALUES 
('users'), ('courses'), ('categories'), ('cohorts'), ('grades');

-- Reactivar FK
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- FIN DE MIGRACIÓN
-- =============================================================================
SELECT 'Migración completada exitosamente' AS resultado;
