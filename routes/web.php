<?php
declare(strict_types=1);

/**
 * ============================================================================
 * EDUMA - Definición de Rutas Profesionales
 * ============================================================================
 * @var App\Core\Router $router
 */

use App\Controllers\{
    AuthController, DashboardController, UsuarioController, RolController, 
    PermisoController, UsuarioPerfilController, AuditController, 
    UserSessionController, NotificationController,
    GestionController, PredictionController, InvestigacionController,
    MoodleController, MoodleWebhookController, SystemController, 
    RecycleBinController
};

// ----------------------------------------------------------------------------
// 1. RUTAS PÚBLICAS Y AUTENTICACIÓN
// ----------------------------------------------------------------------------
$router->get('', [AuthController::class, 'showLogin']);
$router->get('login', [AuthController::class, 'showLogin']);
$router->post('login', [AuthController::class, 'login']);
$router->get('logout', [AuthController::class, 'logout']);
$router->get('captcha/image', [AuthController::class, 'captchaImage']);

// ----------------------------------------------------------------------------
// WEBHOOKS Y API PÚBLICAS (Sin auth de sesión — autenticación por token)
// ----------------------------------------------------------------------------
// Moodle NO tiene sesión PHP, por lo que el webhook debe estar fuera del
// grupo 'auth'. La autenticación se realiza por token en el controller.
$router->post('api/webhook/moodle', [MoodleWebhookController::class, 'handle']);

// Recuperación de Contraseña
$router->group(['prefix' => 'password'], function($r) {
    $r->get('forgot', [\App\Controllers\PasswordResetController::class, 'showLinkRequestForm']);
    $r->post('email', [\App\Controllers\PasswordResetController::class, 'sendResetLinkEmail']);
    $r->get('reset/{token}', [\App\Controllers\PasswordResetController::class, 'showResetForm']);
    $r->post('reset', [\App\Controllers\PasswordResetController::class, 'reset']);
});

// Errores Globales
$router->get('errors/403', function() { require_once __DIR__ . '/../app/Views/errors/403.php'; });

// Setup RBAC movido a sección protegida (ver abajo)

// ----------------------------------------------------------------------------
// 2. RUTAS PROTEGIDAS (REQUIEREN LOGIN)
// ----------------------------------------------------------------------------
$router->group(['middleware' => 'auth'], function($r) {
    
    // --- Dashboard ---
    $r->get('escritorio', [DashboardController::class, 'index']);
    $r->get('escritorio/ajax/stats', [DashboardController::class, 'ajaxChartData']); 

    // --- Perfil de Usuario ---
    $r->group(['prefix' => 'perfil'], function($r) {
        $r->get('', [UsuarioPerfilController::class, 'index']);
        $r->post('actualizar', [UsuarioPerfilController::class, 'actualizarPerfil']);
        $r->get('sesiones', [UserSessionController::class, 'index']);
        $r->post('sesiones/revoke', [UserSessionController::class, 'revoke']);
    });

    // --- Notificaciones ---
    $r->post('notificaciones/leer-todas', [NotificationController::class, 'leerTodas']);
    $r->post('notificaciones/leer/{id}', [NotificationController::class, 'leer']);

    // ----------------------------------------------------------------------------
    // 3. ADMINISTRACIÓN Y RBAC (REQUIEREN PERMISOS ESPECÍFICOS)
    // ----------------------------------------------------------------------------

    // Gestión de Usuarios
    $r->group(['middleware' => 'permission:ver_usuario'], function($r) {
        $r->get('usuario', [UsuarioController::class, 'index']);
        $r->get('usuario/datatable', [UsuarioController::class, 'datatable']);
        $r->get('admin/sesiones', [UserSessionController::class, 'admin']);
        $r->post('admin/sesiones/force-revoke', [UserSessionController::class, 'forceRevoke']);
        
        $r->group(['middleware' => 'permission:crear_usuario'], function($r) {
            $r->get('usuario/crear', [UsuarioController::class, 'create']);
            $r->post('usuario/crear', [UsuarioController::class, 'store']);
        });
        
        $r->group(['middleware' => 'permission:editar_usuario'], function($r) {
            $r->get('usuario/editar/{id}', [UsuarioController::class, 'edit']);
            $r->post('usuario/editar/{id}', [UsuarioController::class, 'update']);
        });
        
        $r->group(['middleware' => 'permission:eliminar_usuario'], function($r) {
            $r->post('usuario/eliminar/{id}', [UsuarioController::class, 'eliminar']);
        });
    });

    // Gestión de Roles y Permisos
    $r->group(['middleware' => 'permission:ver_rol|ver_permiso'], function($r) {
        $r->get('rol', [RolController::class, 'index']);
        $r->get('permiso', [PermisoController::class, 'index']);
        
        // Operaciones de Escritura (Protección individual)
        $r->post('rol/crear', [RolController::class, 'store'], ['middleware' => 'permission:crear_rol']);
        $r->post('rol/editar/{id}', [RolController::class, 'update'], ['middleware' => 'permission:editar_rol']);
        $r->post('rol/eliminar/{id}', [RolController::class, 'eliminar'], ['middleware' => 'permission:eliminar_rol']);
        
        $r->post('permiso/crear', [PermisoController::class, 'store'], ['middleware' => 'permission:crear_permiso']);
        $r->post('permiso/editar/{id}', [PermisoController::class, 'update'], ['middleware' => 'permission:editar_permiso']);
        $r->post('permiso/eliminar/{id}', [PermisoController::class, 'eliminar'], ['middleware' => 'permission:eliminar_permiso']);
    });

    // Auditoría
    $r->group(['middleware' => 'permission:ver_auditoria'], function($r) {
        $r->get('audit', [AuditController::class, 'index']);
    });

    // ----------------------------------------------------------------------------
    // 4. MÓDULOS ACADÉMICOS
    // ----------------------------------------------------------------------------

    // Sistema y Mantenimiento (Protegidos)
    // Sistema y Mantenimiento (Protegidos)
    $r->group(['middleware' => 'permission:sistema.ver'], function($r) {
        $r->get('sistema', [SystemController::class, 'index']);
        $r->post('sistema/migrate', [SystemController::class, 'migrate']);
        $r->post('sistema/janitor/run', [SystemController::class, 'runJanitor']);
    });

    // Papelera de Reciclaje
    $r->group(['middleware' => 'permission:papelera.gestionar', 'prefix' => 'recycle-bin'], function($r) {
        $r->get('', [RecycleBinController::class, 'index']);
        $r->get('restore', [RecycleBinController::class, 'restore']);
        $r->get('purge', [RecycleBinController::class, 'purge']);
    });

    // Setup RBAC
    $r->get('setup/rbac', function() {
        $session = new \App\Helpers\SessionHelper();
        // Doble verificación: Debe estar logueado y tener permiso explícito
        if (!$session->isLoggedIn() || !$session->hasPermission('rbac.configurar')) {
            // Si es CLI, permitir
            if (php_sapi_name() !== 'cli') {
                 header('HTTP/1.0 403 Forbidden');
                 echo "Acceso Denegado. Se requiere permiso 'rbac.configurar'.";
                 exit;
            }
        }
        
        $setup = new \App\Services\RbacSetupService();
        echo $setup->runSetup();
    });

    // Módulos Académicos
    // Sincronización Moodle
    $r->group(['middleware' => 'permission:sincronizar_moodle', 'prefix' => 'moodle'], function($r) {
        $r->get('', [MoodleController::class, 'index']);
        $r->get('health', [MoodleController::class, 'health']);
        
        // Control de Procesos y Circuit Breaker
        $r->post('reset-processes', [MoodleController::class, 'resetProcesses']);
        $r->post('reset-circuit', [MoodleController::class, 'resetCircuit']);
        
        // Sincronización Asíncrona (SSE y Estado)
        $r->group(['prefix' => 'sync'], function($r) {
            $r->post('asyncStart', [MoodleController::class, 'asyncStart']);
            $r->post('asyncStop', [MoodleController::class, 'asyncStop']);
            $r->get('getAsyncStatus', [MoodleController::class, 'getAsyncStatus']);
            $r->get('streamProgress', [MoodleController::class, 'streamProgress']);
            $r->post('{entity}', [MoodleController::class, 'sync']);
        });
        
        $r->get('jobs/status', [MoodleController::class, 'jobsStatus']);
        
        // Cleanup Tools
        $r->group(['prefix' => 'cleanup'], function($r) {
            $r->get('summary', [MoodleController::class, 'cleanupSummary']);
            $r->post('execute', [MoodleController::class, 'cleanupExecute']);
            $r->post('enrollments', [MoodleController::class, 'cleanupEnrollments']);
            $r->post('reactivate-user', [MoodleController::class, 'reactivateUser']);
            $r->post('reactivate-course', [MoodleController::class, 'reactivateCourse']);
            $r->get('orphan-users', [MoodleController::class, 'orphanUsers']);
            $r->get('orphan-courses', [MoodleController::class, 'orphanCourses']);
        });

        // Webhook Moodle movido a sección pública (línea ~28)
    });

    // Gestión Académica y Tareas
    $r->group(['prefix' => 'gestion'], function($r) {
        $r->get('', [GestionController::class, 'index']);
        $r->post('subir-evidencia', [GestionController::class, 'subirEvidencia']);
        
        // Admin Tareas
        $r->group(['middleware' => 'permission:admin_gestion'], function($r) {
            $r->get('admin', [GestionController::class, 'admin']);
            $r->post('guardar', [GestionController::class, 'guardarTarea']);
            $r->post('eliminar/{id}', [GestionController::class, 'eliminarTarea']);
        });
    });

    // Investigación (Tesis)
    $r->group(['prefix' => 'investigacion'], function($r) {
        $r->get('', [InvestigacionController::class, 'index']);
        $r->get('ver/{id}', [InvestigacionController::class, 'ver']);
        $r->get('buscar-participantes', [InvestigacionController::class, 'buscarParticipantes']);
        $r->get('exportar', [InvestigacionController::class, 'exportar']);
        
        $r->group(['middleware' => 'permission:investigacion.crear|investigacion.editar|investigacion.eliminar'], function($r) {
            $r->get('registrar', [InvestigacionController::class, 'registrar']);
            $r->post('guardar', [InvestigacionController::class, 'guardar']);
            $r->get('editar/{id}', [InvestigacionController::class, 'editar']);
            $r->post('actualizar/{id}', [InvestigacionController::class, 'actualizar']);
            $r->post('eliminar/{id}', [InvestigacionController::class, 'eliminar']);
        });
    });

    // Predicción Académica (IA)
    $r->group(['prefix' => 'prediccion'], function($r) {
        $r->get('docente', [PredictionController::class, 'teacherDashboard'], ['middleware' => 'permission:ver_cursos']);
        $r->get('api/estudiante/{id}', [PredictionController::class, 'studentScore']);
    });
});

