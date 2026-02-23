<?php
declare(strict_types=1);

namespace App\Repositories\Investigacion;

use App\Models\Investigacion\TesisModel;
use PDO;

class TesisRepository {
    private TesisModel $model;
    private PDO $db;

    public function __construct(TesisModel $model, PDO $db) {
        $this->model = $model;
        $this->db = $db;
    }

    public function find(int $id): ?array {
        return $this->model->find($id);
    }



    public function create(array $data, array $estudiantesIds, array $docentesIds): int {
        $tesisId = $this->model->create($data);

        // Guardar Estudiantes (IDs)
        $stmtEst = $this->db->prepare("INSERT INTO tesis_estudiantes (tesis_id, estudiante_id) VALUES (:tesis_id, :estudiante_id)");
        foreach ($estudiantesIds as $estId) {
            $stmtEst->execute([
                ':tesis_id' => $tesisId, 
                ':estudiante_id' => $estId
            ]);
        }

        // Guardar Docentes (IDs)
        $stmtDoc = $this->db->prepare("INSERT INTO tesis_docentes (tesis_id, docente_id, rol) VALUES (:tesis_id, :docente_id, :rol)");
        foreach ($docentesIds as $docId) {
            $stmtDoc->execute([
                ':tesis_id' => $tesisId, 
                ':docente_id' => $docId,
                ':rol' => 'Tutor' // Default role for now
            ]);
        }

        return $tesisId;
    }

    public function update(int $id, array $data, ?array $estudiantesIds = null, ?array $docentesIds = null): bool {
        $updated = $this->model->update($id, $data);

        if ($estudiantesIds !== null) {
            $this->db->prepare("DELETE FROM tesis_estudiantes WHERE tesis_id = :id")->execute([':id' => $id]);
            $stmt = $this->db->prepare("INSERT INTO tesis_estudiantes (tesis_id, estudiante_id) VALUES (:tesis_id, :estudiante_id)");
            foreach ($estudiantesIds as $estId) {
                 $stmt->execute([
                    ':tesis_id' => $id, 
                    ':estudiante_id' => $estId
                ]);
            }
        }

        if ($docentesIds !== null) {
            $this->db->prepare("DELETE FROM tesis_docentes WHERE tesis_id = :id")->execute([':id' => $id]);
            $stmt = $this->db->prepare("INSERT INTO tesis_docentes (tesis_id, docente_id, rol) VALUES (:tesis_id, :docente_id, :rol)");
            foreach ($docentesIds as $docId) {
                $stmt->execute([
                    ':tesis_id' => $id, 
                    ':docente_id' => $docId,
                    ':rol' => 'Tutor'
                ]);
            }
        }

        return $updated;
    }

    public function delete(int $id): bool {
        return $this->model->delete($id);
    }

    public function paginate(int $page = 1, int $perPage = 15): array {
        $offset = ($page - 1) * $perPage;

        // 1. Obtener Total (Count Distinct)
        $countSql = "SELECT COUNT(*) as total FROM tesis";
        $stmt = $this->db->query($countSql);
        $total = (int)$stmt->fetchColumn();

        // 2. Obtener Data
        $sql = "SELECT t.*, 
                         GROUP_CONCAT(DISTINCT CONCAT(u_est.nombre, ' ', u_est.apellido) SEPARATOR ' / ') as estudiantes_nombres,
                         GROUP_CONCAT(DISTINCT CONCAT(u_doc.nombre, ' ', u_doc.apellido) SEPARATOR ' / ') as tutores_nombres
                  FROM tesis t
                  LEFT JOIN tesis_estudiantes te ON t.id = te.tesis_id
                  LEFT JOIN estudiantes e ON te.estudiante_id = e.id
                  LEFT JOIN usuarios u_est ON e.usuario_id = u_est.id

                  LEFT JOIN tesis_docentes td ON t.id = td.tesis_id
                  LEFT JOIN docentes d ON td.docente_id = d.id
                  LEFT JOIN usuarios u_doc ON d.usuario_id = u_doc.id

                  GROUP BY t.id
                  ORDER BY t.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    // Método getAll eliminado en favor de paginate, pero mantenemos compatibilidad temporal
    // si alguna otra parte del sistema lo usa (aunque el refactor pide reemplazarlo).
    // Lo dejaremos vacio o lanzando excepcion para forzar el cambio.
    public function getAll(): array {
        // Fallback a paginate con pagina 1 y un numero alto si es critico
        // return $this->paginate(1, 1000)['data'];
        throw new \Exception("Uso de getAll() deprecado. Use paginate().");
    }

    public function searchEstudiantes(string $term): array {
        $term = "%$term%";
        $stmt = $this->db->prepare("
            SELECT e.id, u.nombre, u.apellido, u.username, e.legajo 
            FROM estudiantes e 
            JOIN usuarios u ON e.usuario_id = u.id 
            WHERE CONCAT(u.nombre, ' ', u.apellido) LIKE :term1 
               OR e.legajo LIKE :term2
               OR u.username LIKE :term3
            ORDER BY u.apellido ASC 
            LIMIT 20
        ");
        $stmt->execute([
            ':term1' => $term,
            ':term2' => $term,
            ':term3' => $term
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchDocentes(string $term): array {
        $term = "%$term%";
        $stmt = $this->db->prepare("
            SELECT d.id, u.nombre, u.apellido, u.username, d.titulo_profesional 
            FROM docentes d 
            JOIN usuarios u ON d.usuario_id = u.id 
            WHERE CONCAT(u.nombre, ' ', u.apellido) LIKE :term1 
               OR d.titulo_profesional LIKE :term2
               OR u.username LIKE :term3
            ORDER BY u.apellido ASC 
            LIMIT 20
        ");
        $stmt->execute([
            ':term1' => $term,
            ':term2' => $term,
            ':term3' => $term
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getEstudiantesIds(int $tesisId): array {
        return [];
    }

    public function getTutoresIds(int $tesisId): array {
        return [];
    }

    public function getEstudiantesList(int $tesisId): array {
        $sql = "SELECT te.*, e.id as estudiante_id, u.nombre, u.apellido, e.legajo 
                FROM tesis_estudiantes te
                JOIN estudiantes e ON te.estudiante_id = e.id
                JOIN usuarios u ON e.usuario_id = u.id
                WHERE te.tesis_id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $tesisId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDocentesList(int $tesisId): array {
        $sql = "SELECT td.*, d.id as docente_id, u.nombre, u.apellido, d.titulo_profesional 
                FROM tesis_docentes td
                JOIN docentes d ON td.docente_id = d.id
                JOIN usuarios u ON d.usuario_id = u.id
                WHERE td.tesis_id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $tesisId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEstudiantesFull(int $tesisId): array {
        return $this->getEstudiantesList($tesisId);
    }

    public function getTutoresFull(int $tesisId): array {
        return $this->getDocentesList($tesisId);
    }

    public function searchTesis(string $term): array {
        $term = "%$term%";
        $sql = "SELECT t.id, t.titulo, t.codigo, t.estado, t.created_at
                FROM tesis t
                WHERE t.titulo LIKE :term1 OR t.codigo LIKE :term2
                ORDER BY t.created_at DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':term1' => $term, ':term2' => $term]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
