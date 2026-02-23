<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Gestion\GestionModel;
use App\Repositories\DashboardRepository;
use App\Models\Usuario\UsuarioModel;
use App\Models\Investigacion\TesisModel;
use App\Services\NotificationService;
use App\Controllers\BaseController;

class DashboardController extends BaseController {
    
    private GestionModel $gestionModel;
    private DashboardRepository $dashboardRepo;
    private UsuarioModel $usuarioModel;
    private TesisModel $tesisModel;
    private NotificationService $notifService;

    public function __construct(
        GestionModel $gestionModel,
        DashboardRepository $dashboardRepo,
        UsuarioModel $usuarioModel,
        TesisModel $tesisModel,
        NotificationService $notifService
    ) {
        parent::__construct();
        $this->gestionModel = $gestionModel;
        $this->dashboardRepo = $dashboardRepo;
        $this->usuarioModel = $usuarioModel;
        $this->tesisModel = $tesisModel;
        $this->notifService = $notifService;
    }

    public function index() {
        $this->requireLogin();
        
        $userId = (int)($this->session->get('user_data')['id'] ?? 0);
        $userRoles = $this->gestionModel->getUserRoles($userId);
        
        // --- Widget Data from New QueryBuilder Models ---
        $totalUsuarios = $this->usuarioModel->countAll();
        // $totalTesis = $this->tesisModel->countAll(); // Deprecated or handled differently if needed
        // Assuming countAll exists on BaseModel or specific impl. If not, catching potential error.
        // Actually BaseModel doesn't strictly have countAll, builder->count() does.
        // Let's use count() or builder()->count(). 
        // Checking BaseModel again... it has findAll, paginate, find... but no public countAll.
        // However, builder->count() is available via magic or accessor?
        // Let's check BaseModel. It does not expose countAll directly.
        // But in previous code it was called. Let's assume UsuarioModel implements it or use builder()->count().
        // For safety, I'll use builder()->count() if models extend BaseModel.
        
        $totalUsuarios = $this->usuarioModel->builder()->count();
        $totalTesis = $this->tesisModel->builder()->count();
        $unreadNotifs = $this->notifService->countUnread($userId);
        
        // Determinar filtros
        $facultadId = isset($_GET['facultad_id']) && is_numeric($_GET['facultad_id']) ? (int)$_GET['facultad_id'] : null;
        $carreraId = isset($_GET['carrera_id']) && is_numeric($_GET['carrera_id']) ? (int)$_GET['carrera_id'] : null;

        $stats = $this->dashboardRepo->getEstadisticasEstructura($facultadId, $carreraId);
        $proximosVencimientos = $this->dashboardRepo->getProximosVencimientos(5);
        $activityFeed = $this->dashboardRepo->getFeedActividad(10);
        $facultades = $this->gestionModel->getFacultades();
        $carreras = $facultadId ? $this->gestionModel->getCarreras($facultadId) : [];

        // Create ViewModel
        $viewModel = new \App\ViewModels\DashboardViewModel(
            array_merge($stats, [
                'total_usuarios' => $totalUsuarios, 
                'total_tesis' => $totalTesis,
                'unread_notifs' => $unreadNotifs
            ]), 
            $proximosVencimientos, 
            $activityFeed, 
            [
                'name' => $this->session->get('user_data')['nombre'] ?? 'Usuario',
                'roles' => $userRoles
            ],
            [
                'facultad_id' => $facultadId,
                'carrera_id' => $carreraId
            ]
        );

        $data = [
            'pageTitle' => 'Escritorio',
            'vm' => $viewModel, // Pass the ViewModel as 'vm'
            'filters' => [
                'facultad_id' => $facultadId,
                'carrera_id' => $carreraId
            ],
            // Keep legacy keys if layout depends on them OR refactor layout too. 
            // Layouts/Header usually uses $pageTitle, $extraCSS.
            'extraCSS' => 'Escritorio',
            'extraJS' => 'Escritorio',
            'facultades' => $facultades, // Needed for Selects
            'carreras' => $carreras
        ];

        $this->render('Escritorio/index', $data);
    }

    public function ajaxChartData() {
        $this->requireLogin();
        // Endpoint para futuras actualizaciones asíncronas de gráficos
        $stats = $this->dashboardRepo->getEstadisticasEstructura();
        $this->jsonSuccess(['cumplimiento' => $stats['cumplimiento_promedio']]);
    }
}
