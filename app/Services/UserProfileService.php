<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Servicio para gestión de perfiles de usuario (Estudiantes, Docentes, Administrativos)
 * 
 * Este servicio maneja la creación y actualización de registros en las tablas
 * de perfiles extendidos: `estudiantes`, `docentes` y `administrativos`.
 * 
 * Se activa automáticamente al procesar matrículas desde Moodle:
 * - Si un usuario tiene rol 'student' → Crea/actualiza registro en `estudiantes`
 * - Si un usuario tiene rol 'editingteacher' → Crea/actualiza registro en `docentes`
 * 
 * IDEMPOTENCIA: Usa INSERT IGNORE para evitar duplicados y errores en re-ejecuciones.
 * 
 * @version 1.1
 */
class UserProfileService extends BaseService {

    private PDO $db;
    private SyncStateDbService $stateService;

    public function __construct(PDO $db, SyncStateDbService $stateService) {
        $this->db = $db;
        $this->stateService = $stateService;
    }

    /**
     * Sincroniza perfiles de usuario basado en sus matrículas actuales
     * 
     * Este método debe ejecutarse DESPUÉS de sincronizar matrículas.
     * Lee la tabla curso_matriculas para determinar qué perfiles crear.
     * 
     * @return array Estadísticas de la operación
     */
    public function sincronizarPerfilesDesdeMatriculas(): array {
        $stats = [
            'estudiantes_created' => 0,
            'docentes_created' => 0,
            'errors' => 0
        ];

        try {
            // 1. Crear perfiles de ESTUDIANTES
            // Busca usuarios con es_estudiante=1 que NO tienen registro en tabla estudiantes
            $stats['estudiantes_created'] = $this->crearPerfilesEstudiantesFaltantes();

            // 2. Crear perfiles de DOCENTES
            // Busca usuarios con es_docente=1 que NO tienen registro en tabla docentes
            $stats['docentes_created'] = $this->crearPerfilesDocentesFaltantes();

            LoggerService::info("Perfiles sincronizados desde matrículas", $stats);

        } catch (\Exception $e) {
            $stats['errors']++;
            LoggerService::error("Error sincronizando perfiles", ['error' => $e->getMessage()]);
        }

        return $stats;
    }

    private function crearPerfilesEstudiantesFaltantes(): int {
        if ($this->stateService->shouldStop()) {
             throw new \App\Exceptions\Moodle\StopSyncException("Detenido antes de perfiles estudiantes");
        }

        // Subquery: usuarios con es_estudiante=1 que NO existen en tabla estudiantes
        $sql = "INSERT INTO estudiantes (usuario_id, created_at, updated_at)
                SELECT u.id, NOW(), NOW()
                FROM usuarios u
                WHERE u.es_estudiante = 1
                  AND NOT EXISTS (
                      SELECT 1 FROM estudiantes e WHERE e.usuario_id = u.id
                  )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return (int)$stmt->rowCount();
    }

    private function crearPerfilesDocentesFaltantes(): int {
        if ($this->stateService->shouldStop()) {
             throw new \App\Exceptions\Moodle\StopSyncException("Detenido antes de perfiles docentes");
        }

        // Subquery: usuarios con es_docente=1 que NO existen en tabla docentes
        $sql = "INSERT INTO docentes (usuario_id, created_at, updated_at)
                SELECT u.id, NOW(), NOW()
                FROM usuarios u
                WHERE u.es_docente = 1
                  AND NOT EXISTS (
                      SELECT 1 FROM docentes d WHERE d.usuario_id = u.id
                  )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return (int)$stmt->rowCount();
    }

    /**
     * Procesa un usuario individual y crea sus perfiles según sus roles de Moodle
     * 
     * Útil para sincronización granular o procesamiento en tiempo real.
     * 
     * @param int $usuarioId ID local del usuario
     * @param array $moodleRoles Roles de Moodle (ej: ['student'], ['editingteacher'])
     * @return array Estadísticas de la operación
     */
    public function crearPerfilesParaUsuario(int $usuarioId, array $moodleRoles): array {
        $stats = [
            'estudiante_created' => false,
            'docente_created' => false
        ];

        // Normalizar roles a minúsculas
        $roles = array_map('strtolower', $moodleRoles);

        // ¿Es estudiante?
        if (in_array('student', $roles)) {
            $stats['estudiante_created'] = $this->crearPerfilEstudiante($usuarioId);
        }

        // ¿Es docente? (editingteacher, teacher, o manager)
        if (array_intersect(['editingteacher', 'teacher', 'manager'], $roles)) {
            $stats['docente_created'] = $this->crearPerfilDocente($usuarioId);
        }

        return $stats;
    }

    /**
     * Crea un perfil de estudiante individual (idempotente)
     * 
     * @param int $usuarioId ID local del usuario
     * @return bool True si se creó, False si ya existía
     */
    public function crearPerfilEstudiante(int $usuarioId): bool {
        try {
            // INSERT IGNORE para idempotencia
            $stmt = $this->db->prepare(
                "INSERT IGNORE INTO estudiantes (usuario_id, created_at, updated_at)
                 VALUES (?, NOW(), NOW())"
            );
            $stmt->execute([$usuarioId]);
            
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            LoggerService::warning("Error creando perfil estudiante", [
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Crea un perfil de docente individual (idempotente)
     * 
     * @param int $usuarioId ID local del usuario
     * @return bool True si se creó, False si ya existía
     */
    public function crearPerfilDocente(int $usuarioId): bool {
        try {
            // INSERT IGNORE para idempotencia
            $stmt = $this->db->prepare(
                "INSERT IGNORE INTO docentes (usuario_id, created_at, updated_at)
                 VALUES (?, NOW(), NOW())"
            );
            $stmt->execute([$usuarioId]);
            
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            LoggerService::warning("Error creando perfil docente", [
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Actualiza datos del perfil de estudiante
     * 
     * @param int $usuarioId ID local del usuario
     * @param array $datos Datos a actualizar (legajo, carrera_principal_id, anio_ingreso)
     * @return bool True si se actualizó
     */
    public function actualizarPerfilEstudiante(int $usuarioId, array $datos): bool {
        $updates = [];
        $params = [];

        if (isset($datos['legajo'])) {
            $updates[] = "legajo = ?";
            $params[] = $datos['legajo'];
        }
        if (isset($datos['carrera_principal_id'])) {
            $updates[] = "carrera_principal_id = ?";
            $params[] = $datos['carrera_principal_id'];
        }
        if (isset($datos['anio_ingreso'])) {
            $updates[] = "anio_ingreso = ?";
            $params[] = $datos['anio_ingreso'];
        }

        if (empty($updates)) {
            return false;
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $usuarioId;

        $sql = "UPDATE estudiantes SET " . implode(', ', $updates) . " WHERE usuario_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Actualiza datos del perfil de docente
     * 
     * @param int $usuarioId ID local del usuario
     * @param array $datos Datos a actualizar (titulo_profesional, especialidad, tipo_contrato)
     * @return bool True si se actualizó
     */
    public function actualizarPerfilDocente(int $usuarioId, array $datos): bool {
        $updates = [];
        $params = [];

        if (isset($datos['titulo_profesional'])) {
            $updates[] = "titulo_profesional = ?";
            $params[] = $datos['titulo_profesional'];
        }
        if (isset($datos['especialidad'])) {
            $updates[] = "especialidad = ?";
            $params[] = $datos['especialidad'];
        }
        if (isset($datos['tipo_contrato'])) {
            $updates[] = "tipo_contrato = ?";
            $params[] = $datos['tipo_contrato'];
        }

        if (empty($updates)) {
            return false;
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $usuarioId;

        $sql = "UPDATE docentes SET " . implode(', ', $updates) . " WHERE usuario_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Obtiene información completa de un usuario con todos sus perfiles
     * 
     * @param int $usuarioId ID local del usuario
     * @return array|null Datos del usuario con perfiles, o null si no existe
     */
    public function obtenerUsuarioConPerfiles(int $usuarioId): ?array {
        $sql = "SELECT 
                    u.*,
                    e.id AS estudiante_id,
                    e.legajo,
                    e.carrera_principal_id,
                    e.anio_ingreso,
                    d.id AS docente_id,
                    d.titulo_profesional,
                    d.especialidad,
                    d.tipo_contrato,
                    a.id AS administrativo_id,
                    a.cargo,
                    a.departamento
                FROM usuarios u
                LEFT JOIN estudiantes e ON u.id = e.usuario_id
                LEFT JOIN docentes d ON u.id = d.usuario_id
                LEFT JOIN administrativos a ON u.id = a.usuario_id
                WHERE u.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$usuarioId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Verifica y sincroniza un usuario individual al procesar una matrícula
     * 
     * Este método se puede llamar durante el procesamiento de matrículas para
     * crear el usuario si no existe y asignar el perfil correspondiente.
     * 
     * @param array $moodleUser Datos del usuario desde Moodle
     * @param string $rolMoodle Rol en el curso (student, editingteacher, etc.)
     * @return array ['usuario_id' => int, 'created' => bool, 'profile_created' => bool]
     */
    public function procesarUsuarioDeMatricula(array $moodleUser, string $rolMoodle): array {
        $result = [
            'usuario_id' => null,
            'usuario_created' => false,
            'profile_created' => false
        ];

        // 1. Verificar si el usuario ya existe localmente
        $stmt = $this->db->prepare(
            "SELECT id, es_estudiante, es_docente FROM usuarios WHERE id_moodle = ?"
        );
        $stmt->execute([$moodleUser['id']]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            // Usuario existe
            $result['usuario_id'] = (int)$existingUser['id'];
            
            // Actualizar flags si es necesario
            $this->actualizarFlagsUsuario(
                $result['usuario_id'], 
                $rolMoodle,
                (bool)$existingUser['es_estudiante'],
                (bool)$existingUser['es_docente']
            );
        } else {
            // Crear usuario nuevo
            $result['usuario_id'] = $this->crearUsuarioDesdeMatricula($moodleUser, $rolMoodle);
            $result['usuario_created'] = true;
        }

        // 2. Crear perfil si no existe
        if ($result['usuario_id']) {
            $profileResult = $this->crearPerfilesParaUsuario(
                $result['usuario_id'], 
                [$rolMoodle]
            );
            $result['profile_created'] = $profileResult['estudiante_created'] || $profileResult['docente_created'];
        }

        return $result;
    }

    /**
     * Crea un usuario nuevo a partir de datos de matrícula
     * 
     * @param array $moodleUser Datos del usuario desde Moodle
     * @param string $rolMoodle Rol en el curso
     * @return int ID del usuario creado
     */
    private function crearUsuarioDesdeMatricula(array $moodleUser, string $rolMoodle): int {
        $esEstudiante = (strtolower($rolMoodle) === 'student') ? 1 : 0;
        $esDocente = in_array(strtolower($rolMoodle), ['editingteacher', 'teacher', 'manager']) ? 1 : 0;

        $dataForHash = json_encode([
            'username' => $moodleUser['username'] ?? '',
            'firstname' => $moodleUser['firstname'] ?? '',
            'lastname' => $moodleUser['lastname'] ?? '',
            'email' => $moodleUser['email'] ?? '',
            'auth' => $moodleUser['auth'] ?? 'manual',
            'suspended' => $moodleUser['suspended'] ?? 0
        ], JSON_UNESCAPED_UNICODE);

        // Generar contraseña: username + 123*
        $username = $moodleUser['username'] ?? '';
        $password = password_hash($username . '123*', PASSWORD_BCRYPT);

        $sql = "INSERT INTO usuarios (
                    id_moodle, username, email, password, nombre, apellido,
                    es_estudiante, es_docente, auth_method, suspended, data_hash,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $moodleUser['id'],
            $username,
            $moodleUser['email'] ?? '',
            $password,
            $moodleUser['firstname'] ?? '',
            $moodleUser['lastname'] ?? '',
            $esEstudiante,
            $esDocente,
            $moodleUser['auth'] ?? 'manual',
            $moodleUser['suspended'] ?? 0,
            md5($dataForHash)
        ]);

        $userId = (int)$this->db->lastInsertId();

        // Registrar en audit_logs
        LoggerService::audit(null, 'USER_CREATED_FROM_ENROLLMENT', "User:$userId", [
            'id_moodle' => $moodleUser['id'],
            'username' => $username,
            'rol_moodle' => $rolMoodle,
            'source' => 'MoodleSync'
        ]);

        return $userId;
    }

    /**
     * Actualiza los flags es_estudiante/es_docente de un usuario existente
     * 
     * @param int $usuarioId ID local del usuario
     * @param string $rolMoodle Nuevo rol detectado
     * @param bool $yaEsEstudiante Flag actual
     * @param bool $yaEsDocente Flag actual
     */
    private function actualizarFlagsUsuario(
        int $usuarioId, 
        string $rolMoodle,
        bool $yaEsEstudiante,
        bool $yaEsDocente
    ): void {
        $updates = [];

        $rolLower = strtolower($rolMoodle);

        // Si es student y no tenía el flag
        if ($rolLower === 'student' && !$yaEsEstudiante) {
            $updates[] = "es_estudiante = 1";
        }

        // Si es docente y no tenía el flag
        if (in_array($rolLower, ['editingteacher', 'teacher', 'manager']) && !$yaEsDocente) {
            $updates[] = "es_docente = 1";
        }

        if (!empty($updates)) {
            $sql = "UPDATE usuarios SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$usuarioId]);
        }
    }
}
