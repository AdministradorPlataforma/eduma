-- ==============================================
-- MÓDULO DE SEGUIMIENTO Y GESTIÓN ACADÉMICA
-- Tabla de Tareas Programadas por Facultad
-- ==============================================

CREATE TABLE gestion_control_seguimiento (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    facultad_id INT UNSIGNED NOT NULL,
    producto_documento VARCHAR(255) NOT NULL COMMENT 'Nombre del entregable o documento',
    destino VARCHAR(255) NOT NULL COMMENT 'A quién va dirigido',
    dia_plazo_mes TINYINT UNSIGNED NOT NULL COMMENT 'Día del mes para el vencimiento (1-31)',
    frecuencia ENUM('mensual', 'semestral') DEFAULT 'mensual',
    responsable_cargo VARCHAR(100) NOT NULL COMMENT 'Cargo que debe cumplir la terea (ej: Decano)',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (facultad_id) REFERENCES facultades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==============================================
-- Tabla de Evidencias (Cumplimiento)
-- ==============================================

CREATE TABLE gestion_evidencias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL COMMENT 'Usuario que subió la evidencia',
    
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('CUMPLE', 'NO CUMPLE') DEFAULT 'CUMPLE',
    url_adjunto VARCHAR(255) NOT NULL COMMENT 'Ruta al archivo probatorio',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tarea_id) REFERENCES gestion_control_seguimiento(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
