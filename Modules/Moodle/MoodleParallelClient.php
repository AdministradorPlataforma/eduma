<?php
declare(strict_types=1);

namespace Modules\Moodle;

use Config\MoodleWS;
use App\Exceptions\MoodleException;
use App\Services\LoggerService;

/**
 * Cliente HTTP Paralelo para Moodle v3.0
 * 
 * Utiliza curl_multi para ejecutar múltiples requests simultáneos.
 * Optimizado para sincronización masiva (22K+ usuarios, 10K+ cursos).
 * 
 * Mejoras v3.0:
 * - Circuit breaker compartido con MoodleClient
 * - Reintentos inteligentes para requests fallidos
 * - Early-stop cuando hay fallas masivas (>50%)
 * - Backoff exponencial entre reintentos
 * - Logging agrupado de errores
 * 
 * @version 3.0
 */
class MoodleParallelClient {

    private string $token;
    private string $baseUrl;
    
    /** @var int Número máximo de requests paralelos */
    private int $maxParallel;
    
    /** @var int Timeout por request individual */
    private int $timeout;

    /** @var int Máximo de reintentos por request fallido */
    private const MAX_RETRIES = 3;

    /** @var int Delay inicial entre reintentos (ms) */
    private const RETRY_DELAY_MS = 600;

    /** @var float Umbral de fallos para early-stop (50%) */
    private const FAILURE_THRESHOLD = 0.5;

    /** @var int Contador de errores agrupados para logging */
    private array $errorCounts = [];

    public function __construct() {
        $this->token = MoodleWS::getToken();
        $this->baseUrl = MoodleWS::getUrl();
        $this->maxParallel = MoodleWS::getParallelRequests();
        $this->timeout = MoodleWS::PARALLEL_TIMEOUT;
    }

    /**
     * Configura el número máximo de requests paralelos en tiempo de ejecución
     */
    public function setMaxParallel(int $n): void {
        if ($n > 0) {
            $this->maxParallel = $n;
        }
    }

    /**
     * Configura el timeout por request en tiempo de ejecución
     */
    public function setTimeout(int $seconds): void {
        if ($seconds > 0) {
            $this->timeout = $seconds;
        }
    }

    /**
     * Verifica si el circuit breaker global está abierto
     */
    private function isCircuitOpen(): bool {
        return MoodleClient::getCircuitBreakerStatus()['is_open'];
    }

    /**
     * Ejecuta múltiples llamadas en paralelo a Moodle
     * 
     * @param array $requests Array de ['function' => string, 'params' => array, 'key' => string]
     * @param bool $withRetries Habilitar reintentos para requests fallidos
     * @return array Resultados indexados por 'key'
     */
    public function executeParallel(array $requests, bool $withRetries = true): array {
        if (empty($requests)) {
            return ['results' => [], 'errors' => [], 'stats' => ['total' => 0, 'success' => 0, 'failed' => 0]];
        }

        // Verificar circuit breaker antes de iniciar
        if ($this->isCircuitOpen()) {
            LoggerService::warning("MoodleParallelClient: Circuit breaker abierto, abortando requests paralelos", [
                'requests_count' => count($requests)
            ]);
            return [
                'results' => [],
                'errors' => ['circuit_breaker' => ['error' => 'Circuit breaker abierto']],
                'stats' => ['total' => count($requests), 'success' => 0, 'failed' => count($requests)],
                'aborted' => true,
                'reason' => 'circuit_breaker'
            ];
        }

        $this->errorCounts = []; // Reset contadores de errores
        $results = [];
        $errors = [];
        $aborted = false;
        $abortReason = null;
        
        // Procesar en chunks para no saturar
        $chunks = array_chunk($requests, $this->maxParallel, true);
        $totalChunks = count($chunks);
        $processedChunks = 0;
        
        foreach ($chunks as $chunkIndex => $chunk) {
            // Verificar circuit breaker en cada chunk
            if ($this->isCircuitOpen()) {
                $aborted = true;
                $abortReason = 'circuit_breaker';
                LoggerService::warning("MoodleParallelClient: Circuit breaker detectado, abortando chunks restantes", [
                    'processed_chunks' => $processedChunks,
                    'total_chunks' => $totalChunks,
                    'results_so_far' => count($results)
                ]);
                break;
            }

            $chunkResults = $this->executeChunkWithRetries($chunk, $withRetries);
            $results = array_merge($results, $chunkResults['success']);
            $errors = array_merge($errors, $chunkResults['errors']);
            $processedChunks++;
            
            // Early-stop: Si el chunk tuvo más del 50% de fallos, abortar
            $chunkTotal = count($chunk);
            $chunkFailed = count($chunkResults['errors']);
            
            if ($chunkTotal > 0 && ($chunkFailed / $chunkTotal) > self::FAILURE_THRESHOLD && $chunkFailed >= 2) {
                $aborted = true;
                $abortReason = 'high_failure_rate';
                LoggerService::warning("MoodleParallelClient: Early-stop por alta tasa de fallas", [
                    'chunk_index' => $chunkIndex + 1,
                    'chunk_failed' => $chunkFailed,
                    'chunk_total' => $chunkTotal,
                    'failure_rate' => round(($chunkFailed / $chunkTotal) * 100, 1) . '%',
                    'processed_chunks' => $processedChunks,
                    'total_chunks' => $totalChunks
                ]);
                break;
            }
        }

        // Log resumen (agrupado)
        $this->logErrorSummary(count($requests), count($results), $errors);

        return [
            'results' => $results,
            'errors' => $errors,
            'stats' => [
                'total' => count($requests),
                'success' => count($results),
                'failed' => count($errors),
                'chunks_processed' => $processedChunks,
                'chunks_total' => $totalChunks
            ],
            'aborted' => $aborted,
            'reason' => $abortReason
        ];
    }

    /**
     * Ejecuta un chunk con reintentos para requests fallidos
     */
    private function executeChunkWithRetries(array $requests, bool $withRetries): array {
        $chunkResults = $this->executeChunk($requests);
        
        // Si no hay reintentos o no hay errores, retornar directamente
        if (!$withRetries || empty($chunkResults['errors'])) {
            return $chunkResults;
        }

        // Reintentar solo los requests fallidos (hasta MAX_RETRIES)
        $retriableErrors = [];
        foreach ($chunkResults['errors'] as $key => $errorData) {
            // Solo reintentar errores de conexión, no errores de API de Moodle
            if ($this->isRetriableError($errorData)) {
                $retriableErrors[$key] = $errorData['request'];
            }
        }

        if (empty($retriableErrors)) {
            return $chunkResults;
        }

        // Reintentar con backoff exponencial
        $delay = self::RETRY_DELAY_MS;
        for ($retryAttempt = 1; $retryAttempt <= self::MAX_RETRIES; $retryAttempt++) {
            if (empty($retriableErrors)) {
                break;
            }

            // Esperar antes de reintentar
            usleep($delay * 1000);
            $delay *= 2; // Backoff exponencial

            LoggerService::info("MoodleParallelClient: Reintento $retryAttempt/" . self::MAX_RETRIES, [
                'retry_count' => count($retriableErrors)
            ]);

            $retryRequests = array_values($retriableErrors);
            $retryResults = $this->executeChunk($retryRequests);

            // Mover éxitos de este reintento al resultado final
            foreach ($retryResults['success'] as $key => $data) {
                $chunkResults['success'][$key] = $data;
                unset($chunkResults['errors'][$key]);
                unset($retriableErrors[$key]);
            }

            // Actualizar los errores retriables restantes
            $newRetriableErrors = [];
            foreach ($retryResults['errors'] as $key => $errorData) {
                if ($this->isRetriableError($errorData)) {
                    $newRetriableErrors[$key] = $errorData['request'];
                }
            }
            $retriableErrors = $newRetriableErrors;
        }

        return $chunkResults;
    }

    /**
     * Determina si un error es retriable (problemas de conexión vs errores de API)
     */
    private function isRetriableError(array $errorData): bool {
        $error = $errorData['error'] ?? '';
        $retriablePatterns = [
            'CURL Error:',
            'Operation timed out',
            'Could not resolve host',
            'Connection refused',
            'SSL',
            'HTTP 5', // Errores 5xx son retriables
            'Empty response'
        ];

        foreach ($retriablePatterns as $pattern) {
            if (stripos($error, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ejecuta un chunk de requests en paralelo
     */
    private function executeChunk(array $requests): array {
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        $results = ['success' => [], 'errors' => []];

        // Preparar todos los handles
        foreach ($requests as $index => $request) {
            $key = $request['key'] ?? $index;
            $ch = $this->prepareCurlHandle($request['function'], $request['params'] ?? []);
            
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$key] = [
                'handle' => $ch,
                'request' => $request
            ];
        }

        // Ejecutar en paralelo
        $running = null;
        do {
            $status = curl_multi_exec($multiHandle, $running);
            if ($status > CURLM_OK) {
                break;
            }
            // Esperar actividad (evita CPU 100%)
            if ($running > 0) {
                curl_multi_select($multiHandle, 0.1);
            }
        } while ($running > 0);

        // Recoger resultados
        foreach ($curlHandles as $key => $data) {
            $ch = $data['handle'];
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);

            if ($error) {
                $results['errors'][$key] = [
                    'error' => "CURL Error: $error",
                    'request' => $data['request']
                ];
                $this->countError("CURL Error: $error");
                continue;
            }

            if ($httpCode >= 400) {
                $results['errors'][$key] = [
                    'error' => "HTTP $httpCode",
                    'request' => $data['request']
                ];
                $this->countError("HTTP $httpCode");
                continue;
            }

            // Verificar si la respuesta es vacía (indica timeout o crash del proceso remoto)
            if (empty($response)) {
                $results['errors'][$key] = [
                    'error' => 'Empty response (possible timeout/server crash)',
                    'request' => $data['request']
                ];
                $this->countError('Empty response');
                continue;
            }

            // Decodificar JSON
            $response = $this->cleanJsonResponse($response ?? '');
            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $results['errors'][$key] = [
                    'error' => 'JSON decode error: ' . json_last_error_msg(),
                    'request' => $data['request'],
                    'response_preview' => substr($response ?? '', 0, 500)
                ];
                $this->countError('JSON decode error');
                continue;
            }

            // Verificar error de Moodle
            if (isset($decoded['exception'])) {
                $results['errors'][$key] = [
                    'error' => $decoded['message'] ?? 'Moodle exception',
                    'errorcode' => $decoded['errorcode'] ?? 'unknown',
                    'request' => $data['request']
                ];
                $this->countError($decoded['exception']);
                continue;
            }

            $results['success'][$key] = $decoded;


        }

        curl_multi_close($multiHandle);
        return $results;
    }

    /**
     * Cuenta errores para logging agrupado
     */
    private function countError(string $errorType): void {
        // Normalizar el tipo de error
        $normalized = $this->normalizeErrorType($errorType);
        
        if (!isset($this->errorCounts[$normalized])) {
            $this->errorCounts[$normalized] = 0;
        }
        $this->errorCounts[$normalized]++;
    }

    /**
     * Normaliza tipos de error para agrupar mensajes similares
     */
    private function normalizeErrorType(string $error): string {
        if (stripos($error, 'timeout') !== false) {
            return 'Connection timeout';
        }
        if (stripos($error, 'resolve host') !== false) {
            return 'DNS resolution failed';
        }
        if (stripos($error, 'SSL') !== false) {
            return 'SSL error';
        }
        if (preg_match('/HTTP [45]\d\d/', $error, $matches)) {
            return $matches[0];
        }
        if (stripos($error, 'JSON') !== false) {
            return 'JSON parse error';
        }
        if (strlen($error) > 50) {
            return substr($error, 0, 50) . '...';
        }
        return $error;
    }

    /**
     * Registra resumen de errores (una línea por tipo)
     */
    private function logErrorSummary(int $total, int $success, array $errors): void {
        $failed = count($errors);

        if ($failed === 0) {
            LoggerService::info("MoodleParallelClient: Batch completado", [
                'total' => $total,
                'success' => $success,
                'failed' => 0
            ]);
            return;
        }

        $logContext = [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'success_rate' => round(($success / $total) * 100, 1) . '%',
            'error_breakdown' => $this->errorCounts,
            'failed_keys' => array_slice(array_keys($errors), 0, 10) // Primeros 10 IDs fallidos
        ];

        // Intento de diagnóstico: Incluir preview del primer error de JSON Parse
        // Esto ayuda a identificar si Moodle devuelve HTML (504/500/Maintenance)
        foreach ($errors as $errorData) {
            if (isset($errorData['response_preview']) && stripos($errorData['error'], 'JSON decode') !== false) {
                $logContext['first_json_error_preview'] = $errorData['response_preview'];
                break; // Solo necesitamos uno para ver qué pasa
            }
        }

        // Si hay errores, agruparlos en un solo log
        LoggerService::warning("MoodleParallelClient: Batch con errores", $logContext);
    }

    /**
     * Prepara un handle de CURL para una petición
     */
    private function prepareCurlHandle(string $functionName, array $params = []) {
        $params['wstoken'] = $this->token;
        $params['wsfunction'] = $functionName;
        $params['moodlewsrestformat'] = 'json';

        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'Connection: keep-alive' // Conexión persistente
            ],
            // Optimizaciones de rendimiento
            CURLOPT_TCP_FASTOPEN => true,
            CURLOPT_TCP_NODELAY => true,
        ]);

        // Configuración SSL
        if (MoodleWS::shouldVerifySSL()) {
            $cacertPath = dirname(__DIR__, 1) . '/../config/cacert.pem';
            if (file_exists($cacertPath)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_CAINFO, realpath($cacertPath));
            }
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        return $ch;
    }

    /**
     * Obtiene usuarios matriculados de múltiples cursos en paralelo
     * 
     * @param array $courseIds Lista de IDs de cursos
     * @return array [courseId => [users...]]
     */
    public function getEnrolledUsersParallel(array $courseIds): array {
        $requests = [];
        foreach ($courseIds as $courseId) {
            $requests[] = [
                'key' => (string)$courseId,
                'function' => MoodleWS::FUNCTIONS['GET_ENROLLED_USERS'],
                'params' => ['courseid' => $courseId]
            ];
        }

        $response = $this->executeParallel($requests);
        return $response['results'];
    }

    /**
     * Obtiene calificaciones de múltiples usuarios/cursos en paralelo
     * 
     * SEGURIDAD v3.2: Incluye validación de parámetros y límites
     * 
     * @param array $pairs Array de ['courseId' => int, 'userId' => int]
     * @return array Resultados indexados por "courseId_userId"
     * @throws MoodleException Si se exceden límites de seguridad
     */
    public function getGradesParallel(array $pairs): array {
        // Límite de seguridad para prevenir abuso/DoS
        $maxPairs = 2000;
        if (count($pairs) > $maxPairs) {
            LoggerService::warning("getGradesParallel: Límite de pares excedido", [
                'requested' => count($pairs),
                'max' => $maxPairs
            ]);
            throw new MoodleException("Límite de seguridad: máximo $maxPairs pares por llamada");
        }
        
        $requests = [];
        $skipped = 0;
        
        foreach ($pairs as $pair) {
            // Validación de existencia y tipo
            $courseId = $pair['courseId'] ?? null;
            $userId = $pair['userId'] ?? null;
            
            // Validar que sean enteros positivos
            if (!is_numeric($courseId) || (int)$courseId < 1) {
                $skipped++;
                continue;
            }
            if (!is_numeric($userId) || (int)$userId < 1) {
                $skipped++;
                continue;
            }
            
            // Cast seguro a enteros
            $courseId = (int)$courseId;
            $userId = (int)$userId;
            
            $key = $courseId . '_' . $userId;
            $requests[] = [
                'key' => $key,
                'function' => MoodleWS::FUNCTIONS['GET_COURSE_GRADES'],
                'params' => [
                    'courseid' => $courseId,
                    'userid' => $userId
                ]
            ];
        }
        
        // Log si hubo muchos pares inválidos (posible problema o ataque)
        if ($skipped > 10) {
            LoggerService::warning("getGradesParallel: Muchos pares inválidos saltados", [
                'skipped' => $skipped,
                'valid' => count($requests)
            ]);
        }
        
        if (empty($requests)) {
            return ['results' => [], 'errors' => [], 'stats' => ['total' => 0]];
        }

        $response = $this->executeParallel($requests);
        return $response;
    }

    /**
     * Obtiene calificaciones masivas agrupando usuarios por curso (Estrategia "Matrícula-Style")
     * OPTIMIZACIÓN RENDIMIENTO: Reduce requests de N a 1 por lote.
     * 
     * @param array $batches Array de ['courseId' => int, 'userIds' => int[]]
     * @return array Resultados indexados por "courseId_batchIndex"
     */
    public function getGradesBulkParallel(array $batches): array {
        $requests = [];
        
        foreach ($batches as $index => $batch) {
            $courseId = $batch['courseId'];
            $userIds = $batch['userIds'];
            
            if (empty($userIds)) continue;
            
            // Usar core_grades_get_grades
            $requests[] = [
                'key' => "{$courseId}_batch{$index}",
                'function' => MoodleWS::FUNCTIONS['GET_COURSE_GRADES_BULK'],
                'params' => [
                    'courseid' => $courseId,
                    'userids' => $userIds
                    // component y activityid opcionales se omiten para traer todo
                ]
            ];
        }

        return $this->executeParallel($requests);
    }

    /**
     * Obtiene información de usuarios por IDs en paralelo (chunks grandes)
     * 
     * @param array $userIds Lista de IDs
     * @return array Usuarios encontrados
     */
    public function getUsersByIdsParallel(array $userIds): array {
        // Dividir en chunks de USER_BATCH_SIZE
        $chunks = array_chunk($userIds, MoodleWS::USER_BATCH_SIZE);
        $requests = [];
        
        foreach ($chunks as $index => $chunk) {
            $requests[] = [
                'key' => 'chunk_' . $index,
                'function' => MoodleWS::FUNCTIONS['GET_USERS_BY_FIELD'],
                'params' => [
                    'field' => 'id',
                    'values' => array_map('strval', $chunk)
                ]
            ];
        }

        $response = $this->executeParallel($requests);
        
        // Aplanar resultados
        $allUsers = [];
        foreach ($response['results'] as $users) {
            if (is_array($users)) {
                foreach ($users as $user) {
                    $allUsers[$user['id']] = $user;
                }
            }
        }

        return $allUsers;
    }

    /**
     * Obtiene estadísticas actuales del cliente
     */
    public function getStats(): array {
        return [
            'max_parallel' => $this->maxParallel,
            'timeout' => $this->timeout,
            'max_retries' => self::MAX_RETRIES,
            'failure_threshold' => self::FAILURE_THRESHOLD,
            'circuit_breaker_status' => MoodleClient::getCircuitBreakerStatus()
        ];
    }
    /**
     * Limpia la respuesta JSON de posibles caracteres o textos basura (BOM, warnings PHP, etc.)
     */
    private function cleanJsonResponse(string $response): string {
        $response = trim($response);
        
        // Remover BOM si existe
        $bom = pack('H*','EFBBBF');
        $response = preg_replace("/^$bom/", '', $response);
        
        // Si no empieza con { o [, intentar encontrar el inicio del JSON
        if (!empty($response) && $response[0] !== '{' && $response[0] !== '[') {
            $startArray = strpos($response, '[');
            $startObject = strpos($response, '{');
            
            $start = false;
            if ($startArray !== false && $startObject !== false) {
                $start = min($startArray, $startObject);
            } elseif ($startArray !== false) {
                $start = $startArray;
            } elseif ($startObject !== false) {
                $start = $startObject;
            }
            
            if ($start !== false) {
                $response = substr($response, $start);
                // Si encontramos el inicio, intentamos limpiar el final también si parece haber basura
                // (Opcional, pero json_decode puede fallar si hay texto después)
            }
        }
        
        return $response;
    }
}

