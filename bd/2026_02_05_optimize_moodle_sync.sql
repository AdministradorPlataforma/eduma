-- =============================================================================
-- Migración: Optimización de Sincronización Moodle v3.0
-- Versión: 2026_02_05_optimize_moodle_sync
-- Descripción: Mejoras para soportar 22K+ usuarios, 10K+ cursos
-- Compatible con: MySQL 5.7+ / 8.0+ / 9.x
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabla de cola de trabajos (queue_jobs) - Agregar columnas para optimización
-- =============================================================================

-- Agregar prioridad a los jobs
DELIMITER //
DROP PROCEDURE IF EXISTS optimize_queue_jobs //
CREATE PROCEDURE optimize_queue_jobs()
BEGIN
    -- Columna priority
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'queue_jobs' 
                   AND COLUMN_NAME = 'priority') THEN
        ALTER TABLE `queue_jobs` ADD COLUMN `priority` TINYINT UNSIGNED DEFAULT 5 
            COMMENT 'Prioridad 1-10 (1=más alta)' AFTER `status`;
    END IF;

    -- Columna attempts
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'queue_jobs' 
                   AND COLUMN_NAME = 'attempts') THEN
        ALTER TABLE `queue_jobs` ADD COLUMN `attempts` TINYINT UNSIGNED DEFAULT 0 
            COMMENT 'Número de intentos' AFTER `priority`;
    END IF;

    -- Columna max_attempts
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'queue_jobs' 
                   AND COLUMN_NAME = 'max_attempts') THEN
        ALTER TABLE `queue_jobs` ADD COLUMN `max_attempts` TINYINT UNSIGNED DEFAULT 3 
            COMMENT 'Máximo de reintentos' AFTER `attempts`;
    END IF;

    -- Columna reserved_at (para bloqueo de workers)
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'queue_jobs' 
                   AND COLUMN_NAME = 'reserved_at') THEN
        ALTER TABLE `queue_jobs` ADD COLUMN `reserved_at` DATETIME NULL 
            COMMENT 'Timestamp de reserva por worker' AFTER `max_attempts`;
    END IF;

    -- Columna worker_id
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'queue_jobs' 
                   AND COLUMN_NAME = 'worker_id') THEN
        ALTER TABLE `queue_jobs` ADD COLUMN `worker_id` VARCHAR(50) NULL 
            COMMENT 'ID del worker procesando' AFTER `reserved_at`;
    END IF;

    -- Columna queue (para múltiples colas)
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'queue_jobs' 
                   AND COLUMN_NAME = 'queue') THEN
        ALTER TABLE `queue_jobs` ADD COLUMN `queue` VARCHAR(50) DEFAULT 'default' 
            COMMENT 'Nombre de cola' AFTER `id`;
    END IF;
END //
DELIMITER ;

CALL optimize_queue_jobs();
DROP PROCEDURE IF EXISTS optimize_queue_jobs;

-- Índices para queue_jobs
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_jobs' AND INDEX_NAME = 'idx_queue_pending') = 0,
    'CREATE INDEX idx_queue_pending ON queue_jobs (queue, status, priority, created_at)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'queue_jobs' AND INDEX_NAME = 'idx_queue_reserved') = 0,
    'CREATE INDEX idx_queue_reserved ON queue_jobs (reserved_at, status)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- 2. Tabla sync_batches - Para tracking de batches de sincronización
-- =============================================================================
CREATE TABLE IF NOT EXISTS `sync_batches` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `batch_id` VARCHAR(36) NOT NULL COMMENT 'UUID del batch',
    `sync_type` VARCHAR(50) NOT NULL COMMENT 'Tipo: all, users, courses, delta, etc',
    `status` ENUM('pending', 'running', 'completed', 'error', 'stopped') DEFAULT 'pending',
    `started_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `total_items` INT UNSIGNED DEFAULT 0,
    `processed_items` INT UNSIGNED DEFAULT 0,
    `error_items` INT UNSIGNED DEFAULT 0,
    `skipped_items` INT UNSIGNED DEFAULT 0,
    `stats_json` JSON NULL COMMENT 'Estadísticas detalladas en JSON',
    `error_message` TEXT NULL,
    `triggered_by` VARCHAR(100) NULL COMMENT 'Usuario o proceso que inició',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_batch_id` (`batch_id`),
    INDEX `idx_sync_type_status` (`sync_type`, `status`),
    INDEX `idx_started_at` (`started_at`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Batches de sincronización con Moodle';


-- 3. Optimización de índices en tablas principales
-- =============================================================================

-- usuarios: índice compuesto para sync
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'idx_usuarios_sync') = 0,
    'CREATE INDEX idx_usuarios_sync ON usuarios (id_moodle, data_hash, last_sync_at)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- cursos: índice para sync y visible
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cursos' AND INDEX_NAME = 'idx_cursos_sync_visible') = 0,
    'CREATE INDEX idx_cursos_sync_visible ON cursos (visible, id_moodle)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- curso_matriculas: índice compuesto para lookups
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'curso_matriculas' AND INDEX_NAME = 'idx_matriculas_lookup') = 0,
    'CREATE INDEX idx_matriculas_lookup ON curso_matriculas (curso_id, usuario_id)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- calificaciones: índice para upsert
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calificaciones' AND INDEX_NAME = 'idx_calificaciones_upsert') = 0,
    'CREATE INDEX idx_calificaciones_upsert ON calificaciones (matricula_id, id_moodle_item)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- 4. Tabla sync_metrics - Para métricas de rendimiento
-- =============================================================================
CREATE TABLE IF NOT EXISTS `sync_metrics` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `batch_id` VARCHAR(36) NOT NULL,
    `phase` VARCHAR(50) NOT NULL COMMENT 'Fase: categories, courses, users, etc',
    `metric_name` VARCHAR(100) NOT NULL COMMENT 'Nombre: api_calls, db_queries, etc',
    `metric_value` DECIMAL(15,4) NOT NULL,
    `metric_unit` VARCHAR(20) NULL COMMENT 'Unidad: ms, count, bytes',
    `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_batch_phase` (`batch_id`, `phase`),
    INDEX `idx_metric_name` (`metric_name`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Métricas de rendimiento de sincronización';


-- 5. Agregar columna para tracking de sincronización en tablas de entidades
-- =============================================================================
DELIMITER //
DROP PROCEDURE IF EXISTS add_sync_tracking_columns //
CREATE PROCEDURE add_sync_tracking_columns()
BEGIN
    -- cohortes: id_moodle si no existe
    IF EXISTS (SELECT 1 FROM information_schema.TABLES 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cohortes') THEN
        IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                       WHERE TABLE_SCHEMA = DATABASE() 
                       AND TABLE_NAME = 'cohortes' 
                       AND COLUMN_NAME = 'id_moodle') THEN
            ALTER TABLE `cohortes` ADD COLUMN `id_moodle` INT UNSIGNED NULL UNIQUE 
                COMMENT 'ID en Moodle' AFTER `id`;
        END IF;

        -- cohortes: data_hash
        IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                       WHERE TABLE_SCHEMA = DATABASE() 
                       AND TABLE_NAME = 'cohortes' 
                       AND COLUMN_NAME = 'data_hash') THEN
            ALTER TABLE `cohortes` ADD COLUMN `data_hash` VARCHAR(64) NULL 
                COMMENT 'Hash para detección de cambios';
        END IF;
    END IF;

    -- curso_matriculas: last_sync_at para tracking
    IF EXISTS (SELECT 1 FROM information_schema.TABLES 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'curso_matriculas') THEN
        IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                       WHERE TABLE_SCHEMA = DATABASE() 
                       AND TABLE_NAME = 'curso_matriculas' 
                       AND COLUMN_NAME = 'last_sync_at') THEN
            ALTER TABLE `curso_matriculas` ADD COLUMN `last_sync_at` DATETIME NULL 
                COMMENT 'Última sincronización';
        END IF;
    END IF;
END //
DELIMITER ;

CALL add_sync_tracking_columns();
DROP PROCEDURE IF EXISTS add_sync_tracking_columns;


-- 5.5 Crear tabla sync_logs si no existe (para esta migración independiente)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `sync_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `batch_id` VARCHAR(36) NOT NULL COMMENT 'UUID del batch de sincronización',
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'Tipo de entidad',
    `entity_id` INT UNSIGNED NULL COMMENT 'ID de la entidad procesada',
    `action` VARCHAR(20) NOT NULL DEFAULT 'info' COMMENT 'Acción realizada',
    `estado` VARCHAR(20) NOT NULL DEFAULT 'success' COMMENT 'Estado del log',
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


-- 5.6 Crear tabla sync_status si no existe
-- =============================================================================
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
('users'), ('courses'), ('categories'), ('cohorts'), ('grades'), ('enrollments'), ('all');


-- 6. Vista para monitoreo de sincronización (simplificada para evitar errores)
-- =============================================================================
DROP VIEW IF EXISTS `v_sync_dashboard`;
CREATE VIEW `v_sync_dashboard` AS
SELECT 
    ss.entity_type,
    ss.last_sync_start,
    ss.last_sync_end,
    ss.last_sync_status,
    ss.total_processed,
    ss.total_errors,
    TIMESTAMPDIFF(SECOND, ss.last_sync_start, IFNULL(ss.last_sync_end, NOW())) as duration_seconds,
    ss.updated_at
FROM sync_status ss
ORDER BY ss.entity_type;


-- 7. Procedimiento para limpieza programada
-- =============================================================================
DROP PROCEDURE IF EXISTS `cleanup_sync_data`;
DELIMITER //
CREATE PROCEDURE `cleanup_sync_data`(IN days_to_keep INT)
BEGIN
    DECLARE deleted_logs INT DEFAULT 0;
    DECLARE deleted_batches INT DEFAULT 0;
    DECLARE deleted_metrics INT DEFAULT 0;
    
    -- Limpiar logs antiguos
    DELETE FROM sync_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    SET deleted_logs = ROW_COUNT();
    
    -- Limpiar batches antiguos
    DELETE FROM sync_batches 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY)
    AND status IN ('completed', 'error', 'stopped');
    SET deleted_batches = ROW_COUNT();
    
    -- Limpiar métricas antiguas
    DELETE FROM sync_metrics 
    WHERE recorded_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    SET deleted_metrics = ROW_COUNT();
    
    -- Limpiar jobs completados/fallidos
    DELETE FROM queue_jobs 
    WHERE status IN ('completed', 'failed')
    AND created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    SELECT deleted_logs as logs_deleted, 
           deleted_batches as batches_deleted,
           deleted_metrics as metrics_deleted;
END //
DELIMITER ;


SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- FIN DE MIGRACIÓN
-- =============================================================================
SELECT 'Migración de optimización Moodle Sync v3.0 completada' AS resultado;

