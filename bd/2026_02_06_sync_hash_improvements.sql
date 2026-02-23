-- ==============================================
-- MIGRACIÓN: Mejoras para Sincronización con Hashes
-- Fecha: 2026-02-06
-- Descripción: Agrega columnas para detección de cambios y soft delete
-- ==============================================

USE eduma;

-- 1. Agregar columna idnumber a raw_moodle_categorias (si no existe)
SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'raw_moodle_categorias' 
    AND column_name = 'idnumber'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE raw_moodle_categorias ADD COLUMN idnumber VARCHAR(100) AFTER path',
    'SELECT "Column idnumber already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Agregar columna data_hash a raw_moodle_categorias (si no existe)
SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'raw_moodle_categorias' 
    AND column_name = 'data_hash'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE raw_moodle_categorias MODIFY COLUMN data_hash VARCHAR(64)',
    'SELECT "Column data_hash already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Agregar columna last_sync_at a usuarios (para tracking de sync)
SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'usuarios' 
    AND column_name = 'last_sync_at'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE usuarios ADD COLUMN last_sync_at TIMESTAMP NULL AFTER data_hash',
    'SELECT "Column last_sync_at already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Agregar columna rol a curso_matriculas (para distinguir student/teacher)
SET @column_exists = (
    SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'curso_matriculas' 
    AND column_name = 'rol'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE curso_matriculas ADD COLUMN rol VARCHAR(50) DEFAULT "student" AFTER rol_moodle',
    'SELECT "Column rol already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Agregar índice para búsqueda por rol en matrículas
SET @index_exists = (
    SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'curso_matriculas' 
    AND index_name = 'idx_matriculas_rol'
);

SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_matriculas_rol ON curso_matriculas(rol)',
    'SELECT "Index idx_matriculas_rol already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Agregar índice para usuarios suspendidos
SET @index_exists = (
    SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'usuarios' 
    AND index_name = 'idx_usuarios_suspended'
);

SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_usuarios_suspended ON usuarios(suspended)',
    'SELECT "Index idx_usuarios_suspended already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Vista para dashboard de sincronización
CREATE OR REPLACE VIEW v_sync_dashboard AS
SELECT 
    'usuarios' as entidad,
    COUNT(*) as total,
    SUM(CASE WHEN suspended = 0 THEN 1 ELSE 0 END) as activos,
    SUM(CASE WHEN suspended = 1 THEN 1 ELSE 0 END) as suspendidos,
    MAX(updated_at) as ultima_actualizacion
FROM usuarios WHERE id_moodle IS NOT NULL
UNION ALL
SELECT 
    'cursos' as entidad,
    COUNT(*) as total,
    SUM(CASE WHEN visible = 1 THEN 1 ELSE 0 END) as activos,
    SUM(CASE WHEN visible = 0 THEN 1 ELSE 0 END) as suspendidos,
    MAX(updated_at) as ultima_actualizacion
FROM cursos
UNION ALL
SELECT 
    'matriculas' as entidad,
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
    SUM(CASE WHEN estado = 'suspendido' THEN 1 ELSE 0 END) as suspendidos,
    MAX(updated_at) as ultima_actualizacion
FROM curso_matriculas
UNION ALL
SELECT 
    'categorias' as entidad,
    COUNT(*) as total,
    COUNT(*) as activos,
    0 as suspendidos,
    MAX(updated_at) as ultima_actualizacion
FROM raw_moodle_categorias;

-- 8. Vista para perfiles de usuarios
CREATE OR REPLACE VIEW v_usuarios_perfiles AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.nombre,
    u.apellido,
    u.es_estudiante,
    u.es_docente,
    u.es_admin,
    u.suspended,
    CASE 
        WHEN e.id IS NOT NULL THEN 'Sí' ELSE 'No' 
    END as tiene_perfil_estudiante,
    CASE 
        WHEN d.id IS NOT NULL THEN 'Sí' ELSE 'No' 
    END as tiene_perfil_docente,
    CASE 
        WHEN a.id IS NOT NULL THEN 'Sí' ELSE 'No' 
    END as tiene_perfil_admin,
    e.legajo,
    e.carrera_principal_id,
    d.titulo_profesional,
    d.especialidad,
    a.cargo,
    a.departamento
FROM usuarios u
LEFT JOIN estudiantes e ON u.id = e.usuario_id
LEFT JOIN docentes d ON u.id = d.usuario_id
LEFT JOIN administrativos a ON u.id = a.usuario_id
WHERE u.id_moodle IS NOT NULL;

SELECT 'Migración completada exitosamente' as resultado;
