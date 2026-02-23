<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\System\TaskLogModel;
use App\Helpers\ApiResponse;

class SchedulerController extends BaseController {
    
    protected TaskLogModel $logModel;

    public function __construct() {
        parent::__construct();
        $db = \App\Core\Container::getInstance()->get('db');
        $this->logModel = new TaskLogModel($db);
    }

    /**
     * Muestra el dashboard del Scheduler
     */
    public function index() {
        $this->requirePermission('ver_configuracion');

        $stats = $this->logModel->getStats();
        $logs = $this->logModel->getRecentLogs(50);

        $this->render('Sistema/scheduler', [
            'pageTitle' => 'Tareas Programadas',
            'stats' => $stats,
            'logs' => $logs
        ]);
    }
    
    /**
     * API para obtener logs en tiempo real (ajax)
     */
    public function apiLogs() {
        $this->requirePermission('ver_configuracion');
        
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $logs = $this->logModel->getRecentLogs($limit);
        
        ApiResponse::success($logs);
    }
}
