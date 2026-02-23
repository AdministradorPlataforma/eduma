-- Script de actualización para EDUMA V2
-- Propósito: Añadir timestamps (created_at, updated_at) y tablas faltantes (queue, cohortes, audit)
-- Fecha: 2026-02-04

SET FOREIGN_KEY_CHECKS = 0;

-- 1. TABLAS FALTANTES
-- ===================

-- Sistema de Colas
CREATE TABLE IF NOT EXISTS queue_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    handler LONGTEXT NOT NULL,
    status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    attempts TINYINT DEFAULT 0,
    last_error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Cohortes (Moodle)
CREATE TABLE IF NOT EXISTS cohortes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_moodle INT UNSIGNED UNIQUE NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    idnumber VARCHAR(100),
    descripcion TEXT,
    visible TINYINT(1) DEFAULT 1,
    data_hash VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Logs de Auditoría
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL, 
    action VARCHAR(50) NOT NULL,
    resource VARCHAR(100) NULL,
    details JSON NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- 2. ACTUALIZACIÓN DE TIMESTAMPS EN TABLAS EXISTENTES
-- ===================================================

-- Procedimiento auxiliar seguro: añade columnas solo si no existen
DROP PROCEDURE IF EXISTS AddTimestamps;
DELIMITER //
CREATE PROCEDURE AddTimestamps(IN tbl VARCHAR(64), IN col VARCHAR(64), IN def VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @stmt = CONCAT('ALTER TABLE ', tbl, ' ADD COLUMN ', col, ' ', def);
        PREPARE stmt FROM @stmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

-- Facultades
CALL AddTimestamps('facultades', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
CALL AddTimestamps('facultades', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Carreras
CALL AddTimestamps('carreras', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
CALL AddTimestamps('carreras', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Estudiantes
CALL AddTimestamps('estudiantes', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
CALL AddTimestamps('estudiantes', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Docentes
CALL AddTimestamps('docentes', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
CALL AddTimestamps('docentes', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Administrativos
CALL AddTimestamps('administrativos', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
CALL AddTimestamps('administrativos', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Cursos
CALL AddTimestamps('cursos', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
CALL AddTimestamps('cursos', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Calificaciones
CALL AddTimestamps('calificaciones', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
CALL AddTimestamps('calificaciones', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Roles
CALL AddTimestamps('roles', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
CALL AddTimestamps('roles', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Permisos
CALL AddTimestamps('permisos', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
CALL AddTimestamps('permisos', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Usuario Roles (Pivot)
CALL AddTimestamps('usuario_roles', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

-- Rol Permisos (Pivot)
CALL AddTimestamps('rol_permisos', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');

-- Sync Logs (Añadir updated_at si falta)
CALL AddTimestamps('sync_logs', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

DROP PROCEDURE IF EXISTS AddTimestamps;

SET FOREIGN_KEY_CHECKS = 1;

SELECT "Actualización de base de datos completada exitosamente." AS Mensaje;
