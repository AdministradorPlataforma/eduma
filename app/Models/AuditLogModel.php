<?php
declare(strict_types=1);

namespace App\Models;

use App\Models\BaseModel;
use PDO;

class AuditLogModel extends BaseModel {
    protected string $table = 'audit_logs';
    protected array $allowedFields = ['user_id', 'action', 'resource', 'details', 'ip_address', 'user_agent'];

    /**
     * Obtiene los logs filtrados con información del usuario.
     * @param array $filters Opcional: ['start_date', 'end_date', 'user_id', 'action']
     * @param int $limit
     * @return array
     */
    public function getLogsFiltered(array $filters = [], int $limit = 100) {
        $where = [];
        $params = [];

        if (!empty($filters['start_date'])) {
            $where[] = "a.created_at >= :start_date";
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
        }
        if (!empty($filters['end_date'])) {
            $where[] = "a.created_at <= :end_date";
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }
        if (!empty($filters['user_id'])) {
            $where[] = "a.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        if (!empty($filters['action'])) {
             $where[] = "a.action LIKE :action";
             $params[':action'] = "%" . $filters['action'] . "%";
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT a.*, u.username, u.nombre, u.apellido 
                FROM audit_logs a 
                LEFT JOIN usuarios u ON a.user_id = u.id 
                $whereClause
                ORDER BY a.created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
