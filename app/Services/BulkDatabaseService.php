<?php
declare(strict_types=1);

namespace App\Services;

use Config\MoodleWS;
use PDO;

/**
 * Servicio de operaciones masivas de base de datos v2.0
 * 
 * Optimizado para insertar/actualizar miles de registros
 * de forma eficiente usando bulk operations.
 * 
 * Mejoras v2.0:
 * - Early-stop en errores consecutivos
 * - Logging agrupado de errores (evita spam)
 * - Contador de errores por tipo
 * - Password hashing optimizado
 * 
 * @version 2.0
 */
class BulkDatabaseService extends BaseService {

    private PDO $db;
    
    /** @var int Tamaño de batch para INSERT */
    private int $batchSize;

    /** @var int Contador de errores consecutivos */
    private int $consecutiveErrors = 0;

    /** @var int Umbral para early-stop */
    private const MAX_CONSECUTIVE_ERRORS = 3;

    /** @var array Contadores de errores por tipo */
    private array $errorCounts = [];

    /** @var bool Flag para indicar si se abortó por errores */
    private bool $earlyStopTriggered = false;

    private SyncStateDbService $stateService;

    public function __construct(PDO $db, SyncStateDbService $stateService) {
        $this->db = $db;
        $this->stateService = $stateService;
        $this->batchSize = \Config\MoodleWS::BULK_INSERT_SIZE;
    }

    /**
     * Inserta múltiples usuarios en bulk con UPSERT
     * 
     * @param array $users Lista de usuarios con estructura normalizada
     * @return array Estadísticas de la operación
     */
    public function bulkUpsertUsers(array $users, array $options = []): array {
        if (empty($users)) {
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $chunks = array_chunk($users, $this->batchSize);

        $totalChunks = count($chunks);
        $currentChunk = 0;

        foreach ($chunks as $chunk) {
            $currentChunk++;
            // Reportar progreso cada 5 chunks para no saturar BD
            if ($currentChunk % 5 === 0 || $currentChunk === $totalChunks) {
                if ($this->stateService->shouldStop()) {
                    $stats['aborted'] = true;
                    LoggerService::warning("bulkUpsertUsers: Detención solicitada durante guardado de chunks");
                    return $stats;
                }
                // Rango visual en la UI estipulado de 55% a 70% para esta tarea
                $percent = 55 + (int)(($currentChunk / $totalChunks) * 15);
                $this->stateService->updateProgress($percent, "Guardando usuarios: " . ($currentChunk * $this->batchSize) . " de " . count($users));
            }

            try {
                $result = $this->executeUserBulkUpsert($chunk, $options);
                $stats['inserted'] += $result['inserted'];
                $stats['updated'] += $result['updated'];
            } catch (\Exception $e) {
                if ($e instanceof \App\Exceptions\Moodle\StopSyncException || $e->getMessage() === 'USER_STOP_REQUESTED') {
                    $stats['aborted'] = true;
                    throw $e;
                }
                $stats['errors'] += count($chunk);
                $this->stateService->recordErrorSummary('usuarios', 'bulk_upsert_failure', $e->getMessage());
                LoggerService::error("Bulk upsert users failed", [
                    'error' => $e->getMessage(),
                    'chunk_size' => count($chunk)
                ]);
            }
        }

        return $stats;
    }

    /**
     * Ejecuta el UPSERT masivo de un chunk de usuarios
     */
    private function executeUserBulkUpsert(array $users, array $options = []): array {
        $regeneratePasswords = $options['regenerate_passwords'] ?? false;

        // Construir los hashes para detectar cambios
        $userHashes = [];
        $userMoodleIds = [];
        
        foreach ($users as $user) {
            $dataForHash = json_encode([
                'username' => $user['username'] ?? '',
                'firstname' => $user['firstname'] ?? '',
                'lastname' => $user['lastname'] ?? '',
                'email' => $user['email'] ?? '',
                'auth' => $user['auth'] ?? 'manual',
                'suspended' => $user['suspended'] ?? 0
            ], JSON_UNESCAPED_UNICODE);
            
            $userHashes[$user['id']] = md5($dataForHash);
            $userMoodleIds[] = $user['id'];
        }

        // Obtener hashes Y passwords existentes en una sola query
        $placeholders = implode(',', array_fill(0, count($userMoodleIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT id_moodle, data_hash, password FROM usuarios WHERE id_moodle IN ($placeholders)"
        );
        $stmt->execute($userMoodleIds);
        $existingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mapear id_moodle => [hash, has_password]
        $existing = [];
        foreach ($existingRows as $row) {
            $existing[$row['id_moodle']] = [
                'hash' => $row['data_hash'],
                'has_password' => !empty($row['password'])
            ];
        }

        // Separar: usuarios a insertar vs actualizar vs skip
        $toInsert = [];
        $toUpdate = [];
        $skipped = 0;

        foreach ($users as $user) {
            $id = $user['id'];
            $newHash = $userHashes[$id];
            
            $exists = isset($existing[$id]);
            $currentData = $exists ? $existing[$id] : null;
            
            // Decidir si generar password
            $shouldGeneratePassword = false;
            if (!$exists) {
                $shouldGeneratePassword = true; // Nuevo usuario -> Siempre
            } elseif (!$currentData['has_password']) {
                $shouldGeneratePassword = true; // Usuario sin password -> Siempre
            } elseif ($regeneratePasswords) {
                $shouldGeneratePassword = true; // Forzado -> Sí
            }

            if (!$exists) {
                // Nuevo usuario
                $toInsert[] = $this->prepareUserData($user, $newHash, true);
            } elseif (($currentData['hash'] ?? '') !== $newHash || $shouldGeneratePassword) {
                // Usuario cambió O necesitamos actualizar password
                // Si solo cambia hash pero no password, prepareUserData(..., false)
                // Si cambia password, prepareUserData(..., true)
                $toUpdate[] = $this->prepareUserData($user, $newHash, $shouldGeneratePassword);
            } else {
                // Sin cambios
                $skipped++;
            }
        }

        // Ejecutar INSERT masivo
        $insertCount = 0;
        if (!empty($toInsert)) {
            $insertCount = $this->executeBulkInsert('usuarios', $toInsert, [
                'id_moodle', 'username', 'email', 'password', 'nombre', 'apellido', 
                'auth_method', 'suspended', 'data_hash', 'last_sync_at', 
                'created_at', 'updated_at'
            ]);
        }

        // Ejecutar UPDATE masivo (usando CASE WHEN para eficiencia)
        $updateCount = 0;
        if (!empty($toUpdate)) {
            $updateCount = $this->executeBulkUpdate('usuarios', $toUpdate, 'id_moodle');
        }

        return [
            'inserted' => $insertCount,
            'updated' => $updateCount,
            'skipped' => $skipped
        ];
    }

    /**
     * Prepara los datos de un usuario para bulk insert/update
     */
    private function prepareUserData(array $user, string $hash, bool $generatePassword = false): array {
        $now = date('Y-m-d H:i:s');
        
        $data = [
            'id_moodle' => $user['id'],
            'username' => $user['username'] ?? '',
            'email' => $user['email'] ?? '',
            'nombre' => $user['firstname'] ?? '',
            'apellido' => $user['lastname'] ?? '',
            'auth_method' => $user['auth'] ?? 'manual',
            'suspended' => $user['suspended'] ?? 0,
            'data_hash' => $hash,
            'last_sync_at' => $now,
            // created_at solo se usa efectivamente en INSERT, update ignora columnas no mapeadas en executeBulkUpdate si no somos cuidadosos
            // Pero executeBulkUpdate itera keys, así que mejor no incluir created_at aquí si no es necesario, o filtrarlo luego.
            // Para simplificar, lo dejamos, y executeBulkUpdate ya tiene lógica para filtrar created_at
            'updated_at' => $now
        ];

        // Añadir created_at (executeBulkInsert lo usará, executeBulkUpdate lo filtrará)
        $data['created_at'] = $now;

        if ($generatePassword) {
            // Estrategia de contraseña solicitada:
            // username + '123*'
            // Ejemplo: jperez -> jperez123*
            
            $base = $user['username'] ?? '';
            
            // Fallback al email si no hay username (raro en Moodle)
            if (empty($base)) {
                $base = $user['email'] ?? 'usuario'; // Fallback final
                // Si es email, usamos la parte antes del @ para mantenerlo limpio, o el email completo si se prefiere.
                // El requerimiento dice "username como base". Si no hay username, intentamos acercarnos.
                $parts = explode('@', $base);
                $base = $parts[0];
            }
            
            // Eliminar espacios por si acaso
            $base = trim($base);
            
            // Patrón: username + 123 + *
            $plainPassword = $base . '123*';
            $data['password'] = password_hash($plainPassword, PASSWORD_BCRYPT);
        }

        return $data;
    }

    /**
     * Ejecuta un INSERT masivo utilizando INSERT ... VALUES (...), (...), ...
     * 
     * NOTA: Este método está diseñado PRINCIPALMENTE para registros nuevos.
     * La cláusula ON DUPLICATE KEY UPDATE solo actualiza `updated_at`,
     * no los valores reales. Para upsert real, usar executeBulkUpdate o queries específicas.
     * 
     * @param string $table Nombre de la tabla
     * @param array $records Registros a insertar
     * @param array $columns Columnas en orden
     * @return int Número de registros insertados
     */
    public function executeBulkInsert(string $table, array $records, array $columns): int {
        if (empty($records)) {
            return 0;
        }

        $columnList = implode(', ', array_map(fn($c) => "`$c`", $columns));
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($records), $placeholders));

        $sql = "INSERT INTO `$table` ($columnList) VALUES $allPlaceholders
                ON DUPLICATE KEY UPDATE updated_at = NOW()";

        $values = [];
        foreach ($records as $record) {
            foreach ($columns as $col) {
                $values[] = $record[$col] ?? null;
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        
        return count($records);
    }

    /**
     * Ejecuta UPDATE masivo usando transacción y batch de queries
     */
    private function executeBulkUpdate(string $table, array $records, string $keyColumn): int {
        if (empty($records)) {
            return 0;
        }

        $this->db->beginTransaction();
        $count = 0;

        try {
            // Preparar statement una sola vez
            $columns = array_keys($records[0]);
            $setClauses = [];
            foreach ($columns as $col) {
                if ($col !== $keyColumn && $col !== 'created_at') {
                    $setClauses[] = "`$col` = :$col";
                }
            }
            $setClause = implode(', ', $setClauses);
            
            $sql = "UPDATE `$table` SET $setClause, updated_at = NOW() WHERE `$keyColumn` = :$keyColumn";
            $stmt = $this->db->prepare($sql);

            foreach ($records as $index => $record) {
                // Verificar detención cada 100 updates para no afectar rendimiento
                if ($index % 100 === 0 && $this->stateService->shouldStop()) {
                    throw new \App\Exceptions\Moodle\StopSyncException("USER_STOP_REQUESTED");
                }
                $params = [];
                foreach ($columns as $col) {
                    if ($col !== 'created_at') {
                        $params[$col] = $record[$col] ?? null;
                    }
                }
                $stmt->execute($params);
                $count++;
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $count;
    }

    /**
     * Bulk UPSERT para cursos
     */
    public function bulkUpsertCourses(array $courses): array {
        if (empty($courses)) {
            return ['processed' => 0];
        }

        $stats = ['processed' => 0, 'errors' => 0];
        $chunks = array_chunk($courses, $this->batchSize);

        foreach ($chunks as $chunk) {
            if ($this->stateService->shouldStop()) {
                $stats['aborted'] = true;
                throw new \App\Exceptions\Moodle\StopSyncException("Detención solicitada en cursos");
            }

            try {
                $values = [];
                $placeholders = [];
                
                foreach ($chunk as $course) {
                    $placeholders[] = "(?, ?, ?, ?, ?, ?, NOW(), NOW())";
                    $values[] = $course['id'];
                    $values[] = $course['categoryid'] ?? 0;
                    $values[] = $course['fullname'] ?? '';
                    $values[] = $course['shortname'] ?? '';
                    $values[] = isset($course['startdate']) ? date('Y-m-d H:i:s', $course['startdate']) : null;
                    $values[] = $course['visible'] ?? 1;
                }

                $sql = "INSERT INTO cursos (id_moodle, id_categoria_moodle, fullname, shortname, start_date, visible, created_at, updated_at)
                    VALUES " . implode(', ', $placeholders) . "
                    ON DUPLICATE KEY UPDATE
                    fullname = VALUES(fullname),
                    shortname = VALUES(shortname),
                    id_categoria_moodle = VALUES(id_categoria_moodle),
                    visible = VALUES(visible),
                    updated_at = NOW()";

                $stmt = $this->db->prepare($sql);
                $stmt->execute($values);
                $stats['processed'] += count($chunk);

            } catch (\Exception $e) {
                if ($e instanceof \App\Exceptions\Moodle\StopSyncException) {
                    throw $e;
                }
                $stats['errors'] += count($chunk);
                $this->stateService->recordErrorSummary('cursos', 'bulk_upsert_failure', $e->getMessage());
                LoggerService::error("Bulk upsert courses failed", ['error' => $e->getMessage()]);
            }
        }

        return $stats;
    }

    /**
     * Bulk UPSERT para cohortes
     */
    public function bulkUpsertCohorts(array $cohorts): array {
        if (empty($cohorts)) {
            return ['processed' => 0];
        }

        $stats = ['processed' => 0, 'errors' => 0];
        
        $values = [];
        $placeholders = [];
        
        foreach ($cohorts as $cohort) {
            $placeholders[] = "(?, ?, ?, ?, NOW(), NOW())";
            $values[] = $cohort['id'];
            $values[] = $cohort['name'] ?? '';
            $values[] = $cohort['idnumber'] ?? null;
            $values[] = $cohort['visible'] ?? 1;
        }

        try {
            if ($this->stateService->shouldStop()) throw new \App\Exceptions\Moodle\StopSyncException("Detenido en cohortes");
            $sql = "INSERT INTO cohortes (id_moodle, nombre, idnumber, visible, created_at, updated_at)
                    VALUES " . implode(', ', $placeholders) . "
                    ON DUPLICATE KEY UPDATE
                    nombre = VALUES(nombre),
                    idnumber = VALUES(idnumber),
                    visible = VALUES(visible),
                    updated_at = NOW()";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            $stats['processed'] = count($cohorts);

        } catch (\Exception $e) {
            if ($e instanceof \App\Exceptions\Moodle\StopSyncException) throw $e;
            $stats['errors'] = count($cohorts);
            LoggerService::error("Bulk upsert cohorts failed", ['error' => $e->getMessage()]);
        }

        return $stats;
    }

    /**
     * Bulk UPSERT para categorías con detección de cambios via data_hash
     * 
     * IDEMPOTENCIA: Compara hashes antes de actualizar para evitar 
     * escrituras innecesarias y mantener timestamps precisos.
     * 
     * @param array $categories Lista de categorías desde Moodle
     * @return array Estadísticas detalladas de la operación
     */
    public function bulkUpsertCategories(array $categories): array {
        if (empty($categories)) {
            return ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $stats = ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        
        // 1. Calcular hashes para todas las categorías nuevas
        $categoryHashes = [];
        $categoryIds = [];
        foreach ($categories as $cat) {
            $dataForHash = json_encode([
                'name' => $cat['name'] ?? '',
                'parent' => $cat['parent'] ?? 0,
                'depth' => $cat['depth'] ?? 0,
                'path' => $cat['path'] ?? '',
                'idnumber' => $cat['idnumber'] ?? ''
            ], JSON_UNESCAPED_UNICODE);
            
            $categoryHashes[$cat['id']] = md5($dataForHash);
            $categoryIds[] = $cat['id'];
        }
        
        // 2. Obtener hashes existentes de la BD
        $existingHashes = [];
        if (!empty($categoryIds)) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT id, data_hash FROM raw_moodle_categorias WHERE id IN ($placeholders)"
            );
            $stmt->execute($categoryIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existingHashes[$row['id']] = $row['data_hash'];
            }
        }
        
        // 3. Separar: insertar vs actualizar vs saltar
        $toInsert = [];
        $toUpdate = [];
        
        foreach ($categories as $cat) {
            $id = $cat['id'];
            $newHash = $categoryHashes[$id];
            
            if (!isset($existingHashes[$id])) {
                // Nueva categoría
                $toInsert[] = [
                    'id' => $id,
                    'name' => $cat['name'] ?? '',
                    'parent_id' => $cat['parent'] ?? 0,
                    'depth' => $cat['depth'] ?? 0,
                    'path' => $cat['path'] ?? '',
                    'idnumber' => $cat['idnumber'] ?? null,
                    'data_hash' => $newHash
                ];
            } elseif ($existingHashes[$id] !== $newHash) {
                // Categoría cambió
                $toUpdate[] = [
                    'id' => $id,
                    'name' => $cat['name'] ?? '',
                    'parent_id' => $cat['parent'] ?? 0,
                    'depth' => $cat['depth'] ?? 0,
                    'path' => $cat['path'] ?? '',
                    'idnumber' => $cat['idnumber'] ?? null,
                    'data_hash' => $newHash
                ];
            } else {
                // Sin cambios
                $stats['skipped']++;
            }
        }
        
        // 4. Ejecutar INSERT masivo
        if (!empty($toInsert)) {
            $chunks = array_chunk($toInsert, $this->batchSize);
            foreach ($chunks as $chunk) {
                if ($this->stateService->shouldStop()) throw new \App\Exceptions\Moodle\StopSyncException("Stop en categorías");
                try {
                    $values = [];
                    $placeholders = [];
                    
                    foreach ($chunk as $cat) {
                        $placeholders[] = "(?, ?, ?, ?, ?, ?, ?, NOW())";
                        $values[] = $cat['id'];
                        $values[] = $cat['name'];
                        $values[] = $cat['parent_id'];
                        $values[] = $cat['depth'];
                        $values[] = $cat['path'];
                        $values[] = $cat['idnumber'];
                        $values[] = $cat['data_hash'];
                    }

                    $sql = "INSERT INTO raw_moodle_categorias 
                            (id, name, parent_id, depth, path, idnumber, data_hash, updated_at)
                            VALUES " . implode(', ', $placeholders);

                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($values);
                    $stats['inserted'] += count($chunk);

                } catch (\Exception $e) {
                    if ($e instanceof \App\Exceptions\Moodle\StopSyncException) throw $e;
                    $stats['errors'] += count($chunk);
                    LoggerService::error("Bulk insert categories failed", ['error' => $e->getMessage()]);
                }
            }
        }
        
        // 5. Ejecutar UPDATE masivo (solo los que cambiaron)
        if (!empty($toUpdate)) {
            try {
                $stats['updated'] += $this->executeBulkUpdate('raw_moodle_categorias', $toUpdate, 'id');
            } catch (\Exception $e) {
                if ($e instanceof \App\Exceptions\Moodle\StopSyncException) throw $e;
                $stats['errors'] += count($toUpdate);
                LoggerService::error("Bulk update categories failed", ['error' => $e->getMessage()]);
            }
        }
        
        $stats['processed'] = $stats['inserted'] + $stats['updated'] + $stats['skipped'];
        
        return $stats;
    }

    /**
     * Bulk INSERT para matrículas (enrollments)
     * 
     * Mejoras v2.0:
     * - Early-stop si hay más de 3 errores consecutivos
     * - Logging agrupado de errores
     */
    public function bulkUpsertEnrollments(array $enrollments): array {
        if (empty($enrollments)) {
            return ['processed' => 0, 'errors' => 0, 'aborted' => false];
        }

        $stats = ['processed' => 0, 'errors' => 0, 'aborted' => false];
        $chunks = array_chunk($enrollments, $this->batchSize);
        $this->consecutiveErrors = 0;
        $this->earlyStopTriggered = false;
        $this->errorCounts = [];
        $totalChunks = count($chunks);
        $processedChunks = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            // Verificar detención manual
            if ($this->stateService->shouldStop()) {
                $stats['aborted'] = true; // Mantener por si acaso el caller atrapa y mira stats
                throw new \App\Exceptions\Moodle\StopSyncException("Detenido por el usuario en matrículas");
            }

            // Early-stop check
            if ($this->consecutiveErrors >= self::MAX_CONSECUTIVE_ERRORS) {
                $this->earlyStopTriggered = true;
                $stats['aborted'] = true;
                $remainingRecords = ($totalChunks - $processedChunks) * $this->batchSize;
                $this->logGroupedErrors('enrollments', $remainingRecords);
                LoggerService::warning("BulkDatabaseService: Early-stop activado en enrollments", [
                    'consecutive_errors' => $this->consecutiveErrors,
                    'chunks_processed' => $processedChunks,
                    'chunks_total' => $totalChunks,
                    'records_remaining' => $remainingRecords
                ]);
                break;
            }

            try {
                $values = [];
                $placeholders = [];
                
                foreach ($chunk as $enroll) {
                    // Estructura: ['course_id' => X, 'user_id' => Y, 'role' => 'student']
                    $placeholders[] = "(
                        (SELECT id FROM cursos WHERE id_moodle = ? LIMIT 1),
                        (SELECT id FROM usuarios WHERE id_moodle = ? LIMIT 1),
                        ?,
                        NOW(), NOW()
                    )";
                    $values[] = $enroll['course_id'];
                    $values[] = $enroll['user_id'];
                    $values[] = $enroll['role'] ?? 'student';
                }

                $sql = "INSERT IGNORE INTO curso_matriculas (curso_id, usuario_id, rol, created_at, updated_at)
                        VALUES " . implode(', ', $placeholders);

                $stmt = $this->db->prepare($sql);
                $stmt->execute($values);
                $stats['processed'] += count($chunk);
                
                // Reset contador de errores en éxito
                $this->consecutiveErrors = 0;
                $processedChunks++;

            } catch (\Exception $e) {
                if ($e instanceof \App\Exceptions\Moodle\StopSyncException) {
                    $stats['aborted'] = true;
                    throw $e;
                }
                $stats['errors'] += count($chunk);
                $this->consecutiveErrors++;
                $processedChunks++;
                
                // Contar error por tipo (agrupado)
                $errorType = $this->normalizeDbError($e->getMessage());
                if (!isset($this->errorCounts[$errorType])) {
                    $this->errorCounts[$errorType] = 0;
                }
                $this->errorCounts[$errorType] += count($chunk);
            }
        }

        // Log resumen final (agrupado)
        if (!empty($this->errorCounts)) {
            $this->logGroupedErrors('enrollments', 0);
        }

        return $stats;
    }

    /**
     * Sincroniza el estado de las matrículas de un curso (Suspende las que no vienen en la lista activa)
     * Resuelve el problema de "Zombie Enrollments"
     * 
     * @param int $courseId ID del curso (local)
     * @param array $activeUserIds Lista de IDs de usuarios (locales) que están activos en Moodle
     * @return int Número de matrículas suspendidas
     */
    /**
     * Sincroniza el estado de las matrículas de un curso (Suspende las que no vienen en la lista activa)
     * Resuelve el problema de "Zombie Enrollments" usando IDs de Moodle directamente.
     * 
     * @param int $moodleCourseId ID del curso (Moodle)
     * @param array $activeMoodleUserIds Lista de IDs de usuarios (Moodle) que están activos
     * @return int Número de matrículas suspendidas
     */
    public function bulkSuspendOrphanEnrollments(int $moodleCourseId, array $activeMoodleUserIds): int {
        $safeId = preg_replace('/[^a-zA-Z0-9_]/', '', uniqid('', true));
        $tempTableName = "tmp_active_users_" . $safeId;
        try {
            $this->db->exec("CREATE TEMPORARY TABLE $tempTableName (id_moodle INT UNSIGNED PRIMARY KEY)");
            if (!empty($activeMoodleUserIds)) {
                $chunks = array_chunk($activeMoodleUserIds, 500);
                foreach ($chunks as $chunk) {
                    if ($this->stateService->shouldStop()) {
                        throw new \App\Exceptions\Moodle\StopSyncException("Stop in orphans");
                    }
                    $placeholders = implode(',', array_fill(0, count($chunk), '(?)'));
                    $sql = "INSERT INTO $tempTableName (id_moodle) VALUES $placeholders";
                    $this->db->prepare($sql)->execute($chunk);
                }
            }
            $sql = "UPDATE curso_matriculas cm
                    JOIN cursos c ON cm.curso_id = c.id
                    JOIN usuarios u ON cm.usuario_id = u.id
                    SET cm.estado = 'suspendido', cm.updated_at = NOW()
                    WHERE c.id_moodle = :moodleCourseId 
                      AND cm.estado = 'activo'
                      AND NOT EXISTS (
                          SELECT 1 FROM $tempTableName t WHERE t.id_moodle = u.id_moodle
                      )";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':moodleCourseId' => $moodleCourseId]);
            $count = $stmt->rowCount();
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS $tempTableName");
            return $count;
        } catch (\Exception $e) {
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS $tempTableName");
            if ($e instanceof \App\Exceptions\Moodle\StopSyncException) {
                throw $e;
            }
            LoggerService::error("Error suspendiendo matrículas zombies", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Normaliza mensajes de error de BD para agrupación
     */
    private function normalizeDbError(string $error): string {
        if (stripos($error, 'Unknown column') !== false) {
            // Extraer nombre de columna
            if (preg_match("/Unknown column '([^']+)'/", $error, $matches)) {
                return "Missing column: {$matches[1]}";
            }
            return 'Unknown column error';
        }
        if (stripos($error, 'Duplicate entry') !== false) {
            return 'Duplicate entry';
        }
        if (stripos($error, 'foreign key') !== false) {
            return 'Foreign key constraint';
        }
        if (stripos($error, 'Deadlock') !== false) {
            return 'Deadlock detected';
        }
        if (stripos($error, 'Lock wait timeout') !== false) {
            return 'Lock timeout';
        }
        if (strlen($error) > 80) {
            return substr($error, 0, 80) . '...';
        }
        return $error;
    }

    /**
     * Registra errores agrupados en un solo log
     */
    private function logGroupedErrors(string $entity, int $skippedRecords): void {
        $totalErrors = array_sum($this->errorCounts);
        if ($totalErrors === 0) {
            return;
        }

        LoggerService::error("BulkDatabaseService: Errores en $entity (resumen agrupado)", [
            'total_errors' => $totalErrors,
            'error_breakdown' => $this->errorCounts,
            'early_stop' => $this->earlyStopTriggered,
            'records_skipped' => $skippedRecords
        ]);
    }

    /**
     * Bulk INSERT para calificaciones
     * 
     * SEGURIDAD v3.2: Soporta hash de integridad para auditoría
     * 
     * @param array $grades Array de calificaciones sanitizadas
     * @return array Estadísticas de la operación
     */
    public function bulkUpsertGrades(array $grades): array {
        if (empty($grades)) {
            return ['processed' => 0, 'errors' => 0, 'inserted' => 0, 'updated' => 0];
        }

        $stats = ['processed' => 0, 'errors' => 0, 'inserted' => 0, 'updated' => 0];
        $chunks = array_chunk($grades, $this->batchSize);
        static $cachedHashCheck = null;
        if ($cachedHashCheck === null) {
            $cachedHashCheck = $this->columnExists('calificaciones', 'data_hash');
        }
        $hasDataHashColumn = $cachedHashCheck;

        foreach ($chunks as $chunk) {
            if ($this->stateService->shouldStop()) {
                $stats['aborted'] = true;
                throw new \App\Exceptions\Moodle\StopSyncException("Stop in grades");
            }
            try {
                $values = [];
                $placeholders = [];
                foreach ($chunk as $grade) {
                    if (!isset($grade['matricula_id']) || !isset($grade['item_id'])) {
                        continue;
                    }
                    if ($hasDataHashColumn) {
                        $placeholders[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                        $values[] = (int)$grade['matricula_id'];
                        $values[] = (int)$grade['item_id'];
                        $values[] = mb_substr($grade['item_name'] ?? 'Item', 0, 255);
                        $values[] = (int)($grade['es_nota_final'] ?? 0);
                        $values[] = $grade['grade'] ?? null;
                        $values[] = $grade['grade_max'] ?? 100;
                        $values[] = mb_substr($grade['feedback'] ?? '', 0, 5000);
                        $values[] = $grade['date_graded'] ?? null;
                        $values[] = $grade['data_hash'] ?? null;
                    } else {
                        $placeholders[] = "(?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                        $values[] = (int)$grade['matricula_id'];
                        $values[] = (int)$grade['item_id'];
                        $values[] = mb_substr($grade['item_name'] ?? 'Item', 0, 255);
                        $values[] = (int)($grade['es_nota_final'] ?? 0);
                        $values[] = $grade['grade'] ?? null;
                        $values[] = $grade['grade_max'] ?? 100;
                        $values[] = mb_substr($grade['feedback'] ?? '', 0, 5000);
                        $values[] = $grade['date_graded'] ?? null;
                    }
                }
                if (empty($placeholders)) continue;
                if ($hasDataHashColumn) {
                    $sql = "INSERT INTO calificaciones (matricula_id, id_moodle_item, item_nombre, es_nota_final, calificacion_final, calificacion_maxima, feedback, fecha_modificacion, data_hash, created_at, updated_at) VALUES " . implode(', ', $placeholders) . " ON DUPLICATE KEY UPDATE calificacion_final = VALUES(calificacion_final), calificacion_maxima = VALUES(calificacion_maxima), item_nombre = VALUES(item_nombre), es_nota_final = VALUES(es_nota_final), feedback = VALUES(feedback), fecha_modificacion = VALUES(fecha_modificacion), data_hash = VALUES(data_hash), updated_at = NOW()";
                } else {
                    $sql = "INSERT INTO calificaciones (matricula_id, id_moodle_item, item_nombre, es_nota_final, calificacion_final, calificacion_maxima, feedback, fecha_modificacion, created_at, updated_at) VALUES " . implode(', ', $placeholders) . " ON DUPLICATE KEY UPDATE calificacion_final = VALUES(calificacion_final), calificacion_maxima = VALUES(calificacion_maxima), item_nombre = VALUES(item_nombre), es_nota_final = VALUES(es_nota_final), feedback = VALUES(feedback), fecha_modificacion = VALUES(fecha_modificacion), updated_at = NOW()";
                }
                $this->db->prepare($sql)->execute($values);
                $stats['processed'] += count($chunk);
                $stats['inserted'] += count($chunk);
            } catch (\Exception $e) {
                $stats['errors'] += count($chunk);
                LoggerService::error("Bulk upsert grades failed", ['error' => $e->getMessage()]);
            }
        }
        return $stats;
    }

    /**
     * Verifica si una columna existe en una tabla
     * Utilizado para compatibilidad con migraciones pendientes
     * 
     * @param string $table Nombre de la tabla
     * @param string $column Nombre de la columna
     * @return bool True si la columna existe
     */
    private function columnExists(string $table, string $column): bool {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = ? 
                 AND COLUMN_NAME = ?"
            );
            $stmt->execute([$table, $column]);
            return $stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene el mapeo de IDs de Moodle a IDs locales para matrículas
     * Optimizado para grandes volúmenes
     */
    public function getEnrollmentIdMap(array $courseMoodleIds, array $userMoodleIds): array {
        if (empty($courseMoodleIds) || empty($userMoodleIds)) {
            return [];
        }

        $coursePlaceholders = implode(',', array_fill(0, count($courseMoodleIds), '?'));
        $userPlaceholders = implode(',', array_fill(0, count($userMoodleIds), '?'));

        $sql = "SELECT 
                    cm.id as matricula_id,
                    c.id_moodle as course_moodle_id,
                    u.id_moodle as user_moodle_id
                FROM curso_matriculas cm
                JOIN cursos c ON cm.curso_id = c.id
                JOIN usuarios u ON cm.usuario_id = u.id
                WHERE c.id_moodle IN ($coursePlaceholders)
                AND u.id_moodle IN ($userPlaceholders)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($courseMoodleIds, $userMoodleIds));
        
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['course_moodle_id'] . '_' . $row['user_moodle_id'];
            $map[$key] = $row['matricula_id'];
        }

        return $map;
    }
    /**
     * Crea matrículas al vuelo para usuarios que tienen calificaciones pero no están matriculados
     * (Estrategia Self-Healing)
     */
    public function bulkEnrollMissingUsers(int $localCourseId, array $localUserIds): void {
        if (empty($localUserIds)) {
            return;
        }

        $placeholders = [];
        $values = [];
        
        foreach ($localUserIds as $userId) {
            $placeholders[] = "(?, ?, 'student', NOW(), NOW())";
            $values[] = $localCourseId;
            $values[] = $userId;
        }
        
        // INSERT IGNORE para evitar duplicados si corren procesos concurrentes
        $sql = "INSERT IGNORE INTO curso_matriculas (curso_id, usuario_id, rol, created_at, updated_at)
                VALUES " . implode(', ', $placeholders);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }

    /**
     * Devuelve la conexión PDO
     */
    public function getDb(): PDO {
        return $this->db;
    }
}
