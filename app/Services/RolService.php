<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Rol\RolModel;

class RolService extends BaseService {
    
    private RolModel $rolModel;

    public function __construct(RolModel $rolModel) {
        $this->rolModel = $rolModel;
    }

    public function obtenerTodos(): array {
        return $this->rolModel->getAll();
    }

    public function obtenerPorId(int $id): ?array {
        return $this->rolModel->find($id);
    }

    public function crearRol(array $data, array $permisoIds = []): int {
        // 1. Validar Nombre Único
        if ($this->rolModel->findByNombre($data['nombre'])) {
            $this->error("El nombre del rol '{$data['nombre']}' ya existe.");
        }

        // 2. Crear Rol
        $rolId = $this->rolModel->create([
            'nombre' => trim($data['nombre']),
            'descripcion' => trim($data['descripcion'] ?? '')
        ]);

        if (!$rolId) {
            $this->error("Error al crear el rol.");
        }

        // 3. Asignar Permisos
        if (!empty($permisoIds)) {
            $this->rolModel->syncPermisos((int)$rolId, $permisoIds);
        }

        // Auditoría
        $currentUserId = \App\Helpers\SessionHelper::get('user_id');
        LoggerService::audit($currentUserId, 'ROL_CREATE', "Rol:$rolId", ['nombre' => $data['nombre']]);

        return (int)$rolId;
    }

    public function actualizarRol(int $id, array $data, array $permisoIds = []): bool {
        $rol = $this->rolModel->find($id);
        if (!$rol) {
            $this->error("Rol no encontrado.");
        }

        if (isset($data['nombre']) && $data['nombre'] !== $rol['nombre']) {
            if ($this->rolModel->findByNombre($data['nombre'])) {
                $this->error("El nombre del rol '{$data['nombre']}' ya existe.");
            }
        }

        // Actualizar Info Básica
        $updateData = [
            'nombre' => trim($data['nombre']),
            'descripcion' => trim($data['descripcion'] ?? '')
        ];
        
        $success = $this->rolModel->update($id, $updateData);

        // Sincronizar Permisos (Siempre se ejecuta la sinc se hayan cambiado o no, para asegurar estado)
        $this->rolModel->syncPermisos($id, $permisoIds);

        if ($success) {
            $currentUserId = \App\Helpers\SessionHelper::get('user_id');
            LoggerService::audit($currentUserId, 'ROL_UPDATE', "Rol:$id");
        }

        return true; // Asumimos éxito si no hubo excepción en sync
    }

    public function eliminarRol(int $id): bool {
        // Validación: No borrar roles críticos si existiera esa lógica (ej ID 1 Administrador)
        if ($id === 1) {
            $this->error("No puede eliminar el rol de Administrador principal.");
        }

        // Primero synced permisos vacíos para limpiar pivote (aunque la FK debería tener CASCADE, lo hacemos manual por rolModel logic)
        $this->rolModel->syncPermisos($id, []);
        
        $success = $this->rolModel->delete($id);

        if ($success) {
            $currentUserId = \App\Helpers\SessionHelper::get('user_id');
            LoggerService::audit($currentUserId, 'ROL_DELETE', "Rol:$id");
        }

        return $success;
    }

    public function obtenerPermisosDeRol(int $rolId): array {
        return $this->rolModel->getPermisosIds($rolId);
    }
}
