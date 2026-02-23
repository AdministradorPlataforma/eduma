<?php
declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use Exception;

/**
 * QueryBuilder v2.0 — EDUMA
 * 
 * Mejoras sobre v1.0:
 * - count() ya NO resetea el builder → permite encadenar count() + get()
 * - Soporte para clone() → permite reutilizar condiciones
 * - Nuevos métodos: whereIn(), whereNotIn(), whereBetween(), whereRaw()
 * - havingRaw() para queries con GROUP BY
 * - Método toSql() para debugging
 * 
 * @version 2.0
 * @date 2026-02-23
 */
class QueryBuilder {
    protected PDO $pdo;
    protected string $table;
    protected array $bindings = [];
    protected array $wheres = [];
    protected array $selects = ['*'];
    protected array $joins = [];
    protected ?string $groupBy = null;
    protected ?string $having = null;
    protected ?string $orderBy = null;
    protected ?string $limit = null;
    protected ?string $offset = null;

    // Contador global para generar nombres de parámetros únicos (evita colisiones en whereIn)
    protected int $paramCounter = 0;

    public function __construct(PDO $pdo, string $table = '') {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function setTable(string $table): self {
        $this->table = $table;
        return $this;
    }

    public function select(array $columns = ['*']): self {
        $this->selects = $columns;
        return $this;
    }

    // =========================================================
    // JOIN Methods
    // =========================================================

    public function join(string $table, string $onClause, string $type = 'INNER'): self {
        $this->joins[] = strtoupper($type) . " JOIN $table ON $onClause";
        return $this;
    }
    
    public function leftJoin(string $table, string $onClause): self {
        return $this->join($table, $onClause, 'LEFT');
    }

    public function rightJoin(string $table, string $onClause): self {
        return $this->join($table, $onClause, 'RIGHT');
    }

    // =========================================================
    // WHERE Methods
    // =========================================================

    public function where(string $column, $operator, $value = null): self {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $bindingKey = str_replace('.', '_', $column) . '_' . count($this->wheres);

        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'bindingKey' => $bindingKey 
        ];

        return $this;
    }

    public function orWhere(string $column, $operator, $value = null): self {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        
        $bindingKey = str_replace('.', '_', $column) . '_' . count($this->wheres);

        $this->wheres[] = [
            'type' => 'OR',
            'kind' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'bindingKey' => $bindingKey 
        ];

        return $this;
    }

    /**
     * WHERE column IN (val1, val2, ...)
     * 
     * @param string $column Nombre de la columna
     * @param array $values Lista de valores
     * @return self
     */
    public function whereIn(string $column, array $values): self {
        if (empty($values)) {
            // WHERE 1 = 0 (siempre falso, no retorna resultados)
            $this->wheres[] = [
                'type' => 'AND',
                'kind' => 'raw',
                'sql' => '1 = 0',
                'bindings' => []
            ];
            return $this;
        }

        $placeholders = [];
        $bindings = [];
        foreach ($values as $val) {
            $key = ':win_' . (++$this->paramCounter);
            $placeholders[] = $key;
            $bindings[$key] = $val;
        }

        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'in',
            'column' => $column,
            'placeholders' => $placeholders,
            'bindings' => $bindings,
            'not' => false
        ];

        return $this;
    }

    /**
     * WHERE column NOT IN (val1, val2, ...)
     */
    public function whereNotIn(string $column, array $values): self {
        if (empty($values)) {
            // NOT IN vacío = todos los registros, no agregar condición
            return $this;
        }

        $placeholders = [];
        $bindings = [];
        foreach ($values as $val) {
            $key = ':wnin_' . (++$this->paramCounter);
            $placeholders[] = $key;
            $bindings[$key] = $val;
        }

        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'in',
            'column' => $column,
            'placeholders' => $placeholders,
            'bindings' => $bindings,
            'not' => true
        ];

        return $this;
    }

    /**
     * WHERE column BETWEEN min AND max
     */
    public function whereBetween(string $column, $min, $max): self {
        $keyMin = ':wbtwn_min_' . (++$this->paramCounter);
        $keyMax = ':wbtwn_max_' . (++$this->paramCounter);

        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'between',
            'column' => $column,
            'keyMin' => $keyMin,
            'keyMax' => $keyMax,
            'min' => $min,
            'max' => $max
        ];

        return $this;
    }

    /**
     * WHERE (raw SQL expression)
     * CUIDADO: El SQL no se escapa. No pasar input de usuario directo.
     * 
     * @param string $sql Expresión SQL cruda
     * @param array $bindings Parámetros a bind (ej: [':param' => value])
     */
    public function whereRaw(string $sql, array $bindings = []): self {
        $this->wheres[] = [
            'type' => 'AND',
            'kind' => 'raw',
            'sql' => $sql,
            'bindings' => $bindings
        ];

        return $this;
    }

    // =========================================================
    // GROUP BY / HAVING / ORDER BY / LIMIT / OFFSET
    // =========================================================
    
    public function groupBy(string $column): self {
        $this->groupBy = "GROUP BY $column";
        return $this;
    }

    /**
     * HAVING raw SQL (para usar con GROUP BY)
     */
    public function havingRaw(string $sql): self {
        $this->having = "HAVING $sql";
        return $this;
    }
    
    public function orderBy(string $column, string $direction = 'ASC'): self {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }
        $this->orderBy = "ORDER BY $column $direction";
        return $this;
    }

    public function limit(int $limit): self {
        $this->limit = "LIMIT $limit";
        return $this;
    }
    
    public function offset(int $offset): self {
        $this->offset = "OFFSET $offset";
        return $this;
    }

    // =========================================================
    // Execution Methods (SELECT)
    // =========================================================

    /**
     * Ejecuta la consulta y devuelve todos los resultados.
     * RESETA el builder después de ejecutar.
     */
    public function get(): array {
        $query = $this->compileSelect();
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($this->bindings);
        $this->reset();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Devuelve el primer resultado (LIMIT 1).
     * RESETA el builder después de ejecutar.
     */
    public function first() {
        $this->limit(1);
        $result = $this->get();
        return $result[0] ?? null;
    }

    /**
     * Cuenta los registros que coinciden con las condiciones actuales.
     * ⚡ v2.0: NO RESETA el builder — permite encadenar count() + get().
     * 
     * @return int Total de registros
     */
    public function count(): int {
        // Guardar estado actual (no destructivo)
        $savedSelects = $this->selects;
        $savedLimit = $this->limit;
        $savedOffset = $this->offset;
        $savedOrderBy = $this->orderBy;

        // Compilar para COUNT sin limit/offset/orderby
        $this->selects = ['COUNT(*) as total'];
        $this->limit = null;
        $this->offset = null;
        $this->orderBy = null;

        $query = $this->compileSelect();
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($this->bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // ✅ Restaurar estado (NO resetear)
        $this->selects = $savedSelects;
        $this->limit = $savedLimit;
        $this->offset = $savedOffset;
        $this->orderBy = $savedOrderBy;

        return (int)($result['total'] ?? 0);
    }

    /**
     * Genera un clon del builder actual.
     * Útil para cuando necesitas reutilizar condiciones WHERE
     * en dos queries diferentes (ej: count + get con paginación).
     * 
     * @return self Nueva instancia con el mismo estado
     */
    public function cloneBuilder(): self {
        return clone $this;
    }

    // =========================================================
    // SQL Compilation
    // =========================================================

    protected function compileSelect(): string {
        $sql = "SELECT " . implode(', ', $this->selects) . " FROM " . $this->table;
        
        if (!empty($this->joins)) {
            $sql .= " " . implode(' ', $this->joins);
        }

        if (!empty($this->wheres)) {
            $sql .= " " . $this->compileWheres();
        }
        
        if ($this->groupBy) {
            $sql .= " " . $this->groupBy;
        }

        if ($this->having) {
            $sql .= " " . $this->having;
        }
        
        if ($this->orderBy) {
            $sql .= " " . $this->orderBy;
        }
        
        if ($this->limit) {
            $sql .= " " . $this->limit;
        }
        
        if ($this->offset) {
            $sql .= " " . $this->offset;
        }

        $this->bindings = $this->compileAllBindings();
        
        return $sql;
    }

    protected function compileWheres(): string {
        if (empty($this->wheres)) return '';

        $clauses = [];
        foreach ($this->wheres as $i => $where) {
            $prefix = ($i === 0) ? 'WHERE' : $where['type'];
            
            $kind = $where['kind'] ?? 'basic';

            switch ($kind) {
                case 'basic':
                    if ($where['value'] === null) {
                        $op = strtoupper($where['operator']);
                        if ($op === '=') $op = 'IS';
                        if ($op === '!=' || $op === '<>') $op = 'IS NOT';
                        $clauses[] = "$prefix {$where['column']} $op NULL";
                    } else {
                        $paramName = ":w_" . $i . "_" . $where['bindingKey'];
                        $clauses[] = "$prefix {$where['column']} {$where['operator']} $paramName";
                    }
                    break;

                case 'in':
                    $operator = $where['not'] ? 'NOT IN' : 'IN';
                    $list = implode(', ', $where['placeholders']);
                    $clauses[] = "$prefix {$where['column']} $operator ($list)";
                    break;

                case 'between':
                    $clauses[] = "$prefix {$where['column']} BETWEEN {$where['keyMin']} AND {$where['keyMax']}";
                    break;

                case 'raw':
                    $clauses[] = "$prefix ({$where['sql']})";
                    break;
            }
        }

        return implode(' ', $clauses);
    }

    /**
     * Compila TODOS los bindings (básicos + whereIn + whereBetween + whereRaw)
     */
    protected function compileAllBindings(): array {
        $bindings = [];

        foreach ($this->wheres as $i => $where) {
            $kind = $where['kind'] ?? 'basic';

            switch ($kind) {
                case 'basic':
                    if ($where['value'] !== null) {
                        $paramName = ":w_" . $i . "_" . $where['bindingKey'];
                        $bindings[$paramName] = $where['value'];
                    }
                    break;

                case 'in':
                    foreach ($where['bindings'] as $k => $v) {
                        $bindings[$k] = $v;
                    }
                    break;

                case 'between':
                    $bindings[$where['keyMin']] = $where['min'];
                    $bindings[$where['keyMax']] = $where['max'];
                    break;

                case 'raw':
                    foreach ($where['bindings'] as $k => $v) {
                        $bindings[$k] = $v;
                    }
                    break;
            }
        }

        return $bindings;
    }

    /**
     * Resetea el estado interno del builder.
     * Se invoca después de get(), first(), update(), delete().
     */
    protected function reset(): void {
        $this->wheres = [];
        $this->selects = ['*'];
        $this->joins = [];
        $this->bindings = [];
        $this->orderBy = null;
        $this->groupBy = null;
        $this->having = null;
        $this->limit = null;
        $this->offset = null;
        $this->paramCounter = 0;
    }

    // =========================================================
    // Write Methods (INSERT, UPDATE, DELETE)
    // =========================================================

    /**
     * Inserta un registro y devuelve el ID generado.
     * NO resetea condiciones (no las usa).
     */
    public function insert(array $data): int {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)", 
            $this->table, 
            implode(', ', $columns), 
            implode(', ', $placeholders)
        );
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        return $stmt->execute() ? (int)$this->pdo->lastInsertId() : 0;
    }

    /**
     * Actualiza registros que coincidan con las condiciones WHERE.
     * RESETA el builder después de ejecutar.
     * 
     * @throws Exception Si no hay condición WHERE (prevención de updates masivos accidentales)
     */
    public function update(array $data): bool {
        if (empty($this->wheres)) {
            throw new Exception("UPDATE inseguro: WHERE requerido.");
        }
        
        $setClause = [];
        foreach ($data as $key => $value) {
            $setClause[] = "$key = :update_$key";
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s %s", 
            $this->table, 
            implode(', ', $setClause), 
            $this->compileWheres()
        );
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(":update_$key", $value);
        }
        
        $whereParams = $this->compileAllBindings();
        foreach ($whereParams as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        
        $res = $stmt->execute();
        $this->reset();
        return $res;
    }

    /**
     * Elimina registros que coincidan con las condiciones WHERE.
     * RESETA el builder después de ejecutar.
     * 
     * @throws Exception Si no hay condición WHERE (prevención de deletes masivos accidentales)
     */
    public function delete(): bool {
        if (empty($this->wheres)) {
            throw new Exception("DELETE inseguro: WHERE requerido.");
        }
        
        $sql = sprintf("DELETE FROM %s %s", $this->table, $this->compileWheres());
        $stmt = $this->pdo->prepare($sql);
        
        $whereParams = $this->compileAllBindings();
        foreach ($whereParams as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        
        $res = $stmt->execute();
        $this->reset();
        return $res;
    }

    // =========================================================
    // Debugging
    // =========================================================

    /**
     * Devuelve el SQL que se ejecutaría (sin ejecutar).
     * Útil para debugging y logging.
     * 
     * @return array ['sql' => string, 'bindings' => array]
     */
    public function toSql(): array {
        $query = $this->compileSelect();
        return [
            'sql' => $query,
            'bindings' => $this->bindings
        ];
    }
}
