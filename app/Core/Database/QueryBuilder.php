<?php
declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use Exception;

class QueryBuilder {
    protected PDO $pdo;
    protected string $table;
    protected array $bindings = [];
    protected array $wheres = [];
    protected array $selects = ['*'];
    protected array $joins = [];
    protected ?string $groupBy = null;
    protected ?string $orderBy = null;
    protected ?string $limit = null;
    protected ?string $offset = null;

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

    public function where(string $column, $operator, $value = null): self {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $bindingKey = str_replace('.', '_', $column) . '_' . count($this->wheres);

        $this->wheres[] = [
            'type' => 'AND',
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
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'bindingKey' => $bindingKey 
        ];

        return $this;
    }
    
    public function groupBy(string $column): self {
        $this->groupBy = "GROUP BY $column";
        return $this;
    }
    
    public function orderBy(string $column, string $direction = 'ASC'): self {
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

    public function get(): array {
        $query = $this->compileSelect();
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($this->bindings);
        $this->reset();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ... resto de métodos insert/update/delete similares ...

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
        
        if ($this->orderBy) {
            $sql .= " " . $this->orderBy;
        }
        
        if ($this->limit) {
            $sql .= " " . $this->limit;
        }
        
        if ($this->offset) {
            $sql .= " " . $this->offset;
        }

        $this->bindings = $this->compileWhereBindings();
        
        return $sql;
    }

    protected function compileWheres(): string {
        if (empty($this->wheres)) return '';

        $clauses = [];
        foreach ($this->wheres as $i => $where) {
            $prefix = ($i === 0) ? 'WHERE' : $where['type'];
            
            if ($where['value'] === null) {
                $op = strtoupper($where['operator']);
                if ($op === '=') $op = 'IS';
                if ($op === '!=' || $op === '<>') $op = 'IS NOT';
                $clauses[] = "$prefix {$where['column']} $op NULL";
            } else {
                $paramName = ":w_" . $i . "_" . $where['bindingKey'];
                $clauses[] = "$prefix {$where['column']} {$where['operator']} $paramName";
            }
        }

        return implode(' ', $clauses);
    }

    protected function compileWhereBindings(): array {
        $bindings = [];
        foreach ($this->wheres as $i => $where) {
            if ($where['value'] !== null) {
                $paramName = ":w_" . $i . "_" . $where['bindingKey'];
                $bindings[$paramName] = $where['value'];
            }
        }
        return $bindings;
    }

    protected function reset() {
        $this->wheres = [];
        $this->selects = ['*'];
        $this->joins = [];
        $this->bindings = [];
        $this->orderBy = null;
        $this->groupBy = null;
        $this->limit = null;
        $this->offset = null;
    }

    // Métodos write (insert, update, delete) simplificados para no perderlos
    // (Incluirlos en una sola pasada si es posible, pero el diff original era grande)
    // Para simplificar, asumiré que el USER copiará el resto o que el replace funcionará bien si mantengo parte.
    // Pero como reeemplacé desde namespace, DEBO incluirlos.

    public function first() {
        $this->limit(1);
        $result = $this->get();
        return $result[0] ?? null;
    }

    public function count(): int {
        $originalSelects = $this->selects;
        $this->selects = ['COUNT(*) as total'];
        $query = $this->compileSelect();
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($this->bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->selects = $originalSelects;
        $this->reset();
        return (int)($result['total'] ?? 0);
    }

    public function insert(array $data): int {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        $sql = sprintf("INSERT INTO %s (%s) VALUES (%s)", $this->table, implode(', ', $columns), implode(', ', $placeholders));
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $key => $value) $stmt->bindValue(":$key", $value);
        return $stmt->execute() ? (int)$this->pdo->lastInsertId() : 0;
    }

    public function update(array $data): bool {
        if (empty($this->wheres)) throw new Exception("UPDATE inseguro: WHERE requerido.");
        $setClause = [];
        foreach ($data as $key => $value) $setClause[] = "$key = :update_$key";
        $sql = sprintf("UPDATE %s SET %s %s", $this->table, implode(', ', $setClause), $this->compileWheres());
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $key => $value) $stmt->bindValue(":update_$key", $value);
        $whereParams = $this->compileWhereBindings();
        foreach($whereParams as $k => $v) $stmt->bindValue($k, $v);
        $res = $stmt->execute();
        $this->reset();
        return $res;
    }

    public function delete(): bool {
        if (empty($this->wheres)) throw new Exception("DELETE inseguro: WHERE requerido.");
        $sql = sprintf("DELETE FROM %s %s", $this->table, $this->compileWheres());
        $stmt = $this->pdo->prepare($sql);
        $whereParams = $this->compileWhereBindings();
        foreach($whereParams as $k => $v) $stmt->bindValue($k, $v);
        $res = $stmt->execute();
        $this->reset();
        return $res;
    }
}
