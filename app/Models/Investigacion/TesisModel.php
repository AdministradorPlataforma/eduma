<?php
declare(strict_types=1);

namespace App\Models\Investigacion;

use App\Models\BaseModel;
use PDO;

class TesisModel extends BaseModel {
    protected string $table = 'tesis';
    protected array $allowedFields = [
        'titulo', 'descripcion', 'director_id', 
        'archivo_path', 'archivo_tesis_path', 'estado', 'codigo'
    ];
    protected bool $useSoftDeletes = true;

    /**
     * Cuenta todas las tesis.
     */
    public function countAll(): int {
        return (int) $this->builder->count();
    }
}
