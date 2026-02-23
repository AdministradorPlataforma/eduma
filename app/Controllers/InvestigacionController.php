<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\InvestigacionService;
use App\Models\Investigacion\TesisModel;
use App\Helpers\CSRFHelper;
use App\Helpers\ValidationHelper;
use App\Services\ExportService;
use App\DTOs\Investigacion\TesisDTO;
use Config\Database;

class InvestigacionController extends BaseController {
    
    private InvestigacionService $service;
    private TesisModel $model;
    private \App\Repositories\Investigacion\TesisRepository $repository;
    private ExportService $exportService;

    /**
     * Endpoint AJAX para buscar estudiantes o docentes (Select2)
     */
    public function buscarParticipantes() {
        try {
            $this->requireLogin();

            $term = $_GET['q'] ?? '';
            $type = $_GET['type'] ?? 'estudiante'; // estudiante | docente

            if (strlen($term) < 2) {
                echo json_encode(['results' => []]);
                exit;
            }

            $results = [];
            if ($type === 'estudiante') {
                $data = $this->service->buscarEstudiantes($term);
                foreach ($data as $item) {
                    $legajo = $item['legajo'] ? " - Leg: {$item['legajo']}" : "";
                    $username = $item['username'] ? " ({$item['username']})" : "";
                    $results[] = [
                        'id' => $item['id'],
                        'text' => "{$item['apellido']}, {$item['nombre']}{$username}{$legajo}"
                    ];
                }
            } else {
                $data = $this->service->buscarDocentes($term);
                foreach ($data as $item) {
                    $titulo = $item['titulo_profesional'] ? " - {$item['titulo_profesional']}" : "";
                    $username = $item['username'] ? " ({$item['username']})" : "";
                    $results[] = [
                        'id' => $item['id'],
                        'text' => "{$item['apellido']}, {$item['nombre']}{$username}{$titulo}"
                    ];
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['results' => $results]);
            exit;

        } catch (\Exception $e) {
            \App\Helpers\LoggerHelper::error($e, ['action' => 'buscar_participantes']);
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * El Container ahora inyecta automáticamente las dependencias
     */
    public function __construct(
        InvestigacionService $service, 
        TesisModel $model, 
        \App\Repositories\Investigacion\TesisRepository $repository,
        ExportService $exportService
    ) {
        parent::__construct();
        $this->service = $service;
        $this->model = $model;
        $this->repository = $repository;
        $this->exportService = $exportService;
    }

    public function index() {
        $this->requirePermission('investigacion.ver');
        $this->requireLogin();
        
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        
        // Usar Paginación Refactorizada
        $result = $this->repository->paginate($page, 15);
        $tesis = $result['data'];
        $meta = $result['meta'];
        
        // Preparar array de paginación para la vista simple
        $pagination = [
            'current' => $meta['current_page'],
            'total_pages' => $meta['last_page'],
            'has_prev' => $meta['current_page'] > 1,
            'has_next' => $meta['current_page'] < $meta['last_page'],
            'prev_page' => $meta['current_page'] - 1,
            'next_page' => $meta['current_page'] + 1
        ];

        // AJAX Partial Render
        if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
             // Render only the partial
             // Since we don't have a partial render helper in BaseController (usually),
             // we can include the file directly and exit, or extract partial render logic.
             // We'll simplisticly include and exit to avoid full layout.
             
             // Define variables for view scope
             // The compact() or simple extraction
             include __DIR__ . '/../Views/Investigacion/Partials/TablaTesis.php';
             exit;
        }

        $this->render('Investigacion/ListarTesis', [
            'tesis' => $tesis,
            'pagination' => $pagination,
            'title' => 'Gestión de Tesis'
        ]);
    }

    public function registrar() {
        $this->requirePermission('investigacion.crear');
        $this->requireLogin();
        
        $this->render('Investigacion/RegistrarTesis', [
            'title' => 'Registrar Nueva Tesis',
        ]);
    }

    public function guardar() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('investigacion/registrar');
        }

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->logSecurity("Fallo de validación CSRF en registro de tesis");
            $this->flash('error', 'Sesión de formulario expirada. Intente nuevamente.');
            $this->redirect('investigacion/registrar');
        }

        try {
            // Validate basic input
            $val = ValidationHelper::make($_POST);
            $val->rule('titulo', 'required|min:5');
            
            if (empty($_POST['estudiantes']) || !is_array($_POST['estudiantes'])) {
                $this->flash('error', 'Debe agregar al menos un estudiante.');
                $this->redirect('investigacion/registrar');
                return;
            }
            if (empty($_POST['docentes']) || !is_array($_POST['docentes'])) {
                $this->flash('error', 'Debe agregar al menos un docente (tutor/asesor).');
                $this->redirect('investigacion/registrar');
                return;
            }

            if ($val->fails()) {
                $this->flash('error', $val->firstError());
                $this->redirect('investigacion/registrar');
                return;
            }

            // Get Director ID (Logged in user)
            $directorId = (int) $this->getUserId();
            if ($directorId === 0) {
                 throw new \Exception("Usuario no identificado.");
            }

            // Usar DTO
            $dto = TesisDTO::fromRequest($_POST, $_FILES);
            $this->service->registrarTesis($dto, $directorId);
            
            $this->flash('success', 'Tesis registrada correctamente.');
            $this->redirect('investigacion');

        } catch (\Exception $e) {
            \App\Helpers\LoggerHelper::error($e, ['action' => 'guardar_tesis', 'data' => $_POST]);
            $this->flash('error', 'Error al procesar la tesis: ' . $e->getMessage());
            $this->redirect('investigacion/registrar');
        }
    }

    public function ver($id) {
        $this->requireLogin();
        $tesis = $this->service->getTesisById((int)$id);
        
        if (!$tesis) {
            $this->flash('error', 'Tesis no encontrada.');
            $this->redirect('investigacion');
            return;
        }

        $this->render('Investigacion/VerTesis', [
            'tesis' => $tesis,
            'title' => 'Detalle de Tesis'
        ]);
    }

    public function editar($id) {
        $this->requirePermission('investigacion.editar');
        $this->requireLogin();
        $tesis = $this->service->getTesisById((int)$id);

        if (!$tesis) {
            $this->flash('error', 'Tesis no encontrada o ha sido eliminada.');
            $this->redirect('investigacion');
            return;
        }

        $this->render('Investigacion/EditarTesis', [
            'tesis' => $tesis,
            'title' => 'Editar Tesis'
        ]);
    }

    public function actualizar($id) {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect("investigacion/editar/$id");
        }

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->logSecurity("Fallo de validación CSRF en actualización de tesis (ID: $id)");
            $this->flash('error', 'Sesión de formulario expirada. Intente nuevamente.');
            $this->redirect("investigacion/editar/$id");
        }

        try {
            $val = ValidationHelper::make($_POST);
            $val->rule('titulo', 'required|min:5');
            
            if (empty($_POST['estudiantes']) || !is_array($_POST['estudiantes'])) {
                $this->flash('error', 'Debe agregar al menos un estudiante.');
                $this->redirect("investigacion/editar/$id");
                return;
            }

            if ($val->fails()) {
                $this->flash('error', $val->firstError());
                $this->redirect("investigacion/editar/$id");
                return;
            }
            
            // Usar DTO
            $dto = TesisDTO::fromRequest($_POST, $_FILES);
            $this->service->actualizarTesis((int)$id, $dto);

            $this->flash('success', 'Tesis actualizada correctamente.');
            $this->redirect('investigacion');

        } catch (\Exception $e) {
            \App\Helpers\LoggerHelper::error($e, ['action' => 'actualizar_tesis', 'id' => $id, 'data' => $_POST]);
            $this->flash('error', 'Error al actualizar la tesis: ' . $e->getMessage());
            $this->redirect("investigacion/editar/$id");
        }
    }

    public function eliminar($id) {
        $this->requirePermission('investigacion.eliminar');
        $this->requireLogin();
        
        // Should ideally be POST/DELETE method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             $this->redirect('investigacion');
        }

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->logSecurity("Fallo de validación CSRF en eliminación de tesis (ID: $id)");
            $this->flash('error', 'Error de seguridad en la solicitud.');
            $this->redirect('investigacion');
        }

        try {
            $this->service->eliminarTesis((int)$id);
            $this->flash('success', 'Tesis eliminada correctamente.');
        } catch (\Exception $e) {
            $this->flash('error', 'Error al eliminar: ' . $e->getMessage());
        }

        $this->redirect('investigacion');
    }

    /**
     * Genera el Acta de Aprobación en PDF
     */

    public function generarActa($id) {
        $this->requirePermission('investigacion.ver');
        $this->requireLogin();

        $tesisFull = $this->service->getTesisById((int)$id);
        
        if (!$tesisFull) {
            $this->flash('error', 'Tesis no encontrada.');
            $this->redirect('investigacion');
            return;
        }

        // Format names for PDF
        $estNombres = array_map(function($e) { return $e['nombre'] . ' ' . $e['apellido']; }, $tesisFull['estudiantes'] ?? []);
        $docNombres = array_map(function($d) { return $d['nombre'] . ' ' . $d['apellido']; }, $tesisFull['docentes'] ?? []);
        
        $tesisFull['estudiantes_nombres'] = implode(' / ', $estNombres);
        $tesisFull['tutores_nombres'] = implode(' / ', $docNombres);

        try {
            // Instantiate PdfService manually or via container if registered
            // Since we didn't add it to constructor yet (container injection is usually auto but cumbersome if constructor changed too much without container config update)
            // We'll instantiate manually for this specific action.
            $pdfService = new \App\Services\PdfService();
            $pdfService->generateActaAprobacion($tesisFull);
            
        } catch (\Exception $e) {
            \App\Helpers\LoggerHelper::error($e, ['action' => 'generar_acta_pdf', 'id' => $id]);
            $this->flash('error', 'Error al generar el PDF: ' . $e->getMessage());
            $this->redirect('investigacion');
        }
    }

    /**
     * Exporta el listado de tesis a un archivo Excel (.xlsx) usando ExportService
     */
    public function exportar() {
        $this->requirePermission('investigacion.ver');
        $this->requireLogin();

        // Obtenemos todas sin paginar para el reporte
        // Aquí necesitamos getAll() pero lo hemos borrado... 
        // Usamos paginate con un limit alto o creamos un método específico en repo para export.
        // Por ahora usamos paginate con 10000 records.
        $result = $this->repository->paginate(1, 10000);
        $tesis = $result['data'];

        $this->exportService->exportTesisToExcel($tesis);
    }
}
