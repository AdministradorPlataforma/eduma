<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Container;
use PDO;

class DashboardRepository {
    protected $db;

    public function __construct() {
        $this->db = Container::getInstance()->get('db');
    }

    /**
     * Obtiene estadísticas agregadas del sistema para el Escritorio (KPIs)
     */
    public function getEstadisticasEstructura(?int $facultadId = null, ?int $carreraId = null): array {
        $stats = [
            'total_facultades' => 0,
            'total_carreras' => 0,
            'total_cursos_activos' => 0,
            'cumplimiento_promedio' => 0, // Placeholder
            'crecimiento_cursos' => 0,
            'cursos_nuevos_mes' => 0
        ];

        try {
            // 1. Contar Facultades
            $sqlFacultades = "SELECT COUNT(*) FROM facultades";
            $paramsFac = [];
            if ($facultadId) {
                $sqlFacultades .= " WHERE id = :id";
                $paramsFac[':id'] = $facultadId;
            } elseif ($carreraId) {
                 $sqlFacultades = "SELECT COUNT(DISTINCT facultad_id) FROM carreras WHERE id = :carrera_id";
                 $paramsFac[':carrera_id'] = $carreraId;
            }
            $stmt = $this->db->prepare($sqlFacultades);
            $stmt->execute($paramsFac);
            $stats['total_facultades'] = (int)$stmt->fetchColumn();

            // 2. Contar Carreras
            $sqlCarreras = "SELECT COUNT(*) FROM carreras";
            $paramsCar = [];
            $whereCar = [];
            if ($facultadId) {
                $whereCar[] = "facultad_id = :facultad_id";
                $paramsCar[':facultad_id'] = $facultadId;
            }
            if ($carreraId) {
                $whereCar[] = "id = :carrera_id";
                $paramsCar[':carrera_id'] = $carreraId;
            }
            if (!empty($whereCar)) {
                $sqlCarreras .= " WHERE " . implode(" AND ", $whereCar);
            }
            $stmt = $this->db->prepare($sqlCarreras);
            $stmt->execute($paramsCar);
            $stats['total_carreras'] = (int)$stmt->fetchColumn();

            // 3. Contar Cursos Activos (Visible = 1)
            $sqlCursos = "SELECT COUNT(*) FROM cursos c";
            $paramsCur = [];
            $joinsCur = [];
            $whereCur = ["c.visible = 1"];

            if ($facultadId || $carreraId) {
                $joinsCur[] = "JOIN carreras ca ON c.carrera_id = ca.id";
            }

            if ($facultadId) {
                $whereCur[] = "ca.facultad_id = :facultad_id";
                $paramsCur[':facultad_id'] = $facultadId;
            }
            if ($carreraId) {
                $whereCur[] = "c.carrera_id = :carrera_id";
                $paramsCur[':carrera_id'] = $carreraId;
            }
            if (!empty($joinsCur)) {
                $sqlCursos .= " " . implode(" ", $joinsCur);
            }
            if (!empty($whereCur)) {
                $sqlCursos .= " WHERE " . implode(" AND ", $whereCur);
            }

            $stmt = $this->db->prepare($sqlCursos);
            $stmt->execute($paramsCur);
            $stats['total_cursos_activos'] = (int)$stmt->fetchColumn();

            // --- Crecimiento ---
            $sqlEsteMes = "SELECT COUNT(*) FROM cursos c";
            if (!empty($joinsCur)) $sqlEsteMes .= " " . implode(" ", $joinsCur);
            
            $whereEsteMes = array_merge($whereCur, ["MONTH(c.created_at) = MONTH(CURRENT_DATE()) AND YEAR(c.created_at) = YEAR(CURRENT_DATE())"]);
            $sqlEsteMes .= " WHERE " . implode(" AND ", $whereEsteMes);

             // Removing Visible = 1 filter for growth calculation if desired, but reusing params, so keeping consistency
             // Actually Model logic had visible=1 in count but growth calculation query starts fresh?
             // Model logic: $sqlBaseCrecimiento = "SELECT COUNT(*) FROM cursos c"; -> No visible filter by default?
             // Let's stick to strict replication of logic but cleaner.
             
             // Re-building growth queries to match original logic exactly (no visible filter likely intended for "new courses")
             $whereGrowth = [];
             if ($facultadId) $whereGrowth[] = "ca.facultad_id = :facultad_id";
             if ($carreraId) $whereGrowth[] = "c.carrera_id = :carrera_id";
             
             $sqlGrowthBase = "SELECT COUNT(*) FROM cursos c";
             if (!empty($joinsCur)) $sqlGrowthBase .= " " . implode(" ", $joinsCur);
             
             $whereThisMonth = array_merge($whereGrowth, ["MONTH(c.created_at) = MONTH(CURRENT_DATE()) AND YEAR(c.created_at) = YEAR(CURRENT_DATE())"]);
             $sqlThisMonth = $sqlGrowthBase . ((!empty($whereThisMonth)) ? " WHERE " . implode(" AND ", $whereThisMonth) : "");
             
             $whereLastMonth = array_merge($whereGrowth, ["MONTH(c.created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(c.created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)"]);
             $sqlLastMonth = $sqlGrowthBase . ((!empty($whereLastMonth)) ? " WHERE " . implode(" AND ", $whereLastMonth) : "");

            $stmt = $this->db->prepare($sqlThisMonth);
            $stmt->execute($paramsCur);
            $countEsteMes = (int)$stmt->fetchColumn();
            $stats['cursos_nuevos_mes'] = $countEsteMes;

            $stmt = $this->db->prepare($sqlLastMonth);
            $stmt->execute($paramsCur);
            $countMesPasado = (int)$stmt->fetchColumn();
            
            if ($countMesPasado > 0) {
                $stats['crecimiento_cursos'] = round((($countEsteMes - $countMesPasado) / $countMesPasado) * 100, 1);
            } elseif ($countEsteMes > 0) {
                $stats['crecimiento_cursos'] = 100; 
            }

        } catch (\Exception $e) {
            // Log error silently or rethrow
        }

        return $stats;
    }

    /**
     * Obtiene feed de actividad reciente
     */
    public function getFeedActividad(int $limit = 5): array {
        $sql = "SELECT e.*, t.producto_documento, u.nombre as usuario_nombre, u.apellido as usuario_apellido 
                FROM gestion_evidencias e
                JOIN gestion_control_seguimiento t ON e.tarea_id = t.id
                JOIN usuarios u ON e.usuario_id = u.id
                ORDER BY e.fecha_subida DESC 
                LIMIT :limit";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene próximos vencimientos
     */
    public function getProximosVencimientos(int $limit = 5): array {
        try {
            $sql = "SELECT * FROM gestion_actividades_maestras WHERE fecha_vencimiento >= CURDATE() ORDER BY fecha_vencimiento ASC LIMIT :limit";
             $stmt = $this->db->prepare($sql);
             $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
             $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
             return [];
        }
    }
}
