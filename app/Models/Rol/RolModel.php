<?php
declare(strict_types=1);

namespace App\Models\Rol;

use App\Models\BaseModel;
use PDO;

class RolModel extends BaseModel {
    protected string $table = 'roles';
    protected array $allowedFields = [
        'nombre', 'descripcion'
    ];
    protected bool $useTimestamps = false;
    
    /**
     * Obtiene todos los roles.
     * @return array
     */
    public function getAll(): array {
        return $this->builder
            ->select(['*'])
            ->orderBy('id', 'ASC')
            ->get();
    }

    /**
     * Busca un rol por nombre.
     * @param string $nombre
     * @return array|null
     */
    public function findByNombre(string $nombre) {
        return $this->builder
            ->where('nombre', '=', $nombre)
            ->first();
    }

    /**
     * Obtiene los IDs de los permisos asignados a un rol.
     * @param int $rolId
     * @return array Array de IDs de permisos
     */
    public function getPermisosIds(int $rolId): array {
        $stmt = $this->db->prepare("SELECT permiso_id FROM rol_permisos WHERE rol_id = :rol_id");
        $stmt->bindParam(':rol_id', $rolId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Asigna permisos a un rol (limpia anteriores y asigna nuevos).
     * @param int $rolId
     * @param array $permisoIds
     * @return void
     */
    public function syncPermisos(int $rolId, array $permisoIds): void {
        try {
            $this->db->beginTransaction();

            // 1. Eliminar asignaciones previas
            $stmt = $this->db->prepare("DELETE FROM rol_permisos WHERE rol_id = :rol_id");
            $stmt->bindParam(':rol_id', $rolId, PDO::PARAM_INT);
            $stmt->execute();

            // 2. Insertar nuevas
            if (!empty($permisoIds)) {
                $sql = "INSERT INTO rol_permisos (rol_id, permiso_id) VALUES ";
                $values = [];
                $params = [];
                
                foreach ($permisoIds as $permisoId) {
                    $values[] = "(?, ?)";
                    $params[] = $rolId;
                    $params[] = $permisoId;
                }
                
                $sql .= implode(", ", $values);
                $stmtInsert = $this->db->prepare($sql);
                $stmtInsert->execute($params);
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
