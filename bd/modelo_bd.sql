-- ==============================================
-- EDUMA FINAL: Arquitectura Híbrida (Sync Moodle + Gestión Local)
-- Autor: Tu IA de Confianza (Gemini)
-- Fecha: 2026-02-01
-- ==============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-03:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. PREPARACIÓN DEL ENTORNO
DROP DATABASE IF EXISTS eduma;
CREATE DATABASE IF NOT EXISTS eduma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eduma;

-- =======================================================
-- 2. TABLAS DE INFRAESTRUCTURA (Espejo de Moodle)
-- =======================================================

CREATE TABLE raw_moodle_categorias (
    id INT UNSIGNED PRIMARY KEY, 
    parent_id INT UNSIGNED DEFAULT 0,
    name VARCHAR(255) NOT NULL,
    idnumber VARCHAR(100),
    depth INT UNSIGNED DEFAULT 0,
    path VARCHAR(255),
    data_hash VARCHAR(64),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_parent (parent_id),
    INDEX idx_depth (depth)
) ENGINE=InnoDB;

-- =======================================================
-- 3. VISTA DE ESTRUCTURA ACADÉMICA
-- =======================================================
-- Aplana el árbol de categorías: Facultad -> Carrera -> Semestre

CREATE OR REPLACE VIEW vista_estructura_academica AS
SELECT 
    cat_semestre.id AS moodle_cat_id,
    cat_semestre.name AS nombre_semestre,
    cat_anio.id AS anio_moodle_id,
    cat_anio.name AS nombre_anio,
    cat_carrera.id AS carrera_moodle_id,
    cat_carrera.name AS nombre_carrera,
    cat_facultad.id AS facultad_moodle_id,
    cat_facultad.name AS nombre_facultad,
    cat_modalidad.name AS modalidad,
    cat_periodo.name AS periodo_academico
FROM raw_moodle_categorias AS cat_semestre
LEFT JOIN raw_moodle_categorias AS cat_anio ON cat_semestre.parent_id = cat_anio.id
LEFT JOIN raw_moodle_categorias AS cat_carrera ON cat_anio.parent_id = cat_carrera.id
LEFT JOIN raw_moodle_categorias AS cat_facultad ON cat_carrera.parent_id = cat_facultad.id
LEFT JOIN raw_moodle_categorias AS cat_modalidad ON cat_facultad.parent_id = cat_modalidad.id
LEFT JOIN raw_moodle_categorias AS cat_periodo ON cat_modalidad.parent_id = cat_periodo.id
WHERE cat_semestre.depth = 6;

-- =======================================================
-- 4. ESTRUCTURA LOCAL (Facultades y Carreras)
-- =======================================================

CREATE TABLE facultades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_moodle_categoria INT UNSIGNED UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    codigo_corto VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE carreras (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_moodle_categoria INT UNSIGNED UNIQUE,
    facultad_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    duracion_anios INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (facultad_id) REFERENCES facultades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =======================================================
-- 5. USUARIOS (Núcleo de Identidad)
-- =======================================================

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- id_moodle ahora permite NULL para usuarios 100% locales (Administrativos)
    id_moodle INT UNSIGNED UNIQUE DEFAULT NULL, 
    
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) DEFAULT NULL, 
    nombre VARCHAR(100),
    apellido VARCHAR(100),
    
    -- NUEVO: Banderas de Roles Múltiples (No excluyentes)
    es_estudiante TINYINT(1) DEFAULT 0,
    es_docente TINYINT(1) DEFAULT 0,
    es_admin TINYINT(1) DEFAULT 0, -- Para acceso al backend local
    
    auth_method VARCHAR(20) DEFAULT 'manual',
    suspended TINYINT(1) DEFAULT 0,
    data_hash VARCHAR(64),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- =======================================================
-- 6. PERFILES EXTENDIDOS (Separación de Datos)
-- =======================================================

-- Perfil para Alumnos (Datos académicos)
CREATE TABLE estudiantes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL UNIQUE, -- FK a usuarios
    
    legajo VARCHAR(50) UNIQUE, -- Matrícula universitaria local
    carrera_principal_id INT UNSIGNED,
    anio_ingreso YEAR,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (carrera_principal_id) REFERENCES carreras(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Perfil para Docentes (Datos laborales)
CREATE TABLE docentes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL UNIQUE, -- FK a usuarios
    
    titulo_profesional VARCHAR(150),
    especialidad VARCHAR(150),
    tipo_contrato VARCHAR(50),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Perfil para Administrativos (Staff local)
CREATE TABLE administrativos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL UNIQUE, -- FK a usuarios
    
    cargo VARCHAR(100), -- "Secretario", "Soporte Técnico"
    departamento VARCHAR(100),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =======================================================
-- 7. CURSOS
-- =======================================================

CREATE TABLE cursos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_moodle INT UNSIGNED UNIQUE NOT NULL,
    id_categoria_moodle INT UNSIGNED NOT NULL,
    
    carrera_id INT UNSIGNED DEFAULT NULL,
    
    fullname VARCHAR(255) NOT NULL,
    shortname VARCHAR(100) NOT NULL,
    start_date DATETIME,
    visible TINYINT(1) DEFAULT 1,
    
    -- Metadatos de búsqueda
    semestre_texto VARCHAR(100),
    anio_texto VARCHAR(100),
    
    data_hash VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (carrera_id) REFERENCES carreras(id) ON DELETE SET NULL,
    INDEX idx_category (id_categoria_moodle)
) ENGINE=InnoDB;

-- =======================================================
-- 8. MATRÍCULAS (Vinculación Usuario-Curso)
-- =======================================================
-- Esta tabla alimenta las banderas es_estudiante/es_docente

CREATE TABLE curso_matriculas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    curso_id INT UNSIGNED NOT NULL,
    
    -- Rol Moodle: 'student' o 'editingteacher'
    rol_moodle VARCHAR(50) NOT NULL, 
    
    fecha_inscripcion DATETIME,
    estado ENUM('activo', 'suspendido') DEFAULT 'activo',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Clave única compuesta: Usuario + Curso + Rol
    UNIQUE KEY uk_matricula (usuario_id, curso_id, rol_moodle),
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =======================================================
-- 9. CALIFICACIONES
-- =======================================================

CREATE TABLE calificaciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricula_id BIGINT UNSIGNED NOT NULL,
    
    item_nombre VARCHAR(255) DEFAULT 'Nota Final',
    id_moodle_item INT UNSIGNED DEFAULT NULL,
    
    calificacion_final DECIMAL(10,5),
    calificacion_maxima DECIMAL(10,5) DEFAULT 100.00,
    
    feedback TEXT,
    fecha_modificacion DATETIME, -- Moodle field
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_calificacion_item (matricula_id, id_moodle_item),
    FOREIGN KEY (matricula_id) REFERENCES curso_matriculas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =======================================================
-- 10. LOGS
-- =======================================================

CREATE TABLE sync_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(36),
    entidad VARCHAR(50),
    mensaje TEXT,
    estado ENUM('info', 'warning', 'error', 'success'),
    registros_afectados INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =======================================================
-- 11. SISTEMA DE SEGURIDAD RBAC (Roles y Permisos Locales)
-- =======================================================

-- Catálogo de Roles Disponibles (Ej: SuperAdmin, Secretaria, Soporte)
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE, -- Ej: 'Administrador', 'Secretaría'
    descripcion VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Catálogo de Permisos (Acciones específicas del sistema)
CREATE TABLE permisos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL, -- Ej: 'Crear Usuario'
    slug VARCHAR(100) NOT NULL UNIQUE, -- Clave para código: 'usuario.crear'
    descripcion VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla Pivote: Qué permisos tiene cada Rol
CREATE TABLE rol_permisos (
    rol_id INT UNSIGNED NOT NULL,
    permiso_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Solo fecha de asignación
    PRIMARY KEY (rol_id, permiso_id),
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla Pivote: Qué roles tiene cada Usuario LOCALMENTE
-- Un usuario puede tener roles académicos (Moodle) y roles administrativos (Local) a la vez.
CREATE TABLE usuario_roles (
    usuario_id INT UNSIGNED NOT NULL,
    rol_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Solo fecha de asignación
    PRIMARY KEY (usuario_id, rol_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 12. TAREA PENDIENTE: TABLA QUEUE_JOBS (Faltaba en SQL anterior)
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

-- =======================================================
-- SEEDER INICIAL (Datos obligatorios para empezar)
-- =======================================================

-- 1. Crear el Rol Maestro
INSERT INTO roles (id, nombre, descripcion) VALUES (1, 'Super Admin', 'Acceso total al sistema');

-- 2. Asignar el Rol al usuario ID 1 (El admin que creaste antes)
-- Asegúrate de que el usuario con ID 1 exista antes de correr esto
INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (1, 1);

-- 3. Crear Permisos Básicos (Ejemplos)
INSERT INTO permisos (nombre, slug, descripcion) VALUES 
('Ver Usuarios', 'usuario.ver', 'Puede ver el listado de usuarios'),
('Crear Usuarios', 'usuario.crear', 'Puede registrar nuevos usuarios'),
('Editar Usuarios', 'usuario.editar', 'Puede modificar usuarios existentes'),
('Eliminar Usuarios', 'usuario.eliminar', 'Puede borrar usuarios'),
('Sincronizar Moodle', 'moodle.sync', 'Puede ejecutar procesos de sincronización');


--  COHORTES (Grupos Globales de Moodle)
CREATE TABLE IF NOT EXISTS cohortes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_moodle INT UNSIGNED UNIQUE NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    idnumber VARCHAR(100),
    descripcion TEXT,
    visible TINYINT(1) DEFAULT 1,
    data_hash VARCHAR(64), -- Para detectar cambios
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
-- AUDITORÍA Y SEGURIDAD (LoggerService)
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL, -- Nulo si es acción de sistema o login fallido
    action VARCHAR(50) NOT NULL COMMENT 'Código de acción ej: MOODLE_SYNC',
    resource VARCHAR(100) NULL COMMENT 'Recurso afectado ej: User:15',
    details JSON NULL COMMENT 'Metadatos en formato JSON',
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- 4. Darle todos los permisos al Super Admin
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT 1, id FROM permisos;

-- RESTAURAR CHEQUEOS
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;