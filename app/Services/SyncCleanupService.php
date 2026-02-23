<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Servicio de Limpieza y Detección de Entidades Huérfanas
 * 
 * Este servicio maneja la sincronización de eliminaciones entre Moodle y EDUMA.
 * Implementa SOFT DELETE para mantener integridad referencial y permitir auditoría.
 * 
 * Estrategia de Eliminación:
 * - NO se eliminan registros físicamente (hard delete)
 * - Se marcan como "desactivados" o "suspendidos"
 * - Se registra el evento en audit_logs
 * - Se permite reactivación si la entidad reaparece en Moodle
 * 
 * @version 1.0
 */
class SyncCleanupService extends BaseService {

    private PDO $db;

    /** @var array Estadísticas de la última limpieza */
    private array $stats = [];

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Ejecuta limpieza completa de todas las entidades
     * 
     * @param array $moodleData Datos actuales de Moodle (opcional, si no se pasa, se obtienen)
     * @return array Estadísticas de la limpieza
     */
    public function ejecutarLimpiezaCompleta(?array $moodleData = null): array {
        $this->stats = [
            'usuarios_desactivados' => 0,
            'cursos_ocultados' => 0,
            'categorias_huerfanas' => 0,
            'matriculas_desactivadas' => 0,
            'errors' => 0,
            'start_time' => microtime(true)
        ];

        try {
            // Si no se proporcionan datos, obtener de Moodle
            if ($moodleData === null) {
                $client = new \Modules\Moodle\MoodleClient();
                $moodleData = [
                    'users' => $client->getAllUsers(),
                    'courses' => $client->getAllCourses(),
                    'categories' => $client->getCategories()
                ];
            }

            // 1. Limpiar usuarios
            if (isset($moodleData['users'])) {
                $this->stats['usuarios_desactivados'] = $this->detectarUsuariosEliminados(
                    array_column($moodleData['users'], 'id')
                );
            }

            // 2. Limpiar cursos
            if (isset($moodleData['courses'])) {
                $this->stats['cursos_ocultados'] = $this->detectarCursosEliminados(
                    array_column($moodleData['courses'], 'id')
                );
            }

            // 3. Limpiar categorías
            if (isset($moodleData['categories'])) {
                $this->stats['categorias_huerfanas'] = $this->detectarCategoriasEliminadas(
                    array_column($moodleData['categories'], 'id')
                );
            }

            // 4. Limpiar matrículas huérfanas
            $this->stats['matriculas_desactivadas'] = $this->limpiarMatriculasHuerfanas();

            $this->stats['elapsed_seconds'] = round(microtime(true) - $this->stats['start_time'], 2);
            
            LoggerService::info("Limpieza de sincronización completada", $this->stats);

        } catch (\Exception $e) {
            $this->stats['errors']++;
            $this->stats['error_message'] = $e->getMessage();
            LoggerService::error("Error en limpieza de sincronización", [
                'error' => $e->getMessage()
            ]);
        }

        return $this->stats;
    }

    /**
     * Detecta usuarios que existen localmente pero ya no están en Moodle
     * y los marca como suspendidos
     * 
     * @param array $moodleUserIds Lista de IDs de Moodle activos
     * @return int Número de usuarios desactivados
     */
    public function detectarUsuariosEliminados(array $moodleUserIds): int {
        if (empty($moodleUserIds)) {
            LoggerService::warning("detectarUsuariosEliminados: Lista vacía de IDs, no se realizará limpieza");
            return 0;
        }

        // Usar nombre único para evitar colisiones en concurrencia
        $tempTable = 'tmp_moodle_users_' . uniqid();

        // Crear tabla temporal con IDs de Moodle activos
        $this->db->exec("DROP TEMPORARY TABLE IF EXISTS $tempTable");
        $this->db->exec("CREATE TEMPORARY TABLE $tempTable (id_moodle INT UNSIGNED PRIMARY KEY)");

        try {
            // Insertar IDs en chunks para evitar queries enormes
            $chunks = array_chunk($moodleUserIds, 500);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '(?)'));
                $stmt = $this->db->prepare(
                    "INSERT INTO $tempTable (id_moodle) VALUES $placeholders"
                );
                $stmt->execute($chunk);
            }

            // Obtener usuarios locales que NO están en Moodle
            $sql = "SELECT u.id, u.id_moodle, u.username, u.email
                    FROM usuarios u
                    WHERE u.id_moodle IS NOT NULL
                      AND u.suspended = 0
                      AND NOT EXISTS (
                          SELECT 1 FROM $tempTable t WHERE t.id_moodle = u.id_moodle
                      )";

            $stmt = $this->db->query($sql);
            $usuariosHuerfanos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } finally {
             // Limpieza siempre
             $this->db->exec("DROP TEMPORARY TABLE IF EXISTS $tempTable");
        }

        if (empty($usuariosHuerfanos)) {
            return 0;
        }

        // Marcar como suspendidos (soft delete)
        $idsToSuspend = array_column($usuariosHuerfanos, 'id');
        $placeholders = implode(',', array_fill(0, count($idsToSuspend), '?'));

        $updateSql = "UPDATE usuarios 
                      SET suspended = 1, 
                          updated_at = NOW()
                      WHERE id IN ($placeholders)";

        $stmt = $this->db->prepare($updateSql);
        $stmt->execute($idsToSuspend);

        $count = $stmt->rowCount();

        // Registrar en audit_logs
        LoggerService::audit(null, 'USERS_SUSPENDED_CLEANUP', 'Users:' . implode(',', $idsToSuspend), [
            'count' => $count,
            'reason' => 'not_in_moodle',
            'usernames' => array_column($usuariosHuerfanos, 'username')
        ]);

        return $count;
    }

    /**
     * Detecta cursos que existen localmente pero ya no están en Moodle
     * y los marca como no visibles
     * 
     * @param array $moodleCourseIds Lista de IDs de Moodle activos
     * @return int Número de cursos ocultados
     */
    public function detectarCursosEliminados(array $moodleCourseIds): int {
        if (empty($moodleCourseIds)) {
            LoggerService::warning("detectarCursosEliminados: Lista vacía de IDs");
            return 0;
        }

        // Usar nombre único
        $tempTable = 'tmp_moodle_courses_' . uniqid();

        // Crear tabla temporal
        $this->db->exec("DROP TEMPORARY TABLE IF EXISTS $tempTable");
        $this->db->exec("CREATE TEMPORARY TABLE $tempTable (id_moodle INT UNSIGNED PRIMARY KEY)");

        try {
            $chunks = array_chunk($moodleCourseIds, 500);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '(?)'));
                $stmt = $this->db->prepare(
                    "INSERT INTO $tempTable (id_moodle) VALUES $placeholders"
                );
                $stmt->execute($chunk);
            }

            // Obtener cursos locales que NO están en Moodle
            $sql = "SELECT c.id, c.id_moodle, c.shortname
                    FROM cursos c
                    WHERE c.visible = 1
                      AND NOT EXISTS (
                          SELECT 1 FROM $tempTable t WHERE t.id_moodle = c.id_moodle
                      )";

            $stmt = $this->db->query($sql);
            $cursosHuerfanos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } finally {
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS $tempTable");
        }

        if (empty($cursosHuerfanos)) {
            return 0;
        }

        // Marcar como no visibles (soft delete)
        $idsToHide = array_column($cursosHuerfanos, 'id');
        $placeholders = implode(',', array_fill(0, count($idsToHide), '?'));

        $updateSql = "UPDATE cursos 
                      SET visible = 0, 
                          updated_at = NOW()
                      WHERE id IN ($placeholders)";

        $stmt = $this->db->prepare($updateSql);
        $stmt->execute($idsToHide);

        $count = $stmt->rowCount();

        // Registrar en audit_logs
        LoggerService::audit(null, 'COURSES_HIDDEN_CLEANUP', 'Courses:' . implode(',', $idsToHide), [
            'count' => $count,
            'reason' => 'not_in_moodle',
            'shortnames' => array_column($cursosHuerfanos, 'shortname')
        ]);

        return $count;
    }

    /**
     * Detecta categorías que ya no existen en Moodle
     * Las marca como huérfanas pero no las elimina
     * 
     * @param array $moodleCategoryIds Lista de IDs de categorías activas en Moodle
     * @return int Número de categorías marcadas
     */
    public function detectarCategoriasEliminadas(array $moodleCategoryIds): int {
        if (empty($moodleCategoryIds)) {
            return 0;
        }

        // Las categorías en raw_moodle_categorias no tienen campo "visible" o "suspended"
        // Opción 1: Eliminar físicamente (peligroso para integridad referencial)
        // Opción 2: Agregar columna "deleted_at" (soft delete)
        // Opción 3: Solo registrar en logs y no tomar acción

        // Implementamos Opción 3: Solo detectar y loggear
        $placeholders = implode(',', array_fill(0, count($moodleCategoryIds), '?'));

        $sql = "SELECT id, name 
                FROM raw_moodle_categorias 
                WHERE id NOT IN ($placeholders)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($moodleCategoryIds);
        $categoriasHuerfanas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($categoriasHuerfanas)) {
            LoggerService::warning("Categorías huérfanas detectadas (no eliminadas en Moodle)", [
                'count' => count($categoriasHuerfanas),
                'categories' => $categoriasHuerfanas
            ]);
        }

        return count($categoriasHuerfanas);
    }

    /**
     * Limpia matrículas que apuntan a cursos o usuarios que ya no están activos
     * 
     * @return int Número de matrículas procesadas
     */
    public function limpiarMatriculasHuerfanas(): int {
        // Desactivar matrículas de usuarios suspendidos
        $sql = "UPDATE curso_matriculas cm
                JOIN usuarios u ON cm.usuario_id = u.id
                SET cm.estado = 'suspendido', cm.updated_at = NOW()
                WHERE u.suspended = 1 
                  AND cm.estado = 'activo'";

        $stmt = $this->db->query($sql);
        $countUsuarios = $stmt->rowCount();

        // Desactivar matrículas de cursos no visibles
        $sql = "UPDATE curso_matriculas cm
                JOIN cursos c ON cm.curso_id = c.id
                SET cm.estado = 'suspendido', cm.updated_at = NOW()
                WHERE c.visible = 0 
                  AND cm.estado = 'activo'";

        $stmt = $this->db->query($sql);
        $countCursos = $stmt->rowCount();

        $total = $countUsuarios + $countCursos;

        if ($total > 0) {
            LoggerService::audit(null, 'ENROLLMENTS_SUSPENDED_CLEANUP', 'Enrollments', [
                'by_suspended_users' => $countUsuarios,
                'by_hidden_courses' => $countCursos,
                'total' => $total
            ]);
        }

        return $total;
    }

    /**
     * Reactiva un usuario que reaparece en Moodle
     * 
     * @param int $idMoodle ID de Moodle del usuario
     * @return bool True si se reactivó
     */
    public function reactivarUsuario(int $idMoodle): bool {
        $stmt = $this->db->prepare(
            "UPDATE usuarios 
             SET suspended = 0, updated_at = NOW() 
             WHERE id_moodle = ? AND suspended = 1"
        );
        $stmt->execute([$idMoodle]);

        if ($stmt->rowCount() > 0) {
            LoggerService::audit(null, 'USER_REACTIVATED', "User:moodle:$idMoodle", [
                'reason' => 'reappeared_in_moodle'
            ]);
            return true;
        }

        return false;
    }

    /**
     * Reactiva un curso que reaparece en Moodle
     * 
     * @param int $idMoodle ID de Moodle del curso
     * @return bool True si se reactivó
     */
    public function reactivarCurso(int $idMoodle): bool {
        $stmt = $this->db->prepare(
            "UPDATE cursos 
             SET visible = 1, updated_at = NOW() 
             WHERE id_moodle = ? AND visible = 0"
        );
        $stmt->execute([$idMoodle]);

        if ($stmt->rowCount() > 0) {
            LoggerService::audit(null, 'COURSE_REACTIVATED', "Course:moodle:$idMoodle", [
                'reason' => 'reappeared_in_moodle'
            ]);
            return true;
        }

        return false;
    }

    /**
     * Obtiene resumen de entidades huérfanas sin realizar cambios
     * Útil para reportes y dashboards
     * 
     * @return array Conteo de entidades huérfanas por tipo
     */
    public function obtenerResumenHuerfanos(): array {
        $result = [
            'usuarios_suspendidos' => 0,
            'cursos_ocultos' => 0,
            'matriculas_suspendidas' => 0,
            'ultima_limpieza' => null
        ];

        // Contar usuarios suspendidos que vinieron de Moodle
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM usuarios WHERE id_moodle IS NOT NULL AND suspended = 1"
        );
        $result['usuarios_suspendidos'] = (int)$stmt->fetchColumn();

        // Contar cursos ocultos
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM cursos WHERE visible = 0"
        );
        $result['cursos_ocultos'] = (int)$stmt->fetchColumn();

        // Contar matrículas suspendidas
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM curso_matriculas WHERE estado = 'suspendido'"
        );
        $result['matriculas_suspendidas'] = (int)$stmt->fetchColumn();

        // Última limpieza registrada
        $stmt = $this->db->query(
            "SELECT MAX(created_at) FROM audit_logs WHERE action LIKE '%CLEANUP%'"
        );
        $result['ultima_limpieza'] = $stmt->fetchColumn();

        return $result;
    }

    /**
     * Programar limpieza automática (para uso con cron)
     * 
     * @return int ID del job de cola creado
     */
    public function programarLimpiezaAutomatica(): int {
        $handler = json_encode([
            'class' => 'SyncCleanupJob',
            'method' => 'handle',
            'params' => []
        ]);

        $stmt = $this->db->prepare(
            "INSERT INTO queue_jobs (handler, status, created_at) VALUES (?, 'pending', NOW())"
        );
        $stmt->execute([$handler]);

        return (int)$this->db->lastInsertId();
    }
}
