-- Migración para optimizar consultas de auditoría
-- Fecha: 2026-02-09

ALTER TABLE audit_logs ADD INDEX idx_action_created (action, created_at);
ALTER TABLE audit_logs ADD INDEX idx_user_created (user_id, created_at);
