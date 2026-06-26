<?php
declare(strict_types=1);

namespace Modules\Moodle;

use Config\MoodleWS;
use App\Exceptions\Moodle\MoodleException;
use App\Exceptions\Moodle\StopSyncException;
use App\Services\LoggerService;

/**
 * Cliente HTTP para el Webservice de Moodle
 * 
 * Mejoras v2:
 * - Reintentos con backoff exponencial
 * - Detección de respuestas HTML vs JSON
 * - Circuit breaker básico
 * - Health check
 * - Logging detallado para diagnóstico
 */
class MoodleClient {

    private string $token;
    private string $baseUrl;
    
    /** @var int Contador de fallos consecutivos para circuit breaker */
    private static int $consecutiveFailures = 0;
    
    /** @var int Umbral para abrir el circuit breaker */
    private const CIRCUIT_BREAKER_THRESHOLD = 10;
    
    /** @var int|null Tiempo de cuando se abrió el circuit breaker */
    private static ?int $circuitOpenTime = null;
    
    /** @var int Tiempo de espera antes de intentar cerrar el circuit breaker (segundos) */
    private const CIRCUIT_RESET_TIME = 60;

    /** @var bool Indica si el estado ya fue cargado en este request */
    private static bool $stateLoaded = false;

    public function __construct() {
        $this->token = MoodleWS::getToken();
        $this->baseUrl = MoodleWS::getUrl();
        self::$lastStopCheck = 0;
        self::$stopSignalCached = false;
        self::loadCircuitState();
    }

    private static float $lastStopCheck = 0;
    private static bool $stopSignalCached = false;

    private static function checkStopSignal(): bool {
        $now = microtime(true);
        if ($now - self::$lastStopCheck < 0.5) {
            return self::$stopSignalCached;
        }

        self::$lastStopCheck = $now;
        self::$stopSignalCached = self::queryStopRequestFromDb();
        return self::$stopSignalCached;
    }

    private static function queryStopRequestFromDb(): bool {
        try {
            $container = \App\Core\Container::getInstance();
            $db = $container->get('db');
            if (!($db instanceof \PDO)) {
                return false;
            }

            $stmt = $db->query("SELECT COUNT(*) FROM sync_status WHERE last_sync_status = 'stopping'");
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Ruta al archivo de persistencia del circuit breaker
     */
    private static function getCircuitFile(): string {
        return dirname(__DIR__, 2) . '/storage/moodle_circuit.json';
    }

    /**
     * Carga el estado del circuit breaker desde archivo
     */
    private static function loadCircuitState(): void {
        if (self::$stateLoaded) return;

        $file = self::getCircuitFile();
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                self::$consecutiveFailures = $data['failures'] ?? 0;
                self::$circuitOpenTime = $data['open_time'] ?? null;
            }
        }
        self::$stateLoaded = true;
    }

    /**
     * Guarda el estado del circuit breaker en archivo
     */
    private static function saveCircuitState(): void {
        $file = self::getCircuitFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $data = [
            'failures' => self::$consecutiveFailures,
            'open_time' => self::$circuitOpenTime,
            'updated_at' => time()
        ];
        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Verifica si el circuit breaker está abierto
     */
    private function isCircuitOpen(): bool {
        self::loadCircuitState(); // Asegurar carga

        if (self::$circuitOpenTime === null) {
            return false;
        }
        
        // Si pasó el tiempo de reset, intentamos cerrar el circuito
        if ((time() - self::$circuitOpenTime) > self::CIRCUIT_RESET_TIME) {
            self::$circuitOpenTime = null;
            self::$consecutiveFailures = 0;
            self::saveCircuitState(); // Guardar cierre
            LoggerService::info("Moodle Circuit Breaker: Intentando cerrar circuito (half-open)");
            return false;
        }
        
        return true;
    }

    /**
     * Registra un fallo para el circuit breaker
     */
    private function recordFailure(): void {
        self::loadCircuitState();
        self::$consecutiveFailures++;
        
        if (self::$consecutiveFailures >= self::CIRCUIT_BREAKER_THRESHOLD) {
            self::$circuitOpenTime = time();
            LoggerService::info("Moodle Circuit Breaker: Circuito ABIERTO tras " . self::$consecutiveFailures . " fallos consecutivos");
        }
        self::saveCircuitState();
    }

    /**
     * Registra un éxito y resetea el circuit breaker
     */
    private function recordSuccess(): void {
        self::loadCircuitState();
        if (self::$consecutiveFailures > 0) {
            LoggerService::info("Moodle Circuit Breaker: Conexión restaurada, reseteando contador");
        }
        if (self::$consecutiveFailures > 0 || self::$circuitOpenTime !== null) {
            self::$consecutiveFailures = 0;
            self::$circuitOpenTime = null;
            self::saveCircuitState();
        }
    }

    /**
     * Health check para verificar conectividad con Moodle
     * Usa GET_CATEGORIES como función de prueba ya que es más común tenerla habilitada
     * 
     * @return array Información del sitio o array con error
     */
    public function healthCheck(): array {
        try {
            // Health check usa executeCallDirect() sin callback de stop
            // para que NUNCA se aborte por una señal de detención de sync
            $startTime = microtime(true);
            $siteInfo = $this->executeCallDirect(MoodleWS::FUNCTIONS['SITE_INFO']);
            $elapsed = round((microtime(true) - $startTime) * 1000);
            
            return [
                'success' => true,
                'sitename' => $siteInfo['sitename'] ?? 'Moodle Conectado',
                'version' => $siteInfo['release'] ?? 'API Activa',
                'username' => $siteInfo['username'] ?? 'Token válido',
                'functions_count' => count(MoodleWS::FUNCTIONS),
                'categories_count' => -1, // No relevante para ping
                'response_time_ms' => $elapsed
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Ejecuta una llamada directa sin callback de stop signal.
     * Usado por healthCheck y otras operaciones que no deben ser interrumpidas.
     */
    private function executeCallDirect(string $functionName, array $params = []): array {
        $params['wstoken'] = $this->token;
        $params['wsfunction'] = $functionName;
        $params['moodlewsrestformat'] = 'json';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30, // Health check: timeout corto
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
        ]);

        // SSL
        if (MoodleWS::shouldVerifySSL()) {
            $cacertPath = dirname(__DIR__, 2) . '/config/cacert.pem';
            if (file_exists($cacertPath)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_CAINFO, realpath($cacertPath));
            }
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new \App\Exceptions\Moodle\MoodleConnectionException("Health check CURL: $error", false);
        }
        if (empty($response)) {
            throw new \App\Exceptions\Moodle\MoodleConnectionException('Respuesta vacía', true);
        }
        if ($httpCode >= 400) {
            throw new \App\Exceptions\Moodle\MoodleConnectionException("HTTP $httpCode", false);
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \App\Exceptions\Moodle\MoodleApiException('JSON error: ' . json_last_error_msg(), 'JSON_ERROR');
        }
        if (isset($decoded['exception'])) {
            throw new \App\Exceptions\Moodle\MoodleApiException($decoded['message'] ?? 'Error', $decoded['errorcode'] ?? 'unknown');
        }

        return $decoded;
    }

    /**
     * Realiza una petición al Webservice de Moodle con reintentos.
     * 
     * @param string $functionName Nombre de la función (ej: core_user_get_users)
     * @param array $params Parámetros para la función
     * @param int $maxRetries Número máximo de reintentos (0 = usar configuración)
     * @return array Respuesta decodificada
     * @throws MoodleConnectionException
     * @throws MoodleApiException
     */
    public function call(string $functionName, array $params = [], int $maxRetries = 0): array {
        // Verificar circuit breaker
        if ($this->isCircuitOpen()) {
            throw new \App\Exceptions\Moodle\MoodleConnectionException(
                "Circuit breaker abierto: Moodle API no disponible. Espere " . 
                (self::CIRCUIT_RESET_TIME - (time() - self::$circuitOpenTime)) . " segundos.",
                true // Es timeout/bloqueo
            );
        }

        $maxRetries = $maxRetries > 0 ? $maxRetries : MoodleWS::MAX_RETRIES;
        $lastException = null;
        $delay = MoodleWS::RETRY_DELAY_MS;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $result = $this->executeCall($functionName, $params);
                $this->recordSuccess();
                return $result;
            } catch (\App\Exceptions\Moodle\MoodleConnectionException $e) {
                // Errores de conexión: Reintentar
                $lastException = $e;
                $this->recordFailure(); // Contabilizar para Circuit Breaker
                
                LoggerService::warning("Moodle Connection Retry $attempt/$maxRetries para $functionName", [
                    'error' => $e->getMessage()
                ]);

                if ($attempt < $maxRetries) {
                    usleep($delay * 1000); 
                    $delay *= MoodleWS::BACKOFF_MULTIPLIER;
                }
            } catch (\App\Exceptions\Moodle\MoodleApiException $e) {
                // Errores de API (Lógica): NO reintentar, son fatales
                LoggerService::error("Moodle API Fatal Error", [
                    'function' => $functionName,
                    'error' => $e->getMessage(),
                    'moodle_code' => $e->getMoodleErrorCode()
                ]);
                throw $e; 
            }
        }

        // Si agotamos reintentos por conexión
        throw $lastException ?? new \App\Exceptions\Moodle\MoodleConnectionException("Fallo de conexión después de $maxRetries intentos", true);
    }

    /**
     * Ejecuta una llamada individual al API de Moodle
     */
    private function executeCall(string $functionName, array $params = []): array {
        $params['wstoken'] = $this->token;
        $params['wsfunction'] = $functionName;
        $params['moodlewsrestformat'] = 'json';

        $queryString = http_build_query($params);
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $queryString,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => MoodleWS::getTimeout(),
            CURLOPT_CONNECTTIMEOUT => MoodleWS::CONNECT_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
            // Callback de progreso para detención inmediata (v4.0)
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function() {
                return self::checkStopSignal() ? 1 : 0;
            }
        ]);
        
        // Configuración SSL
        if (MoodleWS::shouldVerifySSL()) {
            $cacertPath = dirname(__DIR__, 2) . '/config/cacert.pem';
            if (file_exists($cacertPath)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_CAINFO, realpath($cacertPath));
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            }
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        } 

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);

        // 1. Errores de Conectividad (Throw ConnectionException)
        if ($error) {
            if ($errno === 42) {
                LoggerService::warning("MoodleClient: cURL abortado por solicitud del usuario (Stop Requested)");
                throw new StopSyncException('cURL abort');
            }
            throw new \App\Exceptions\Moodle\MoodleConnectionException("Error CURL ($errno): $error", ($errno === 28)); // 28 is Timeout
        }

        if ($httpCode >= 500) {
            throw new \App\Exceptions\Moodle\MoodleConnectionException("Error Servidor Moodle (HTTP $httpCode)", false);
        }

        if (empty($response)) {
            throw new \App\Exceptions\Moodle\MoodleConnectionException("Respuesta vacía de Moodle (posible timeout)", true);
        }

        // 2. Errores de Logic/API (Throw ApiException)
        if ($httpCode >= 400 && $httpCode < 500) {
            throw new \App\Exceptions\Moodle\MoodleApiException("Error Cliente HTTP $httpCode", "HTTP_$httpCode");
        }

        if ($this->isHtmlResponse($response)) {
            $errorType = $this->detectHtmlErrorType($response);
            // Si es mantenimiento o error 500 camuflado, es Connection
            if (strpos($errorType, 'mantenimiento') !== false || strpos($errorType, '500') !== false) {
                 throw new \App\Exceptions\Moodle\MoodleConnectionException("Moodle Unavailable: $errorType", false);
            }
            // Si es login/404/403, es API Exception
            throw new \App\Exceptions\Moodle\MoodleApiException("Moodle devolvió HTML ($errorType). Verifique config.", "HTML_RESPONSE");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \App\Exceptions\Moodle\MoodleApiException("Error decodificando JSON: " . json_last_error_msg(), "JSON_ERROR");
        }

        // Error Explícito de Moodle API
        if (isset($decoded['exception'])) {
            $errorCode = $decoded['errorcode'] ?? 'unknown';
            $errorMessage = $decoded['message'] ?? 'Error desconocido';
            
            throw new \App\Exceptions\Moodle\MoodleApiException(
                "Moodle API: $errorMessage", 
                $errorCode,
                0
            );
        }

        return $decoded;
    }

    /**
     * Detecta si la respuesta es HTML en lugar de JSON
     */
    private function isHtmlResponse(string $response): bool {
        $trimmed = ltrim($response);
        
        // Detectar patrones comunes de HTML
        return (
            strpos($trimmed, '<!DOCTYPE') === 0 ||
            strpos($trimmed, '<html') === 0 ||
            strpos($trimmed, '<HTML') === 0 ||
            strpos($trimmed, '<?xml') === 0 ||
            (strpos($trimmed, '<') === 0 && strpos($trimmed, '{') !== 0)
        );
    }

    /**
     * Intenta detectar el tipo de error basándose en el contenido HTML
     */
    private function detectHtmlErrorType(string $html): string {
        $lower = strtolower($html);
        
        if (strpos($lower, 'login') !== false || strpos($lower, 'signin') !== false) {
            return 'Página de login - Token inválido o expirado';
        }
        
        if (strpos($lower, '404') !== false || strpos($lower, 'not found') !== false) {
            return 'Página 404 - URL incorrecta';
        }
        
        if (strpos($lower, '403') !== false || strpos($lower, 'forbidden') !== false) {
            return 'Acceso denegado - Permisos insuficientes';
        }
        
        if (strpos($lower, '500') !== false || strpos($lower, 'internal server') !== false) {
            return 'Error interno de Moodle';
        }
        
        if (strpos($lower, 'maintenance') !== false) {
            return 'Moodle en mantenimiento';
        }
        
        return 'Respuesta HTML no esperada';
    }

    public function getCategories(): array {
        return $this->call(MoodleWS::FUNCTIONS['GET_CATEGORIES']);
    }

    /**
     * Obtiene usuarios por lista de IDs (Batch processing).
     * Usa core_user_get_users_by_field que soporta array de valores.
     * 
     * NOTA: Se recomienda batches de máximo 20 usuarios para evitar timeouts.
     * 
     * @param array $ids Lista de IDs de Moodle
     * @return array
     */
    public function getUsersByIds(array $ids): array {
        if (empty($ids)) {
            return [];
        }

        // Limitar tamaño de batch para evitar problemas
        if (count($ids) > MoodleWS::USER_BATCH_SIZE) {
            LoggerService::info("getUsersByIds: Batch muy grande (" . count($ids) . "), procesando en chunks");
            
            $results = [];
            $chunks = array_chunk($ids, MoodleWS::USER_BATCH_SIZE);
            
            foreach ($chunks as $chunk) {
                $chunkResult = $this->getUsersByIds($chunk);
                $results = array_merge($results, $chunkResult);
            }
            
            return $results;
        }

        $params = [
            'field' => 'id',
            'values' => array_map('strval', $ids) // Moodle espera strings
        ];
        return $this->call(MoodleWS::FUNCTIONS['GET_USERS_BY_FIELD'], $params);
    }

    /**
     * Obtiene cursos por lista de IDs.
     */
    public function getCoursesByIds(array $ids): array {
        if (empty($ids)) {
            return [];
        }

        $params = [
            'options' => [
                'ids' => $ids
            ]
        ];
        return $this->call(MoodleWS::FUNCTIONS['GET_COURSES'], $params);
    }

    /**
     * Obtiene todos los cursos disponibles.
     */
    public function getAllCourses(): array {
        return $this->call(MoodleWS::FUNCTIONS['GET_COURSES']);
    }

    /**
     * Obtiene cursos cuyo inicio cae en un año específico.
     */
    public function getCoursesByYear(int $year): array {
        $courses = $this->getAllCourses();
        $filtered = [];

        foreach ($courses as $course) {
            if (empty($course['startdate']) || !is_numeric($course['startdate'])) {
                continue;
            }
            $courseYear = (int)date('Y', (int)$course['startdate']);
            if ($courseYear === $year) {
                $filtered[] = $course;
            }
        }

        return $filtered;
    }

    /**
     * Obtiene cursos de una categoría específica (Más eficiente)
     */
    public function getCoursesByCategory(int $categoryId): array {
        $result = $this->call('core_course_get_courses_by_field', [
            'field' => 'category',
            'value' => $categoryId
        ]);
        return $result['courses'] ?? [];
    }

    /**
     * Obtiene cohortes por lista de IDs.
     */
    public function getCohortsByIds(array $ids): array {
        if (empty($ids)) {
            return [];
        }

        $params = [
            'cohortids' => $ids
        ];
        return $this->call(MoodleWS::FUNCTIONS['GET_COHORTS'], $params);
    }

    /**
     * Obtiene items de calificación para un usuario en un curso.
     * 
     * @param int $courseId
     * @param int $userId
     */
    public function getGradesForUser(int $courseId, int $userId): array {
        if ($courseId < 1 || $userId < 1) {
            throw new MoodleException("Parámetros inválidos para getGradesForUser");
        }

        $params = [
            'courseid' => $courseId,
            'userid' => $userId
        ];
        return $this->call(MoodleWS::FUNCTIONS['GET_COURSE_GRADES'], $params);
    }

    /**
     * Obtiene usuarios matriculados en un curso.
     */
    public function getEnrolledUsers(int $courseId): array {
        $params = [
            'courseid' => $courseId
        ];
        return $this->call(MoodleWS::FUNCTIONS['GET_ENROLLED_USERS'], $params);
    }

    /**
     * Obtiene usuarios filtrando por criterio (búsqueda genérica)
     * @param string $key Clave de búsqueda (ej: 'email', 'username')
     * @param string $value Valor a buscar
     */
    public function getUsersByField(string $key, string $value): array {
        $params = [
            'criteria' => [
                [
                    'key' => $key,
                    'value' => $value
                ]
            ]
        ];
        return $this->call(MoodleWS::FUNCTIONS['GET_USERS'], $params);
    }

    /**
     * Obtiene estadísticas del circuit breaker (para monitoreo)
     */
    public static function getCircuitBreakerStatus(): array {
        self::loadCircuitState();
        return [
            'consecutive_failures' => self::$consecutiveFailures,
            'is_open' => self::$circuitOpenTime !== null,
            'open_since' => self::$circuitOpenTime,
            'threshold' => self::CIRCUIT_BREAKER_THRESHOLD,
            'reset_time' => self::CIRCUIT_RESET_TIME
        ];
    }

    /**
     * Resetea manualmente el circuit breaker (para administración)
     */
    public static function resetCircuitBreaker(): void {
        self::$consecutiveFailures = 0;
        self::$circuitOpenTime = null;
        self::saveCircuitState();
        LoggerService::info("Moodle Circuit Breaker: Reset manual ejecutado");
    }

    /**
     * Obtiene TODOS los usuarios de Moodle de una vez.
     * 
     * Usa core_user_get_users con criterio amplio (email LIKE '%').
     * Esto es MUCHO más rápido que iterar por cada curso.
     * 
     * @return array Lista de usuarios
     */
    public function getAllUsers(): array {
        // core_user_get_users espera un criterio de búsqueda
        // Usamos un criterio que captura todos los usuarios con email
        $result = $this->call(MoodleWS::FUNCTIONS['GET_USERS'], [
            'criteria' => [
                [
                    'key' => 'email',
                    'value' => '%'  // Wildcard para todos
                ]
            ]
        ]);
        
        return $result['users'] ?? [];
    }

    /**
     * Obtiene usuarios de Moodle en batches usando diferentes criterios.
     * 
     * Estrategia: Dividir por inicial de email para distribuir la carga.
     * 
     * @param callable|null $progressCallback Callback para reportar progreso
     * @return array Lista de usuarios (deduplicados)
     */
    public function getAllUsersBatched(?callable $progressCallback = null): array {
        $allUsers = [];
        $seenIds = [];
        
        // Carga por lotes mediante iniciales de email (a%, b%, c%, etc.)
        // Evita cargar todos los usuarios a la vez en memoria, previniendo timeout y mem_limit
        $initials = array_merge(
            range('a', 'z'),
            range('0', '9')
        );
        
        $total = count($initials);
        $processed = 0;
        
        foreach ($initials as $initial) {
            // Verificar detención antes de cada llamada de red
            if ($progressCallback && $progressCallback($processed, $total, count($allUsers)) === true) {
                LoggerService::warning("getAllUsersBatched: Detención solicitada antes de procesar '$initial'");
                throw new StopSyncException("Detenido por el usuario");
            }

            try {
                $result = $this->call(MoodleWS::FUNCTIONS['GET_USERS'], [
                    'criteria' => [
                        [
                            'key' => 'email',
                            'value' => $initial . '%'
                        ]
                    ]
                ]);
                
                $users = $result['users'] ?? [];
                
                foreach ($users as $user) {
                    if (!isset($seenIds[$user['id']])) {
                        $seenIds[$user['id']] = true;
                        $allUsers[] = $user;
                    }
                }
                
                $processed++;
                if ($progressCallback) {
                    $shouldStop = $progressCallback($processed, $total, count($allUsers));
                    if ($shouldStop === true) {
                        LoggerService::warning("getAllUsersBatched: Detención solicitada por el usuario");
                        throw new StopSyncException("Detenido por el usuario");
                    }
                }
                
            } catch (StopSyncException $e) {
                // Propagar inmediatamente — no tragar la señal de stop
                throw $e;
            } catch (\Exception $e) {
                LoggerService::warning("getAllUsersBatched: Error con inicial '$initial'", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        LoggerService::info("getAllUsersBatched: Total usuarios obtenidos", [
            'count' => count($allUsers),
            'batches' => $processed
        ]);
        
        return $allUsers;
    }
}
