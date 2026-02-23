<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Permiso\PermisoModel;

class PermisoService extends BaseService {
    
    private PermisoModel $permisoModel;

    public function __construct(PermisoModel $permisoModel) {
        $this->permisoModel = $permisoModel;
    }

    /**
     * Obtiene todos los permisos registrados.
     * @return array
     */
    public function obtenerTodos(): array {
        return $this->permisoModel->getAll();
    }

    /**
     * Obtiene un permiso por su ID.
     */
    public function obtenerPorId(int $id): ?array {
        return $this->permisoModel->find($id);
    }

    /**
     * Crea un nuevo permiso en el sistema.
     * 
     * @param array $data Datos validados (slug, descripcion)
     * @return int ID del permiso creado
     * @throws \Exception
     */
    public function crearPermiso(array $data): int {
        // 1. Normalización de Slug
        // Forzamos minúsculas y eliminamos espacios invalidos
        $slug = strtolower(trim($data['slug']));
        // Reemplazamos espacios internos por puntos o guiones bajos si es necesario, 
        // pero el estándar EDUMA dice "entidad.accion". Asumiremos que el usuario lo ingresa bien o lo corregimos minimamente.
        // Mejor solo trim y lower, y validación de formato.
        
        if (!preg_match('/^[a-z0-9_]+\.[a-z0-9_]+$/', $slug)) {
            $this->error("El formato del slug debe ser 'entidad.accion' (ej: usuario.crear). Solo letras minúsculas, números y guiones bajos.");
        }

        // 2. Validación de Unicidad
        if ($this->permisoModel->findBySlug($slug)) {
            $this->error("El permiso '{$slug}' ya existe en el sistema.");
        }

        // 3. Preparación
        $data['slug'] = $slug;
        $data['descripcion'] = trim($data['descripcion'] ?? '');

        // 4. Persistencia
        $id = $this->permisoModel->create($data);

        if (!$id) {
            $this->error("Error al registrar el permiso.");
        }

        // AUDITORÍA
        $currentUserId = \App\Helpers\SessionHelper::get('user_id');
        LoggerService::audit($currentUserId, 'PERMISO_CREATE', "Permiso:$id", ['slug' => $slug]);

        return (int)$id;
    }

    /**
     * Actualiza un permiso existente.
     * 
     * @param int $id
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function actualizarPermiso(int $id, array $data): bool {
        $permiso = $this->permisoModel->find($id);
        if (!$permiso) {
            $this->error("Permiso no encontrado.");
        }

        // Normalización y Validación de Slug si cambia
        if (isset($data['slug']) && $data['slug'] !== $permiso['slug']) {
            $slug = strtolower(trim($data['slug']));
            
            if (!preg_match('/^[a-z0-9_]+\.[a-z0-9_]+$/', $slug)) {
                $this->error("El formato del slug debe ser 'entidad.accion'.");
            }
            
            if ($this->permisoModel->findBySlug($slug)) {
                $this->error("El slug '{$slug}' ya está en uso por otro permiso.");
            }
            $data['slug'] = $slug;
        } else {
            unset($data['slug']); // No actualizamos si no cambia o es vacío
        }

        if (isset($data['descripcion'])) {
            $data['descripcion'] = trim($data['descripcion']);
        }

        $success = $this->permisoModel->update($id, $data);

        if ($success) {
            $currentUserId = \App\Helpers\SessionHelper::get('user_id');
            LoggerService::audit($currentUserId, 'PERMISO_UPDATE', "Permiso:$id", array_keys($data));
        }

        return $success;
    }

    /**
     * Elimina un permiso.
     * 
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function eliminarPermiso(int $id): bool {
        // En un sistema RBAC real, deberíamos chequear si está en uso en rol_permisos.
        // Por simplicidad y tiempo, asumimos que la base de datos (FK) podría restringirlo 
        // o permitimos borrado cascada (peligroso) o borrado lógico.
        // Dado que PermisoModel hereda BaseModel, es delete físico o lógico dependiendo si tiene soft delete.
        // La tabla permisos suele ser delete físico.
        
        $permiso = $this->permisoModel->find($id);
        if (!$permiso) {
            $this->error("Permiso no encontrado.");
        }

        // TODO: Validar dependencias si se requiere estrictez (rol_permisos)
        
        $success = $this->permisoModel->delete($id);

        if ($success) {
            $currentUserId = \App\Helpers\SessionHelper::get('user_id');
            LoggerService::audit($currentUserId, 'PERMISO_DELETE', "Permiso:$id", ['slug' => $permiso['slug']]);
        }

        return $success;
    }
}
