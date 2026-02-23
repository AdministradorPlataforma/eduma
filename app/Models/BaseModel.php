<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database\QueryBuilder;
use Config\Database;
use PDO;

abstract class BaseModel {
    protected PDO $db;
    protected QueryBuilder $builder;
    
    // Propiedades que deben definir los hijos
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $allowedFields = [];
    protected bool $useTimestamps = true;
    protected string $createdField = 'created_at';
    protected string $updatedField = 'updated_at';

    // Soft Delete Properties
    protected bool $useSoftDeletes = false;
    protected string $deletedField = 'deleted_at';

    public function __construct(PDO $db) {
        $this->db = $db;

        // Inicializar QueryBuilder
        $this->builder = new QueryBuilder($this->db, $this->table);
    }

    /**
     * Retorna todos los registros (CUIDADO: Usar paginate() preferiblemente).
     */
    public function findAll(): array {
        if ($this->useSoftDeletes && !$this->tempWithDeleted) {
            $this->builder->where($this->deletedField, 'IS', null);
        }
        if ($this->tempOnlyDeleted) {
            $this->builder->where($this->deletedField, 'IS NOT', null);
        }
        $result = $this->builder->select(['*'])->get();
        $this->resetSoftDeleteFlags();
        return $result;
    }

    /**
     * Retorna registros paginados.
     * @param int $perPage Cantidad de registros por página
     * @param int $page Página actual
     * @return array Estructura ['data' => [], 'meta' => [...]]
     */
    public function paginate(int $perPage = 15, int $page = 1): array {
        // 1. Contar total de registros
        // Clonamos el builder porque count() y get() resetean el estado, 
        // pero necesitamos los WHERES para el count.
        // Nota: QueryBuilder actual resetea al ejecutar. 
        // Idealmente deberíamos poder clonar o obtener el count sin resetear todo.
        // Dado que QueryBuilder::count() resetea, necesitamos reconstruir o modificar QueryBuilder para no resetear en count
        // O hacerlo manualmente.
        
        // Hack temporal: Asumimos que el QueryBuilder actual no tiene wheres complejos pendientes si llamamos directo a paginate
        // Si se llama $model->where(...)->paginate(), esto fallaría con la implementación actual de QueryBuilder::reset().
        // Por ahora, implementaremos una lógica básica que cuente todo si no hay filtros, 
        // IMPORTANTE: Para que esto funcione bien con filtros ($model->where()->paginate()), 
        // tendríamos que modificar QueryBuilder. 
        
        // Vamos a usar una consulta directa de count por ahora sobre la tabla.
        
        if ($this->useSoftDeletes && !$this->tempWithDeleted) {
            $this->builder->where($this->deletedField, 'IS', null);
        }
        if ($this->tempOnlyDeleted) {
            $this->builder->where($this->deletedField, 'IS NOT', null);
        }
        $total = $this->builder->count(); // Esto resetea el builder

        // 2. Calcular Offset
        $offset = ($page - 1) * $perPage;

        // 3. Get Data (Ojo: count resetea el builder, así que hay que re-aplicar condiciones 
        // pero como BaseQueryBuilder resetea, asumimos uso básico por ahora o refactorizamos QueryBuilder luego).
        // En esta fase 1, implementamos la paginación básica.
        
        if ($this->useSoftDeletes && !$this->tempWithDeleted) {
            $this->builder->where($this->deletedField, 'IS', null);
        }
        if ($this->tempOnlyDeleted) {
            $this->builder->where($this->deletedField, 'IS NOT', null);
        }
        $data = $this->builder
                     ->select(['*'])
                     ->limit($perPage)
                     ->offset($offset)
                     ->get();

        $this->resetSoftDeleteFlags();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage)
            ]
        ];
    }

    /**
     * Busca un registro por su clave primaria.
     */
    public function find($id) {
        if ($this->useSoftDeletes && !$this->tempWithDeleted) {
            $this->builder->where($this->deletedField, 'IS', null);
        }
        if ($this->tempOnlyDeleted) {
            $this->builder->where($this->deletedField, 'IS NOT', null);
        }
        $result = $this->builder
                    ->select(['*'])
                    ->where($this->primaryKey, '=', $id)
                    ->first();

        $this->resetSoftDeleteFlags();
        return $result;
    }

    protected function resetSoftDeleteFlags(): void {
        $this->tempWithDeleted = false;
        $this->tempOnlyDeleted = false;
    }

    /**
     * Busca el primer registro que coincida con la condición.
     */
    public function where(string $column, $operator, $value = null): self {
        $this->builder->where($column, $operator, $value);
        return $this;
    }

    /**
     * Ejecuta la consulta construida y devuelve un solo resultado.
     */
    public function first() {
        return $this->builder->first();
    }

    /**
     * Ejecuta la consulta construida y devuelve todos los resultados.
     */
    public function get(): array {
        return $this->builder->get();
    }

    /**
     * Inserta un nuevo registro.
     * Filtra los campos por allowedFields y añade timestamps automáticamente.
     */
    public function create(array $data): int {
        $data = $this->filterData($data);

        if ($this->useTimestamps) {
            $now = date('Y-m-d H:i:s');
            $data[$this->createdField] = $now;
            $data[$this->updatedField] = $now;
        }

        return $this->builder->insert($data);
    }

    /**
     * Actualiza un registro por su ID.
     */
    public function update($id, array $data): bool {
        $data = $this->filterData($data);

        if ($this->useTimestamps) {
            $data[$this->updatedField] = date('Y-m-d H:i:s');
        }

        return $this->builder
                    ->where($this->primaryKey, '=', $id)
                    ->update($data);
    }

    /**
     * Elimina un registro por su ID.
     */
    public function delete($id, bool $purge = false): bool {
        if ($this->useSoftDeletes && !$purge) {
            return $this->builder
                        ->where($this->primaryKey, '=', $id)
                        ->update([$this->deletedField => date('Y-m-d H:i:s')]);
        }

        return $this->builder
                    ->where($this->primaryKey, '=', $id)
                    ->delete();
    }

    /**
     * Restaura un registro eliminado lógicamente.
     */
    public function restore($id): bool {
        if (!$this->useSoftDeletes) return false;

        return $this->builder
                    ->where($this->primaryKey, '=', $id)
                    ->update([$this->deletedField => null]);
    }

    /**
     * Incluye registros eliminados en la próxima consulta.
     */
    public function withDeleted(): self {
        // En nuestro Builder actual, no tenemos un flag global de "ignorar deleted_at".
        // Pero como BaseQueryBuilder resetea al ejecutar, esto es tricky.
        // Por ahora lo dejaremos como un flag interno que el modelo verifique.
        $this->tempWithDeleted = true;
        return $this;
    }

    /**
     * Solo registros eliminados.
     */
    public function onlyDeleted(): self {
        $this->tempOnlyDeleted = true;
        return $this;
    }

    protected bool $tempWithDeleted = false;
    protected bool $tempOnlyDeleted = false;

    /**
     * Filtra los datos de entrada para que solo pasen los allowedFields.
     */
    protected function filterData(array $data): array {
        if (empty($this->allowedFields)) {
            return $data; // Si no se define nada, pasamos todo (¡Cuidado!)
        }
        
        return array_filter(
            $data,
            fn($key) => in_array($key, $this->allowedFields),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Acceso directo al builder para consultas complejas
     */
    public function builder(): QueryBuilder {
        return $this->builder;
    }
    
    /**
     * Acceso directo al PDO para cosas muy específicas
     */
    public function db(): PDO {
        return $this->db;
    }
}
