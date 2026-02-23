<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLogModel;

class AuditController extends BaseController {
    
    private AuditLogModel $auditModel;
    private \App\Models\Usuario\UsuarioModel $userModel;

    public function __construct(AuditLogModel $auditModel, \App\Models\Usuario\UsuarioModel $userModel) {
        parent::__construct();
        $this->auditModel = $auditModel;
        $this->userModel = $userModel;
    }

    public function index() {
        $this->requireLogin();
        // $this->requirePermission('ver_auditoria'); // Descomentar cuando RBAC esté full

        $filters = [
            'start_date' => $_GET['start_date'] ?? null,
            'end_date' => $_GET['end_date'] ?? null,
            'user_id' => $_GET['user_id'] ?? null,
            'action' => $_GET['action'] ?? null
        ];

        // Remove empty filters
        $filters = array_filter($filters);
        
        $logs = $this->auditModel->getLogsFiltered($filters, 200);
        
        // Obtener estadísticas para los KPIs superiores
        $today = date('Y-m-d');
        $stats = [
            'total_today' => $this->auditModel->builder()->where('created_at', '>=', $today . ' 00:00:00')->count(),
            'unique_users' => $this->auditModel->builder()->where('created_at', '>=', $today . ' 00:00:00')->groupBy('user_id')->count(),
            'security_events' => $this->auditModel->builder()->where('action', 'LIKE', '%SECURITY%')->orWhere('action', 'LIKE', '%LOGIN_FAIL%')->count()
        ];
        
        // Obtener lista de usuarios para el filtro select
        $users = $this->userModel->builder()->select(['id', 'username', 'nombre', 'apellido'])->orderBy('username')->get();
        
        $pageTitle = "Logs de Auditoría";
        
        // Usar render de BaseController para inyeccion de dependencias (notificaciones)
        $this->render('Audit/index', [
            'pageTitle' => $pageTitle,
            'logs' => $logs,
            'users' => $users,
            'filters' => $filters,
            'stats' => $stats,
            'extraCSS' => 'Audit'
        ]);
    }
}
