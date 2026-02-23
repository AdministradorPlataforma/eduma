<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario\UsuarioModel;
use App\Helpers\PasswordValidator;

class UsuarioService extends BaseService {
    
    private UsuarioModel $usuarioModel;

    public function __construct(UsuarioModel $usuarioModel) {
        $this->usuarioModel = $usuarioModel;
    }

    private function getDb() {
        return \App\Core\Container::getInstance()->get('db');
    }

    /**
     * Crea un nuevo usuario en el sistema.
     * 
     * @param array $data Datos validados del formulario
     * @return int ID del usuario creado
     * @throws \Exception
     */
    public function crearUsuario(array $data, ?int $rolId = null): int {
        // 1. Validaciones de Negocio (Duplicidad)
        if ($this->usuarioModel->findByUsername($data['username'])) {
            $this->error("El nombre de usuario '{$data['username']}' ya está en uso.");
        }
        
        if ($this->usuarioModel->findByEmail($data['email'])) {
            $this->error("El correo electrónico '{$data['email']}' ya está registrado.");
        }

        // 2. Preparación de Datos
        if (!empty($data['password'])) {
            $data['password'] = PasswordValidator::hash($data['password']);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['activo'] = $data['activo'] ?? 1;
        
        // 3. Persistencia
        $id = $this->usuarioModel->create($data);
        
        if (!$id) {
            $this->error("Error al registrar el usuario en la base de datos.");
        }

        // 4. Asignación de Rol
        if ($rolId) {
            $this->assignRol($id, $rolId);
        }

        // AUDITORÍA
        $currentUserId = \App\Helpers\SessionHelper::get('user_id');
        LoggerService::audit($currentUserId, 'USER_CREATE', "Usuario:$id", ['username' => $data['username'], 'rol_id' => $rolId]);

        return (int)$id;
    }

    public function actualizarUsuario(int $id, array $data, ?int $rolId = null): bool {
        $usuario = $this->usuarioModel->find($id);
        if (!$usuario) {
            $this->error("Usuario no encontrado.");
        }

        if (isset($data['username']) && $data['username'] !== $usuario['username']) {
            if ($this->usuarioModel->findByUsername($data['username'])) {
                $this->error("El nombre de usuario ya existe.");
            }
        }

        if (!empty($data['password'])) {
            $data['password'] = PasswordValidator::hash($data['password']);
        } else {
            unset($data['password']);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        $success = $this->usuarioModel->update($id, $data);
        
        // Actualizar Rol
        if ($rolId !== null) {
            $this->assignRol($id, $rolId);
        }
        
        if ($success) {
            // AUDITORÍA
            $currentUserId = \App\Helpers\SessionHelper::get('user_id');
            // Logueamos solo las llaves cambiadas para no saturar
            $changedKeys = array_keys($data);
            if ($rolId) $changedKeys[] = 'rol_id';
            LoggerService::audit($currentUserId, 'USER_UPDATE', "Usuario:$id", ['changed_fields' => $changedKeys]);
        }

        return $success;
    }

    public function eliminarUsuario(int $id): bool {
        // Limpiar roles antes (aunque cascade debería encargarse)
        $this->assignRol($id, null); // Null borra
        
        $success = $this->usuarioModel->delete($id);
        
        if ($success) {
            $currentUserId = \App\Helpers\SessionHelper::get('user_id');
            LoggerService::audit($currentUserId, 'USER_DELETE', "Usuario:$id");
        }
        
        return $success;
    }

    private function assignRol(int $userId, ?int $rolId) {
        $db = $this->getDb();
        
        // Limpiar roles previos (Asumimos 1 rol por usuario por simplicidad de UI, aunque la tabla soporte N)
        $stmt = $db->prepare("DELETE FROM usuario_roles WHERE usuario_id = :uid");
        $stmt->execute([':uid' => $userId]);

        if ($rolId) {
            $stmt = $db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (:uid, :rid)");
            $stmt->execute([':uid' => $userId, ':rid' => $rolId]);
        }
    }
    
    public function getRolId(int $userId): ?int {
        $db = $this->getDb();
        $stmt = $db->prepare("SELECT rol_id FROM usuario_roles WHERE usuario_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $res ? (int)$res['rol_id'] : null;
    }
}
