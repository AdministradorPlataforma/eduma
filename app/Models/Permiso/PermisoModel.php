<?php
declare(strict_types=1);

namespace App\Models\Permiso;

use App\Models\BaseModel;

class PermisoModel extends BaseModel {
    protected string $table = 'permisos';
    protected array $allowedFields = [
        'slug', 'descripcion'
    ];
    protected bool $useTimestamps = false;
    
    /**
     * Obtiene todos los permisos ordenados por slug.
     * @return array
     */
    public function getAll(): array {
        return $this->builder
            ->select(['*'])
            ->orderBy('slug', 'ASC')
            ->get();
    }

    /**
     * Busca un permiso por su Slug.
     * @param string $slug
     * @return array|null
     */
    public function findBySlug(string $slug) {
        return $this->builder
            ->where('slug', '=', $slug)
            ->first();
    }
}
