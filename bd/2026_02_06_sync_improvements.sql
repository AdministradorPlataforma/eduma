-- =====================================================
-- MIGRACIÓN: Mejoras de Sincronización Moodle v3.1
-- Fecha: 2026-02-06
-- Autor: Antigravity AI
-- =====================================================
-- 
-- Esta migración incluye:
-- 1. Adición de columna `rol` a curso_matriculas (CRÍTICO)
-- 2. Índices adicionales para rendimiento
-- 3. Tabla de métricas para parallel requests
-- =====================================================

-- =====================================================
-- PARTE 1: COLUMNA ROL EN CURSO_MATRICULAS (CRÍTICO)
-- =====================================================
-- Esta columna faltante causa el error:
-- "Unknown column 'rol' in 'field list'"

-- Verificar si la columna existe antes de agregarla
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'curso_matriculas' 
  AND COLUMN_NAME = 'rol';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE curso_matriculas ADD COLUMN `rol` VARCHAR(50) DEFAULT ''student'' AFTER `usuario_id`',
    'SELECT ''Column rol already exists'' AS result');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar índice para consultas por rol
SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'curso_matriculas' 
  AND INDEX_NAME = 'idx_matriculas_rol';

SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE curso_matriculas ADD INDEX `idx_matriculas_rol` (`rol`)',
    'SELECT ''Index idx_matriculas_rol already exists'' AS result');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice compuesto para búsquedas por usuario+rol
SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'curso_matriculas' 
  AND INDEX_NAME = 'idx_matriculas_usuario_rol';

SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE curso_matriculas ADD INDEX `idx_matriculas_usuario_rol` (`usuario_id`, `rol`)',
    'SELECT ''Index idx_matriculas_usuario_rol already exists'' AS result');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- PARTE 2: TABLA DE MÉTRICAS PARA SINCRONIZACIÓN
-- =====================================================

CREATE TABLE IF NOT EXISTS `sync_metrics` (
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
COMMENT='Métricas detalladas de sincronización';

-- =====================================================
-- PARTE 3: TABLA DE ERRORES AGRUPADOS
-- =====================================================

CREATE TABLE IF NOT EXISTS `sync_error_summary` (
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
COMMENT='Resumen agrupado de errores de sincronización';

-- =====================================================
-- PARTE 4: ACTUALIZAR ESTRUCTURA DE SYNC_STATUS
-- =====================================================

-- Agregar columnas adicionales a sync_status si no existen
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'sync_status' 
  AND COLUMN_NAME = 'parallel_requests_active';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE sync_status ADD COLUMN `parallel_requests_active` INT UNSIGNED DEFAULT 0 AFTER `last_error_message`',
    'SELECT ''Column parallel_requests_active already exists'' AS result');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'sync_status' 
  AND COLUMN_NAME = 'circuit_breaker_open';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE sync_status ADD COLUMN `circuit_breaker_open` TINYINT(1) DEFAULT 0 AFTER `parallel_requests_active`',
    'SELECT ''Column circuit_breaker_open already exists'' AS result');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- PARTE 5: PROCEDIMIENTO DE LIMPIEZA
-- =====================================================

DROP PROCEDURE IF EXISTS cleanup_sync_metrics;
DELIMITER //
CREATE PROCEDURE cleanup_sync_metrics(IN days_to_keep INT)
BEGIN
    DECLARE cutoff_date DATETIME;
    SET cutoff_date = DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    DELETE FROM sync_metrics WHERE created_at < cutoff_date;
    DELETE FROM sync_error_summary WHERE first_occurrence < cutoff_date;
    
    -- Optimizar tablas
    OPTIMIZE TABLE sync_metrics;
    OPTIMIZE TABLE sync_error_summary;
END //
DELIMITER ;

-- =====================================================
-- FIN DE LA MIGRACIÓN
-- =====================================================
SELECT 'Migración 2026_02_06_sync_improvements.sql ejecutada correctamente' AS status;
