<?php
declare(strict_types=1);

namespace App\Services;

use Config\Database;
use PDO;

class RbacSetupService extends BaseService {
    
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function runSetup() {
        try {
            // 1. Crear Tablas si no existen (Aunque deberían existir, aseguramos)
            $this->createTables();

            // 2. Sembrar Permisos Base
            $permisos = [
                // Usuarios
                'ver_usuario' => 'Ver listado de usuarios',
                'crear_usuario' => 'Crear nuevos usuarios',
                'editar_usuario' => 'Editar información de usuarios',
                'eliminar_usuario' => 'Eliminar usuarios del sistema',
                
                // Roles
                'ver_rol' => 'Ver roles configurados',
                'crear_rol' => 'Crear nuevos roles',
                'editar_rol' => 'Editar roles y asignar permisos',
                'eliminar_rol' => 'Eliminar roles',

                // Permisos
                'ver_permiso' => 'Ver catálogo de permisos',
                'crear_permiso' => 'Registrar nuevos permisos técnicos',
                'editar_permiso' => 'Editar descripción de permisos',
                'eliminar_permiso' => 'Eliminar permisos',

                // Escritorio Global
                'ver_escritorio' => 'Acceso al Dashboard principal',
                'ver_configuracion' => 'Acceso a configuraciones del sistema',
                'ver_reportes' => 'Ver reportes globales',
                'ver_cursos' => 'Ver módulo de cursos',
                
                // Gestión Académica
                'ver_gestion' => 'Ver gestión académica (Tareas)',
                'admin_gestion' => 'Administrar tareas académicas (CRUD)',

                // Auditoría Avanzada
                'ver_auditoria' => 'Acceso al Explorador de Auditoría y Logs',

                // Moodle
                'sincronizar_moodle' => 'Ejecutar procesos de sincronización con Moodle',

                // Investigación (Tesis)
                'investigacion.ver' => 'Ver listado y detalles de tesis',
                'investigacion.crear' => 'Registrar nuevas tesis y asignaciones',
                'investigacion.editar' => 'Editar información de tesis existentes',
                'investigacion.eliminar' => 'Eliminar registros de tesis'
            ];

            $permisoIds = [];
            foreach ($permisos as $slug => $desc) {
                $id = $this->createOrGetPermiso($slug, $desc);
                $permisoIds[] = $id;
            }

            // 3. Crear Rol Super Admin
            $adminRoleId = $this->createOrGetRol('Super Admin', 'Acceso total al sistema');

            // 4. Asignar TODOS los permisos al Super Admin
            $this->assignPermisosToRol($adminRoleId, $permisoIds);

            // 5. Asignar Rol Admin al usuario ID 1 (o al actual en sesión si pudieramos)
            // Asumimos ID 1 es el admin inicial o root
            $this->assignRolToUser(1, $adminRoleId);

            return "Configuración RBAC completada exitosamente. Rol 'Super Admin' actualizado con " . count($permisoIds) . " permisos.";

        } catch (\Exception $e) {
            return "Error en configuración: " . $e->getMessage();
        }
    }

    private function createTables() {
        // Tablas pivote y catálogos
        $sql = "
        CREATE TABLE IF NOT EXISTS `roles` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nombre` varchar(50) NOT NULL,
            `descripcion` text,
            PRIMARY KEY (`id`),
            UNIQUE KEY `nombre` (`nombre`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `permisos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `slug` varchar(50) NOT NULL,
            `descripcion` text,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `rol_permisos` (
            `rol_id` int(11) NOT NULL,
            `permiso_id` int(11) NOT NULL,
            PRIMARY KEY (`rol_id`,`permiso_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `usuario_roles` (
            `usuario_id` int(11) NOT NULL,
            `rol_id` int(11) NOT NULL,
            PRIMARY KEY (`usuario_id`,`rol_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $this->db->exec($sql);
    }

    private function createOrGetPermiso($slug, $desc) {
        $stmt = $this->db->prepare("SELECT id FROM permisos WHERE slug = :slug");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) return $row['id'];

        $stmt = $this->db->prepare("INSERT INTO permisos (slug, descripcion) VALUES (:slug, :desc)");
        $stmt->execute([':slug' => $slug, ':desc' => $desc]);
        return $this->db->lastInsertId();
    }

    private function createOrGetRol($nombre, $desc) {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE nombre = :nombre");
        $stmt->execute([':nombre' => $nombre]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) return $row['id'];

        $stmt = $this->db->prepare("INSERT INTO roles (nombre, descripcion) VALUES (:nombre, :desc)");
        $stmt->execute([':nombre' => $nombre, ':desc' => $desc]);
        return $this->db->lastInsertId();
    }

    private function assignPermisosToRol($rolId, $permisoIds) {
        // Limpiar
        $stmt = $this->db->prepare("DELETE FROM rol_permisos WHERE rol_id = :rol_id");
        $stmt->execute([':rol_id' => $rolId]);

        // Insertar
        $sql = "INSERT INTO rol_permisos (rol_id, permiso_id) VALUES ";
        $values = [];
        $params = [];
        foreach ($permisoIds as $pId) {
            $values[] = "(?, ?)";
            $params[] = $rolId;
            $params[] = $pId;
        }
        $sql .= implode(", ", $values);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    private function assignRolToUser($userId, $rolId) {
        // Verificar si ya tiene el rol
        $stmt = $this->db->prepare("SELECT * FROM usuario_roles WHERE usuario_id = :uid AND rol_id = :rid");
        $stmt->execute([':uid' => $userId, ':rid' => $rolId]);
        if (!$stmt->fetch()) {
            try {
                $stmt = $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (:uid, :rid)");
                $stmt->execute([':uid' => $userId, ':rid' => $rolId]);
            } catch (\Exception $e) {
                // Si falla por FK (usuario no existe), lo ignoramos silenciosamente en este setup genérico
            }
        }
    }
}
