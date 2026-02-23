-- ==============================================
-- EDUMA — Migración: Tablas Faltantes Críticas
-- Fecha: 2026-02-23
-- Auditoría: Correcciones #2, #3 del informe
-- ==============================================
-- Tablas creadas:
--   1. periodos_academicos — Normalización de períodos temporales
--   2. asistencias — Registro normalizado de asistencia
--   3. Índices de rendimiento en calificaciones
--   4. Columna force_password_change en usuarios
-- ==============================================

-- -----------------------------------------------
-- 1. PERIODOS ACADÉMICOS
-- -----------------------------------------------
-- Reemplaza el uso de texto plano (semestre_texto, anio_texto)
-- por una tabla normalizada con estado y fechas reales.

CREATE TABLE IF NOT EXISTS periodos_academicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE COMMENT 'Código único: 2026-1, 2025-2',
    nombre VARCHAR(100) NOT NULL COMMENT 'Primer Semestre 2026',
    tipo ENUM('semestral', 'cuatrimestral', 'anual', 'intensivo') NOT NULL DEFAULT 'semestral',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado ENUM('planificacion', 'activo', 'cerrado', 'archivado') NOT NULL DEFAULT 'planificacion',
    es_actual TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Solo 1 período puede ser actual',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estado (estado),
    INDEX idx_fechas (fecha_inicio, fecha_fin),
    INDEX idx_actual (es_actual)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------
-- 2. RELACIÓN CURSOS → PERIODO ACADÉMICO
-- -----------------------------------------------
-- Agrega FK opcional a cursos para vincular con el período

-- Verificar y agregar columna periodo_id en cursos si no existe
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cursos' 
    AND COLUMN_NAME = 'periodo_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE cursos ADD COLUMN periodo_id INT UNSIGNED DEFAULT NULL AFTER carrera_id, ADD INDEX idx_periodo (periodo_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- -----------------------------------------------
-- 3. ASISTENCIAS
-- -----------------------------------------------
-- Tabla normalizada para registro de asistencia por matrícula/fecha.

CREATE TABLE IF NOT EXISTS asistencias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricula_id BIGINT UNSIGNED NOT NULL COMMENT 'FK a curso_matriculas',
    fecha DATE NOT NULL,
    estado ENUM('presente', 'ausente', 'tardanza', 'justificado') NOT NULL DEFAULT 'presente',
    observaciones TEXT NULL,
    registrado_por INT UNSIGNED NULL COMMENT 'Docente o admin que registró',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_asistencia (matricula_id, fecha),
    FOREIGN KEY (matricula_id) REFERENCES curso_matriculas(id) ON DELETE CASCADE,
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_fecha (fecha),
    INDEX idx_estado (estado),
    INDEX idx_registrador (registrado_por)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------
-- 4. ÍNDICES DE RENDIMIENTO EN CALIFICACIONES
-- -----------------------------------------------
-- Índices faltantes detectados en la auditoría.

-- Índice en fecha_modificacion (queries de calificaciones recientes)
SET @idx_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'calificaciones'
    AND INDEX_NAME = 'idx_fecha_mod'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE calificaciones ADD INDEX idx_fecha_mod (fecha_modificacion)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice en calificacion_final (rangos y estadísticas)
SET @idx_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'calificaciones'
    AND INDEX_NAME = 'idx_calificacion_final'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE calificaciones ADD INDEX idx_calificacion_final (calificacion_final)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- -----------------------------------------------
-- 5. COLUMNA force_password_change EN USUARIOS
-- -----------------------------------------------
-- Necesaria para la migración de contraseñas legacy.

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND COLUMN_NAME = 'force_password_change'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE usuarios ADD COLUMN force_password_change TINYINT(1) NOT NULL DEFAULT 0 AFTER suspended',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- -----------------------------------------------
-- 6. SEED INICIAL DE PERIODOS (Ejemplo)
-- -----------------------------------------------
-- Insertar períodos comunes si la tabla está vacía

INSERT INTO periodos_academicos (codigo, nombre, tipo, fecha_inicio, fecha_fin, estado, es_actual)
SELECT '2025-1', 'Primer Semestre 2025', 'semestral', '2025-03-01', '2025-07-31', 'cerrado', 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM periodos_academicos WHERE codigo = '2025-1');

INSERT INTO periodos_academicos (codigo, nombre, tipo, fecha_inicio, fecha_fin, estado, es_actual)
SELECT '2025-2', 'Segundo Semestre 2025', 'semestral', '2025-08-01', '2025-12-31', 'cerrado', 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM periodos_academicos WHERE codigo = '2025-2');

INSERT INTO periodos_academicos (codigo, nombre, tipo, fecha_inicio, fecha_fin, estado, es_actual)
SELECT '2026-1', 'Primer Semestre 2026', 'semestral', '2026-03-01', '2026-07-31', 'activo', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM periodos_academicos WHERE codigo = '2026-1');
