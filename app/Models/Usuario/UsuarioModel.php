<?php
declare(strict_types=1);

namespace App\Models\Usuario;

use App\Models\BaseModel;
use PDO;

class UsuarioModel extends BaseModel {
    protected string $table = 'usuarios';
    protected array $allowedFields = [
        'id_moodle', 'username', 'email', 'password', 'nombre', 'apellido', 
        'es_estudiante', 'es_docente', 'es_admin', 'auth_method', 'data_hash',
        'suspended', 'activo'
    ];
    
    // No necesitamos constructor, usa el de BaseModel

    /**
     * Obtiene todos los usuarios que no han sido suspendidos.
     * @deprecated Usar getPaginated() para mejor rendimiento con grandes datasets
     * @return array
     */
    public function getAll() {
        return $this->builder
            ->select([
                '*',
                "(CASE 
                    WHEN es_admin = 1 THEN 'Administrador'
                    WHEN es_docente = 1 THEN 'Docente'
                    WHEN es_estudiante = 1 THEN 'Estudiante'
                    ELSE 'Usuario'
                END) as rol"
            ])
            ->where('suspended', '=', 0)
            ->orderBy('id', 'DESC')
            ->get();
    }

    /**
     * Obtener usuarios con paginación para DataTables Server-Side
     * 
     * @param int $start Offset de inicio
     * @param int $length Cantidad de registros
     * @param string $search Término de búsqueda
     * @param string $orderColumn Columna para ordenar
     * @param string $orderDir Dirección (ASC/DESC)
     * @return array
     */
    public function getPaginated(
        int $start = 0, 
        int $length = 25, 
        string $search = '', 
        string $orderColumn = 'id', 
        string $orderDir = 'DESC'
    ): array {
        // Validar columnas permitidas para ordenar
        $allowedColumns = ['id', 'nombre', 'apellido', 'username', 'email', 'rol', 'created_at'];
        if (!in_array($orderColumn, $allowedColumns)) {
            $orderColumn = 'id';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        // Construir query base
        $baseSelect = "
            u.id,
            u.id_moodle,
            u.nombre,
            u.apellido,
            u.username,
            u.email,
            u.es_admin,
            u.es_docente,
            u.es_estudiante,
            u.activo,
            u.created_at,
            (CASE 
                WHEN u.es_admin = 1 THEN 'Administrador'
                WHEN u.es_docente = 1 THEN 'Docente'
                WHEN u.es_estudiante = 1 THEN 'Estudiante'
                ELSE 'Usuario'
            END) as rol
        ";

        $conditions = ["u.suspended = 0"];
        $params = [];

        // Búsqueda - usar ? placeholders para evitar problemas con PDO
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $conditions[] = "(
                u.nombre LIKE ? 
                OR u.apellido LIKE ? 
                OR u.email LIKE ? 
                OR u.username LIKE ?
                OR CONCAT(u.nombre, ' ', u.apellido) LIKE ?
            )";
            // Agregar el término 5 veces para cada condición LIKE
            $params = array_fill(0, 5, $searchTerm);
        }

        $whereClause = implode(' AND ', $conditions);

        // Query para datos
        $sql = "
            SELECT $baseSelect
            FROM usuarios u
            WHERE $whereClause
            ORDER BY " . ($orderColumn === 'rol' ? "rol" : "u.$orderColumn") . " $orderDir
            LIMIT ? OFFSET ?
        ";

        // Agregar limit y offset
        $params[] = $length;
        $params[] = $start;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta usuarios filtrados para DataTables
     */
    public function countFiltered(string $search = ''): int {
        $conditions = ["suspended = 0"];
        $params = [];

        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $conditions[] = "(
                nombre LIKE ? 
                OR apellido LIKE ? 
                OR email LIKE ? 
                OR username LIKE ?
                OR CONCAT(nombre, ' ', apellido) LIKE ?
            )";
            $params = array_fill(0, 5, $searchTerm);
        }

        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT COUNT(*) FROM usuarios WHERE $whereClause";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }


    /**
     * Cuenta el total de usuarios activos.
     * @return int
     */
    public function countAll(): int {
        return $this->builder
            ->where('suspended', '=', 0)
            ->count();
    }

    /**
     * Busca un usuario por su ID.
     * @param int $id
     * @return array|null
     */
    public function findById(int $id) {
        return $this->builder
            ->where('id', '=', $id)
            ->where('suspended', '=', 0)
            ->first();
    }

    /**
     * Busca un usuario por su nombre de usuario.
     * @param string $username
     * @return array|null
     */
    public function findByUsername(string $username) {
        return $this->builder
            ->where('username', '=', $username)
            ->where('suspended', '=', 0)
            ->first();
    }
    
    /**
     * Busca un usuario por su email.
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email) {
        return $this->builder
            ->where('email', '=', $email)
            ->where('suspended', '=', 0)
            ->first();
    }

    /**
     * Realiza una suspensión lógica del usuario.
     * @param int $id
     * @return bool
     */
    public function suspend(int $id) {
        return $this->update($id, ['suspended' => 1]);
    }
}
