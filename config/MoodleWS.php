<?php
declare(strict_types=1);

namespace Config;

class MoodleWS {
    
    // Fallback constants (Development)
    public const URL = '';
    public const TOKEN = '';
    
    // Configuración de tiempos de espera (aumentado para grandes volúmenes y latencia)
    public const TIMEOUT = 180; // segundos - aumentado drásticamente
    public const CONNECT_TIMEOUT = 60; // tiempo máximo para establecer conexión (antes 30)
    
    // Configuración de reintentos
    public const MAX_RETRIES = 3;
    public const RETRY_DELAY_MS = 1000; // delay inicial entre reintentos
    public const BACKOFF_MULTIPLIER = 2; // multiplicador exponencial
    
    // Configuración de batches (OPTIMIZADO para estabilidad - Reducido por errores SSL/Timeouts)
    public const DEFAULT_BATCH_SIZE = 50;   // Reducido de 100
    public const USER_BATCH_SIZE = 50;      // Reducido de 100
    public const COURSE_BATCH_SIZE = 50;    // Reducido de 100
    public const GRADE_BATCH_SIZE = 5;      // Reducido de 10
    public const COHORT_BATCH_SIZE = 50;    // Reducido de 100
    
    // Configuración de procesamiento paralelo (curl_multi)
    public const PARALLEL_REQUESTS = 5;     // REDUCIDO de 10 a 5 por inestabilidad de red (CURL 35/28)
    public const PARALLEL_TIMEOUT = 120;    // Aumentado de 60
    
    // Configuración de Bulk INSERT
    public const BULK_INSERT_SIZE = 200;    // Registros por INSERT (reducido por seguridad de buffer)
    public const BULK_INSERT_MAX = 1000;    // Máximo absoluto por operación
    
    // Configuración de sincronización incremental
    public const SYNC_LOOKBACK_HOURS = 24;  // Horas hacia atrás para sync delta
    public const FULL_SYNC_INTERVAL_DAYS = 7; // Días entre syncs completos
    
    // Funciones permitidas (Validadas por Usuario)
    public const FUNCTIONS = [
        'GET_CATEGORIES' => 'core_course_get_categories',
        'GET_COURSES' => 'core_course_get_courses',             
        'GET_USERS' => 'core_user_get_users',                    // Añadido - faltaba
        'GET_USERS_BY_FIELD' => 'core_user_get_users_by_field', // Batch por campo
        'GET_COHORTS' => 'core_cohort_get_cohorts',             
        'GET_COURSE_GRADES' => 'gradereport_user_get_grade_items', // Grades per course/user
        'GET_COURSE_GRADES_BULK' => 'core_grades_get_grades',      // Bulk grades
        'GET_ENROLLED_USERS' => 'core_enrol_get_enrolled_users',   // To map users to course for grades
        'SITE_INFO' => 'core_webservice_get_site_info',          // Health check
    ];

    public static function getUrl(): string {
        $envUrl = Env::get('MOODLE_URL');
        return !empty($envUrl) ? $envUrl : self::URL;
    }

    public static function getToken(): string {
        $envToken = Env::get('MOODLE_TOKEN');
        return !empty($envToken) ? $envToken : self::TOKEN;
    }

    public static function getTimeout(): int {
        $envTimeout = Env::get('MOODLE_TIMEOUT');
        return !empty($envTimeout) ? (int)$envTimeout : self::TIMEOUT;
    }

    public static function getParallelRequests(): int {
        $envParallel = Env::get('MOODLE_PARALLEL_REQUESTS');
        return !empty($envParallel) ? (int)$envParallel : self::PARALLEL_REQUESTS;
    }

    public static function shouldVerifySSL(): bool {
        $appEnv = Env::get('APP_ENV') ?? 'production';
        
        // SEGURIDAD: En producción, SIEMPRE verificar SSL
        // Esto previene ataques Man-in-the-Middle en la sincronización de calificaciones
        if ($appEnv === 'production') {
            $envVerify = Env::get('MOODLE_VERIFY_SSL');
            
            // Si alguien intenta deshabilitar SSL en producción, logear y forzar
            if ($envVerify !== null && !filter_var($envVerify, FILTER_VALIDATE_BOOLEAN)) {
                // Usar LoggerService si está disponible
                if (class_exists('\App\Services\LoggerService')) {
                    \App\Services\LoggerService::warning(
                        "SEGURIDAD: Intento de deshabilitar SSL en producción ignorado",
                        ['env_value' => $envVerify]
                    );
                }
            }
            
            return true; // SIEMPRE true en producción
        }
        
        // En desarrollo/staging, permitir configuración
        $envVerify = Env::get('MOODLE_VERIFY_SSL');
        
        if ($envVerify === null) {
            return true; // Default seguro
        }
        
        $verify = filter_var($envVerify, FILTER_VALIDATE_BOOLEAN);
        
        // Warning si se deshabilita incluso en desarrollo
        if (!$verify && class_exists('\App\Services\LoggerService')) {
            \App\Services\LoggerService::info(
                "MOODLE_VERIFY_SSL deshabilitado en entorno de desarrollo",
                ['app_env' => $appEnv]
            );
        }
        
        return $verify;
    }
}
