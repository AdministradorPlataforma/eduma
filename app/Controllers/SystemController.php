<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\JanitorService;
use App\Helpers\ApiResponse;

class SystemController extends BaseController
{
    private JanitorService $janitor;
    private \App\Services\SystemHealthService $healthService;

    public function __construct(JanitorService $janitor, \App\Services\SystemHealthService $healthService)
    {
        parent::__construct();
        $this->janitor = $janitor;
        $this->healthService = $healthService;
    }

    /**
     * Ejecuta el mantenimiento del sistema (Janitor) bajo demanda.
     */
    public function runJanitor()
    {
        $this->requirePermission('sistema.ver');

        try {
            $report = $this->janitor->runAll();
            
            return ApiResponse::success([
                'message' => 'Mantenimiento completado exitosamente',
                'details' => $report
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al ejecutar mantenimiento: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Dashboard principal de Gestión de Sistema
     */
    public function index()
    {
        $this->requirePermission('sistema.ver');

        // Get System Health Metrics
        $health = $this->healthService->getSystemHealth();

        // Get Migration Status
        $runner = new \App\Core\Database\MigrationRunner();
        $migrations = $runner->getStatus();

        $this->render('Sistema/index', [
            'pageTitle' => 'Sistema y Configuración',
            'health' => $health,
            'migrations' => $migrations
        ]);
    }

    /**
     * Ejecuta las migraciones pendientes
     */
    public function migrate()
    {
        $this->requirePermission('sistema.ver');

        try {
            $runner = new \App\Core\Database\MigrationRunner();
            $processed = $runner->run();
            
            if (empty($processed)) {
                return \App\Helpers\ApiResponse::success(['message' => 'El sistema ya está actualizado.']);
            }

            \App\Helpers\ApiResponse::success($processed, 'Base de datos actualizada correctamente');
        } catch (\Exception $e) {
            \App\Helpers\ApiResponse::error('Error al migrar: ' . $e->getMessage(), 500);
        }
    }
}
