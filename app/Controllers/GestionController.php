<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Gestion\GestionModel;
use App\Helpers\CSRFHelper;
use App\Helpers\InputSanitizerHelper;

class GestionController extends BaseController {
    
    private $gestionModel;

    public function __construct(GestionModel $gestionModel) {
        parent::__construct();
        $this->gestionModel = $gestionModel;
    }

    public function index() {
        $userId = $_SESSION['user_id'];
        
        // Obtener tareas del usuario
        $tareas = $this->gestionModel->getPendientesPorUsuario($userId);
        
        // Verificar alertas para Toastr (Vencen en < 48 horas i.e. 'warning' o ya 'danger')
        $alertas = [];
        foreach ($tareas as $t) {
            // Si no está cumplida y el semáforo es warning o danger
            if (!$t['evidencia_id'] && ($t['semaforo'] === 'warning' || $t['semaforo'] === 'danger')) {
                $alertas[] = "La tarea '{$t['producto_documento']}' está por vencer o vencida.";
            }
        }
        
        // Obtener planilla completa si es necesario (ej: para un admin o vista general)
        // Por ahora pasamos las tareas personales para la vista principal
        // Y quizás la planilla completa si el usuario tiene rol Admin (opcional)
        $planilla = $this->gestionModel->getPlanillaControl();

        $data = [
            'pageTitle' => 'Seguimiento y Control Académico',
            'tareas' => $tareas,
            'alertas' => $alertas,
            'planilla' => $planilla,
            'extraJS' => 'Gestion' // Cargar public/js/Gestion.js
        ];

        $this->render('Gestion/index', $data);
    }

    public function subirEvidencia() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            // Validar CSRF
            if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido']);
                return;
            }

            $tareaId = (int)$_POST['tarea_id'];
            $userId = $_SESSION['user_id'];
            
            // Manejo del archivo
            if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../public/uploads/evidencias/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileName = time() . '_' . basename($_FILES['documento']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['documento']['tmp_name'], $targetPath)) {
                    // Guardar en BD (Ruta relativa pública)
                    $publicUrl = '/uploads/evidencias/' . $fileName;
                    
                    if ($this->gestionModel->registrarEvidencia($tareaId, $userId, $publicUrl)) {
                        echo json_encode(['status' => 'success', 'message' => 'Evidencia registrada correctamente']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Error al guardar en base de datos']);
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Error al mover el archivo subido']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No se ha subido ningún archivo válido']);
            }
        }
    }
    /**
     * Vista de administración de tareas (CRUD)
     */
    public function admin() {
        // Verificar permisos de admin (PENDIENTE: Usar middleware real)
        // $this->requirePermission('gestionar_academico'); 

        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;

        $result = $this->gestionModel->paginateTareas($page, 15);
        $tareas = $result['data'];
        $meta = $result['meta'];

        $pagination = [
            'current' => $meta['current_page'],
            'total_pages' => $meta['last_page'],
            'has_prev' => $meta['current_page'] > 1,
            'has_next' => $meta['current_page'] < $meta['last_page'],
            'prev_page' => $meta['current_page'] - 1,
            'next_page' => $meta['current_page'] + 1
        ];

        $facultades = $this->gestionModel->getFacultades();
        $cargos = $this->gestionModel->getCargosExistentes();
        $kpis = $this->gestionModel->getKPIs();

        // AJAX Partial
        if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
            include __DIR__ . '/../Views/Gestion/Partials/TablaTareas.php';
            exit;
        }

        $this->render('Gestion/AdminTareas', [
            'pageTitle' => 'Administración de Tareas Académicas',
            'tareas' => $tareas,
            'pagination' => $pagination,
            'kpis' => $kpis,
            'facultades' => $facultades,
            'cargos' => $cargos,
            'extraJS' => 'AdminTareas' 
        ]);
    }

    /**
     * Procesa la creación de una nueva tarea
     */
    public function guardarTarea() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
                $this->flash('error', 'Token de seguridad inválido.');
                $this->redirect('gestion/admin');
            }

            $datos = InputSanitizerHelper::sanitizeArray($_POST);
            
            // Validaciones básicas
            if (empty($datos['producto_documento']) || empty($datos['facultad_id']) || empty($datos['cargo_responsable'])) {
                $this->flash('error', 'Complete todos los campos obligatorios.');
                $this->redirect('gestion/admin');
            }

            if ($this->gestionModel->crearTarea($datos)) {
                $this->flash('success', 'Tarea creada exitosamente.');
            } else {
                $this->flash('error', 'Error al crear la tarea.');
            }
            $this->redirect('gestion/admin');
        }
    }

    /**
     * Elimina una tarea
     */
    public function eliminarTarea($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             $this->flash('error', 'Método no permitido.');
             $this->redirect('gestion/admin');
        }

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->flash('error', 'Token de seguridad inválido.');
            $this->redirect('gestion/admin');
        }

        if ($this->gestionModel->eliminarTarea((int)$id)) {
            $this->flash('success', 'Tarea eliminada correctamente.');
        } else {
            $this->flash('error', 'No se pudo eliminar la tarea.');
        }
        $this->redirect('gestion/admin');
    }
}
