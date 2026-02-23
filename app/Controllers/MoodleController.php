<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Controllers\BaseController;
use App\Services\QueueService;
use App\Jobs\SyncMoodleOptimizedJob;
use App\Helpers\CSRFHelper;
use App\Helpers\InputSanitizerHelper;
use App\Services\LoggerService;
use App\Services\SyncStateDbService;
use App\Services\MoodleSyncOptimizedService;

class MoodleController extends BaseController {

    private QueueService $queueService;
    private SyncStateDbService $syncService;
    private \App\Services\SyncCleanupService $cleanupService;
    private \App\Services\MoodleSyncOptimizedService $moodleSyncService;

    public function __construct(
        QueueService $queueService, 
        SyncStateDbService $syncService,
        \App\Services\SyncCleanupService $cleanupService,
        \App\Services\MoodleSyncOptimizedService $moodleSyncService
    ) {
        parent::__construct();
        $this->queueService = $queueService;
        $this->syncService = $syncService;
        $this->cleanupService = $cleanupService;
        $this->moodleSyncService = $moodleSyncService;
    }

    public function index() {
        $this->requirePermission('sincronizar_moodle');
        
        $this->render('Moodle/index', [
            'title' => 'Integración Moodle'
        ]);
    }

    /**
     * Health check para verificar conexión con Moodle
     * GET /moodle/health
     */
    public function health() {
        // Solo requiere login, no permiso específico - es diagnóstico
        $this->requireLogin();

        // Forzar respuesta JSON
        header('Content-Type: application/json; charset=utf-8');

        // IMPORTANTE: Cerrar sesión para escritura para evitar bloqueo de requests concomitantes
        // si la llamada a Moodle tarda mucho (Session Locking)
        session_write_close();

        try {
            $result = $this->moodleSyncService->checkConnection();
            
            // Agregar estado del circuit breaker
            $circuitStatus = \Modules\Moodle\MoodleClient::getCircuitBreakerStatus();
            
            // Verificar si healthCheck retornó un error
            if (isset($result['success']) && $result['success'] === false) {
                // Moodle no está accesible pero el endpoint funciona
                echo json_encode([
                    'success' => false,
                    'error' => $result['error'] ?? 'Error de conexión con Moodle',
                    'circuit_breaker' => $circuitStatus
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Conexión exitosa
            $result['circuit_breaker'] = $circuitStatus;
            echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * Resetea manualmente el circuit breaker
     * POST /moodle/reset-circuit
     */
    public function resetCircuit() {
        $this->requirePermission('sincronizar_moodle');
        
        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 403);
            return;
        }

        \Modules\Moodle\MoodleClient::resetCircuitBreaker();
        
        LoggerService::audit(
            $this->getUserId(), 
            'MOODLE_CIRCUIT_RESET', 
            'manual', 
            []
        );
        
        $this->jsonSuccess(['message' => 'Circuit breaker reseteado']);
    }

    /**
     * Resetea todos los procesos de sincronización y jobs trabados
     * POST /moodle/reset-processes
     */
    public function resetProcesses() {
        $this->requirePermission('sincronizar_moodle');
        
        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 403);
            return;
        }

        try {
            $db = \App\Core\Container::getInstance()->get('db');

            // 1. Resetear status de sincronización
            $db->exec("UPDATE sync_status SET last_sync_status = 'error', last_error_message = 'Reset manual por usuario' WHERE last_sync_status IN ('running', 'stopping')");

            // 2. Marcar jobs como fallidos
            $db->exec("UPDATE queue_jobs SET status = 'failed', last_error = 'Reset manual por sistema' WHERE status = 'running'");

            // 3. Resetear archivo JSON legacy
            $jsonPath = dirname(__DIR__, 2) . '/storage/sync_state.json';
            if (file_exists($jsonPath)) {
                @unlink($jsonPath); // Eliminar para que se regenere limpio
            }

            // 4. Resetear Circuit Breaker
            \Modules\Moodle\MoodleClient::resetCircuitBreaker();

            LoggerService::audit($this->getUserId(), 'MOODLE_PROCESS_RESET', 'manual', []);

            $this->jsonSuccess(['message' => 'Todos los procesos han sido reseteados correctamente.']);

        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Error al resetear: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint para iniciar sincronizaciones vía AJAX
     */
    public function sync(string $entity) {
        // Validación CSRF obligatoria
        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 403);
            return;
        }

        // Rate Limiting: Prevenir spam de botones de sincronización
        // Clave única por usuario y acción
        $throttleKey = 'sync_' . $this->getUserId();
        // Permitir 10 intentos en ventana de tiempo (el helper maneja bloqueo por tiempo tras fallo, 
        // aquí lo adaptamos: incrementamos siempre).
        
        $limitCheck = \App\Helpers\RateLimitHelper::check($throttleKey, 5, 60); // 5 peticiones por minuto (si se considera 'fallo')
        
        if ($limitCheck['isBlocked'] ?? false) {
             $this->jsonResponse([
                'success' => false, 
                'message' => 'Demasiadas solicitudes. Por favor espere ' . ($limitCheck['remainingTime'] ?? 60) . ' segundos.'
            ], 429);
            return;
        }

        // Registramos el "intento" (en este contexto, la solicitud) para el rate limiter
        \App\Helpers\RateLimitHelper::recordAttempt($throttleKey);

        try {
            // Saneamiento de entrada
            $entity = InputSanitizerHelper::sanitizeString($entity);
            $entity = strtolower($entity);

            // Mapeo de entidades a tipos de sync optimizado
            $validEntities = [
                'users' => 'users',
                'courses' => 'courses', 
                'cohorts' => 'cohorts',
                'grades' => 'grades',
                'categories' => 'categories',
                'enrollments' => 'enrollments',
                'all' => 'all',
                'delta' => 'delta'
            ];

            if (!isset($validEntities[$entity])) {
                $this->jsonResponse(['success' => false, 'message' => 'Entidad no soportada'], 400);
                return;
            }

            // Usar el nuevo Job Optimizado
            $syncType = $validEntities[$entity];
            $force = (bool)($_POST['force'] ?? false);
            
            $job = new SyncMoodleOptimizedJob($syncType, $force);
            $jobId = $this->queueService->dispatch($job);

            // Auditoría de la acción
            LoggerService::audit(
                $this->getUserId(), 
                'MOODLE_SYNC_START_OPTIMIZED', 
                $entity, 
                ['sync_type' => $syncType, 'force' => $force, 'job_id' => $jobId]
            );

            $this->jsonResponse([
                'success' => true, 
                'message' => "Sincronización optimizada de $entity iniciada.",
                'job_id' => $jobId
            ]);

        } catch (\Throwable $e) {
            LoggerService::error("Error al despachar sync Moodle optimizado", [
                'entity' => $entity ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            $this->jsonResponse([
                'success' => false, 
                'message' => 'Error backend: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Inicia la sincronización global (Async)
     */
    public function asyncStart() {
        $this->requirePermission('sincronizar_moodle');

        // Validación CSRF
        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 403);
            return;
        }

        $currentState = $this->syncService->getStatus();

        if (($currentState['status'] ?? 'idle') === 'running') {
            $this->jsonResponse(['success' => false, 'message' => 'El proceso ya está en ejecución.']);
            return;
        }

        // Forzar siempre modo optimizado v3.0
        $syncType = $_POST['type'] ?? 'all';
        $force = (bool)($_POST['force'] ?? false);
        $regeneratePasswords = (bool)($_POST['regenerate_passwords'] ?? false);
        
        $options = [
            'regenerate_passwords' => $regeneratePasswords
        ];
        
        // En entorno Windows/WAMP sin worker, ejecutar síncronamente
        // Para producción con worker, cambiar a false
        $executeSync = true; // Cambiar a false si hay worker de cola corriendo

        try {
            // Instanciar job optimizado directamente
            $job = new SyncMoodleOptimizedJob($syncType, $force, null, $options);
            
            if ($executeSync) {
                // MODO SÍNCRONO: Ejecutar directamente (útil en Windows sin worker)
                // Nota: Esto puede demorar varios minutos
                
                // Aumentar límites para sync largo
                set_time_limit(0);
                ini_set('memory_limit', '1G');
                
                // IMPORTANTE: Cerrar sesión para escritura para evitar bloqueo de requests concomitantes (polling)
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }

                // Responder al cliente que inició y luego continuar
                // Usamos output buffering para enviar respuesta inmediata
                ignore_user_abort(true);
                
                // Registrar el job de todas formas para tracking
                $jobId = $this->queueService->dispatch($job);
                
                // Enviar respuesta inmediata
                $response = json_encode([
                    'success' => true,
                    'status' => 'started',
                    'job_id' => $jobId,
                    'message' => 'Sincronización iniciada (modo síncrono)'
                ], JSON_UNESCAPED_UNICODE);
                
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Length: ' . strlen($response));
                header('Connection: close');
                
                echo $response;
                
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                } else {
                    ob_end_flush();
                    flush();
                }
                
                // Marcar como running
                $jobModel = \App\Core\Container::getInstance()->get(\App\Models\QueueJobModel::class);
                $jobModel->markAsRunning($jobId);
                
                // Ejecutar el job
                try {
                    $job->handle();
                    $jobModel->markAsCompleted($jobId);
                } catch (\Throwable $e) {
                    $jobModel->markAsFailed($jobId, $e->getMessage());
                    LoggerService::error('Sync job failed', ['error' => $e->getMessage()]);
                }
                
                exit; // Terminar aquí
            } else {
                // MODO ASÍNCRONO: Solo encolar (requiere worker corriendo)
                $jobId = $this->queueService->dispatch($job);
                
                $this->jsonSuccess([
                    'status' => 'started', 
                    'job_id' => $jobId,
                    'message' => 'Proceso encolado. Asegúrese de tener el worker corriendo.'
                ]);
            }
        } catch (\Exception $e) {
            $this->syncService->errorSync($e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'No se pudo iniciar el proceso: ' . $e->getMessage()]);
        }
    }

    /**
     * Detiene la sincronización global
     */
    public function asyncStop() {
        $this->requirePermission('sincronizar_moodle');

        // Validación CSRF
        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 403);
            return;
        }

        $this->syncService->requestStop();

        $this->jsonSuccess(['status' => 'stopping', 'message' => 'Se ha enviado la señal de detención.']);
    }



    /**
     * @deprecated Método debugDb eliminado por seguridad.
     */
    public function debugDb() {
        if (\Config\Env::get('APP_DEBUG', false)) {
            die('Debug method removed for security.');
        }
        $this->redirect('escritorio');
    }

    public function getAsyncStatus() {
        $this->requirePermission('sincronizar_moodle');

        // Usar el nuevo servicio basado en BD
        $status = $this->syncService->getStatus();

        // Si no hay datos, retornamos idle
        if (empty($status) || !isset($status['status'])) {
            $status = [
                'status' => 'idle', 
                'progress' => 0, 
                'message' => 'Listo para sincronizar',
                'total_processed' => 0,
                'total_errors' => 0
            ];
        }

        $this->jsonSuccess($status);
    }

    /**
     * Endpoint API para consultar estado de trabajos (Legacy/Batch Jobs)
     */
    public function jobsStatus() {
        if (!$this->isAjax()) {
            // Opcional: permitir acceso directo para debug
            // $this->redirect('moodle'); 
        }

        $jobModel = \App\Core\Container::getInstance()->get(\App\Models\QueueJobModel::class);
        $jobs = $jobModel->getRecentJobs(10);
        
        // DEBUG: Verificar si está recuperando datos
        LoggerService::info("Checking jobs status", [
            'count' => count($jobs), 
            'first_job' => $jobs[0]['id'] ?? 'none'
        ]);
        
        // Formatear para el frontend si es necesario, o enviar raw
        // El frontend espera: Entity, Batch, Estado, Progreso (simulado por ahora), Iniciado
        
        $formatted = array_map(function($job) {
            // Deserializar handler para sacar info útil
            // Deserializar handler para sacar info útil
            $handlerObj = @unserialize($job['handler']);
            $entity = 'Desconocido';
            $batch = 'N/A';
            
            // Logica simplificada para no complicar el replace
            $info = 'Job #' . $job['id'];
            if (is_object($handlerObj)) {
                $clazz = (new \ReflectionClass($handlerObj))->getShortName();
                $info = $clazz;
                
                if (property_exists($handlerObj, 'entity')) {
                     // Reflection al rescate
                     $ref = new \ReflectionObject($handlerObj);
                     if ($ref->hasProperty('entity')) {
                         $prop = $ref->getProperty('entity');
                         $prop->setAccessible(true);
                         $info = ucfirst($prop->getValue($handlerObj));
                     }
                      if ($ref->hasProperty('batchSize')) {
                         $prop2 = $ref->getProperty('page');
                         if ($prop2) {
                            $prop2->setAccessible(true);
                            $page = $prop2->getValue($handlerObj);
                            $batch = "Lote " . $page;
                         }
                     }
                }
            }

            return [
                'id' => $job['id'],
                'entity' => $info,
                'batch' => $batch,
                'status' => $job['status'],
                'progress' => ($job['status'] === 'completed') ? 100 : (($job['status'] === 'running') ? 50 : 0),
                'started_at' => $job['created_at'],
                'error' => $job['last_error']
            ];
        }, $jobs);

        $this->jsonSuccess($formatted);
    }

    // =========================================================================
    // ENDPOINTS DE LIMPIEZA Y GESTIÓN DE ENTIDADES HUÉRFANAS
    // =========================================================================

    /**
     * Obtiene resumen de entidades huérfanas (usuarios suspendidos, cursos ocultos, etc.)
     * GET /moodle/cleanup/summary
     */
    public function cleanupSummary() {
        $this->requirePermission('sincronizar_moodle');

        try {
            $summary = $this->cleanupService->obtenerResumenHuerfanos();

            $this->jsonSuccess([
                'summary' => $summary,
                'message' => 'Resumen de entidades huérfanas obtenido correctamente'
            ]);
        } catch (\Throwable $e) {
            LoggerService::error("Error obteniendo resumen de limpieza", ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Ejecuta la limpieza completa de entidades huérfanas
     * POST /moodle/cleanup/execute
     * 
     * Este proceso:
     * 1. Suspende usuarios que ya no existen en Moodle
     * 2. Oculta cursos que ya no existen en Moodle
     * 3. Desactiva matrículas huérfanas
     */
    public function cleanupExecute() {
        $this->requirePermission('sincronizar_moodle');

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 403);
            return;
        }

        try {
            // Aumentar límites para proceso largo
            set_time_limit(300);
            ini_set('memory_limit', '512M');

            // Cerrar sesión para evitar bloqueo
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $result = $this->cleanupService->ejecutarLimpiezaCompleta();

            // Registrar auditoría
            LoggerService::audit(
                $this->getUserId(),
                'MOODLE_CLEANUP_EXECUTED',
                'cleanup',
                $result
            );

            $this->jsonSuccess([
                'result' => $result,
                'message' => 'Limpieza ejecutada correctamente'
            ]);
        } catch (\Throwable $e) {
            LoggerService::error("Error ejecutando limpieza", ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Reactiva un usuario que fue suspendido por limpieza
     * POST /moodle/cleanup/reactivate-user
     * 
     * Body: { "id_moodle": 123 }
     */
    public function reactivateUser() {
        $this->requirePermission('sincronizar_moodle');

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 403);
            return;
        }

        $idMoodle = (int)($_POST['id_moodle'] ?? 0);
        
        if ($idMoodle <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'ID de Moodle inválido'], 400);
            return;
        }

        try {
            $reactivated = $this->cleanupService->reactivarUsuario($idMoodle);

            if ($reactivated) {
                LoggerService::audit(
                    $this->getUserId(),
                    'USER_REACTIVATED_MANUAL',
                    "User:moodle:$idMoodle",
                    ['by_user' => $this->getUserId()]
                );
                
                $this->jsonSuccess(['message' => "Usuario con id_moodle=$idMoodle reactivado"]);
            } else {
                $this->jsonResponse([
                    'success' => false, 
                    'message' => 'Usuario no encontrado o ya estaba activo'
                ], 404);
            }
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Reactiva un curso que fue ocultado por limpieza
     * POST /moodle/cleanup/reactivate-course
     * 
     * Body: { "id_moodle": 456 }
     */
    public function reactivateCourse() {
        $this->requirePermission('sincronizar_moodle');

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 403);
            return;
        }

        $idMoodle = (int)($_POST['id_moodle'] ?? 0);
        
        if ($idMoodle <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'ID de Moodle inválido'], 400);
            return;
        }

        try {
            $reactivated = $this->cleanupService->reactivarCurso($idMoodle);

            if ($reactivated) {
                LoggerService::audit(
                    $this->getUserId(),
                    'COURSE_REACTIVATED_MANUAL',
                    "Course:moodle:$idMoodle",
                    ['by_user' => $this->getUserId()]
                );
                
                $this->jsonSuccess(['message' => "Curso con id_moodle=$idMoodle reactivado"]);
            } else {
                $this->jsonResponse([
                    'success' => false, 
                    'message' => 'Curso no encontrado o ya estaba visible'
                ], 404);
            }
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Limpia solo las matrículas huérfanas (sin afectar usuarios ni cursos)
     * POST /moodle/cleanup/enrollments
     */
    public function cleanupEnrollments() {
        $this->requirePermission('sincronizar_moodle');

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->jsonResponse(['success' => false, 'message' => 'Token CSRF inválido'], 403);
            return;
        }

        try {
            $count = $this->cleanupService->limpiarMatriculasHuerfanas();

            LoggerService::audit(
                $this->getUserId(),
                'ENROLLMENTS_CLEANUP_MANUAL',
                'enrollments',
                ['count' => $count]
            );

            $this->jsonSuccess([
                'count' => $count,
                'message' => "$count matrículas huérfanas procesadas"
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene lista de usuarios suspendidos (huérfanos)
     * GET /moodle/cleanup/orphan-users
     */
    public function orphanUsers() {
        $this->requirePermission('sincronizar_moodle');

        try {
            $db = \App\Core\Container::getInstance()->get('db');
            
            $stmt = $db->query(
                "SELECT id, id_moodle, username, email, nombre, apellido, updated_at as suspended_at
                 FROM usuarios 
                 WHERE id_moodle IS NOT NULL AND suspended = 1
                 ORDER BY updated_at DESC
                 LIMIT 100"
            );
            
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->jsonSuccess([
                'count' => count($users),
                'users' => $users
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene lista de cursos ocultos (huérfanos)
     * GET /moodle/cleanup/orphan-courses
     */
    public function orphanCourses() {
        $this->requirePermission('sincronizar_moodle');

        try {
            $db = \App\Core\Container::getInstance()->get('db');
            
            $stmt = $db->query(
                "SELECT id, id_moodle, shortname, fullname, updated_at as hidden_at
                 FROM cursos 
                 WHERE visible = 0
                 ORDER BY updated_at DESC
                 LIMIT 100"
            );
            
            $courses = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->jsonSuccess([
                'count' => count($courses),
                'courses' => $courses
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    /**
     * Engine de Streaming en tiempo real (SSE) para progreso bit-a-bit.
     * Reemplaza el polling por una conexión bidireccional (Server->Client).
     * GET /moodle/sync/streamProgress
     */
    public function streamProgress() {
        $this->requirePermission('sincronizar_moodle');

        // Configurar Headers para SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Para Nginx si estuviera presente

        // IMPORTANTE: Cerrar sesión para escritura para no bloquear otros requests
        session_write_close();

        $lastPulse = 0;
        $file = dirname(__DIR__, 2) . '/storage/sync_state.json';

        // Bucle de streaming
        while (true) {
            // Verificar si el cliente sigue conectado
            if (connection_aborted()) break;

            clearstatcache(true, $file);
            $currentPulse = file_exists($file) ? filemtime($file) : 0;

            if ($currentPulse > $lastPulse) {
                $status = $this->syncService->getStatus();
                echo "data: " . json_encode($status) . "\n\n";
                
                // Flush inmediato al buffer de salida
                if (ob_get_level() > 0) ob_flush();
                flush();
                
                $lastPulse = $currentPulse;

                // Si terminó el proceso, podemos cerrar el stream después de un último envío
                if (!in_array($status['status'], ['running', 'stopping'])) {
                    // Esperar un poco para asegurar que el cliente recibe el 'completed'
                    sleep(2);
                    break;
                }
            }

            // Pausa de cortesía para no saturar CPU (bit-a-bit real, alta frecuencia)
            usleep(200000); // 200ms
        }
    }
}
