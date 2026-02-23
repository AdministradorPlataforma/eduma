-- 1. Tabla para Rate Limiting persistente (Compatible MySQL 5.6+)
-- Usamos DATETIME para evitar el error 1293 de múltiples TIMESTAMPs automáticos
CREATE TABLE IF NOT EXISTS rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(150) NOT NULL COMMENT 'Identificador único (IP o IP+Usuario)',
    attempts INT DEFAULT 0,
    last_attempt DATETIME DEFAULT NULL,
    locked_until DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    UNIQUE KEY uk_rate_limit (`key`)
) ENGINE=InnoDB;

-- 2. Nuevos Permisos Administrativos
INSERT IGNORE INTO permisos (nombre, slug, descripcion) VALUES 
('Ver Sistema', 'sistema.ver', 'Acceso al módulo de estado del sistema y mantenimiento'),
('Gestionar Papelera', 'papelera.gestionar', 'Puede ver y restaurar items de la papelera de reciclaje'),
('Configurar RBAC', 'rbac.configurar', 'Permite ejecutar configuraciones avanzadas de seguridad');

-- 3. Asignar permisos al Super Admin (ID 1)
INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
SELECT 1, id FROM permisos WHERE slug IN ('sistema.ver', 'papelera.gestionar', 'rbac.configurar');
