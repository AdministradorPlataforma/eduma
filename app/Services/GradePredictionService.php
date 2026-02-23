<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Servicio de Predicción de Rendimiento Académico.
 * Utiliza promedios, tendencias y participación para identificar estudiantes en riesgo.
 */
class GradePredictionService extends BaseService
{
    private PDO $db;

    public function __construct()
    {
        $dbConfig = new \Config\Database();
        $this->db = $dbConfig->getConnection();
    }

    /**
     * Analiza el riesgo de un estudiante en un curso específico.
     * Retorna un nivel de riesgo (Bajo, Medio, Alto) y la lógica detrás.
     */
    public function predictStudentRisk(int $userId, int $courseId): array
    {
        // 1. Obtener calificaciones actuales
        $stmt = $this->db->prepare("
            SELECT calificacion_final, calificacion_maxima 
            FROM calificaciones c
            JOIN curso_matriculas m ON c.matricula_id = m.id
            WHERE m.usuario_id = ? AND m.curso_id = ?
        ");
        $stmt->execute([$userId, $courseId]);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($grades)) {
            return ['risk' => 'Desconocido', 'score' => 0, 'reason' => 'Sin datos de calificaciones aún.'];
        }

        $totalScore = 0;
        $count = 0;
        foreach ($grades as $g) {
            $percent = ($g['calificacion_final'] / $g['calificacion_maxima']) * 100;
            $totalScore += $percent;
            $count++;
        }

        $average = $totalScore / $count;
        
        // Lógica de riesgo simplificada
        $risk = 'Bajo';
        $color = '#10b981';
        
        if ($average < 60) {
            $risk = 'Crítico / Alto';
            $color = '#ef4444';
        } elseif ($average < 75) {
            $risk = 'Medio';
            $color = '#f59e0b';
        }

        return [
            'average' => round($average, 1),
            'risk' => $risk,
            'color' => $color,
            'reason' => "Basado en $count ítems calificados. Promedio actual: " . round($average, 1) . "%"
        ];
    }

    /**
     * Obtiene una lista de estudiantes en riesgo para un docente.
     */
    public function getAtRiskStudents(int $teacherUserId): array
    {
        // Esta es una consulta pesada que analiza todos los cursos del docente
        $sql = "SELECT u.id, u.nombre, u.apellido, c.fullname as curso_nombre,
                       AVG(cal.calificacion_final / cal.calificacion_maxima * 100) as promedio
                FROM usuarios u
                JOIN curso_matriculas m ON u.id = m.usuario_id
                JOIN cursos c ON m.curso_id = c.id
                JOIN calificaciones cal ON m.id = cal.matricula_id
                WHERE c.id IN (
                    SELECT curso_id FROM curso_matriculas WHERE usuario_id = ? AND rol_moodle = 'editingteacher'
                )
                GROUP BY u.id, c.id_moodle
                HAVING promedio < 70
                ORDER BY promedio ASC
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$teacherUserId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
