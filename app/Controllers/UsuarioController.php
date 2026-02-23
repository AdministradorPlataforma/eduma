<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\InputSanitizerHelper;
use App\Helpers\CSRFHelper;
use App\Helpers\ValidationHelper;
use App\Models\Usuario\UsuarioModel;

class UsuarioController extends BaseController {
    
    private $usuarioModel;
    private $usuarioService;
    private $rolService;

    public function __construct(
        UsuarioModel $usuarioModel,
        \App\Services\UsuarioService $usuarioService,
        \App\Services\RolService $rolService
    ) {
        parent::__construct();
        $this->usuarioModel = $usuarioModel;
        $this->usuarioService = $usuarioService;
        $this->rolService = $rolService;
    }

    /**
     * Lista usuarios - Versión optimizada con DataTables Server-Side
     * Solo carga el conteo total, los datos se cargan via AJAX
     */
    public function index() {
        $this->requirePermission('ver_usuario');

        $totalUsuarios = $this->usuarioModel->countAll();

        $this->render('Usuario/ListarUsuario', [
            'pageTitle' => 'Listado de Usuarios',
            'extraCSS' => 'Usuario',
            'extraJS' => 'Usuario',
            'totalUsuarios' => $totalUsuarios
        ]);
    }

    /**
     * Endpoint AJAX para DataTables Server-Side Processing
     * GET /usuario/datatable
     */
    public function datatable() {
        // 1. Limpieza agresiva de buffers de salida para evitar contaminación del JSON
        while (ob_get_level()) ob_end_clean();
        
        // 2. Configurar headers para respuesta JSON pura
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate'); 
        header('Pragma: no-cache'); 
        header('Expires: 0'); 

        try {
            // Verificar permisos (Si falla, BaseController manejará la respuesta JSON gracias al header Ajax)
            // Nota: DataTables debe enviar 'X-Requested-With: XMLHttpRequest'
            $this->requirePermission('ver_usuario');

            // Parámetros de DataTables
            $draw = (int) ($_GET['draw'] ?? 1);
            $start = (int) ($_GET['start'] ?? 0);
            $length = min((int) ($_GET['length'] ?? 25), 100); // Límite de seguridad
            $search = trim($_GET['search']['value'] ?? '');
            
            // Limpiar búsqueda de caracteres problemáticos (UTF-8 safe)
            $search = preg_replace('/[^\p{L}\p{N}\s@._-]/u', '', $search);
            
            // Ordenamiento
            $orderColumnIndex = (int) ($_GET['order'][0]['column'] ?? 0);
            $orderDir = strtolower($_GET['order'][0]['dir'] ?? 'desc');
            $columnMap = [0 => 'id', 1 => 'email', 2 => 'id_moodle', 3 => 'rol', 4 => 'activo', 5 => 'id'];
            $orderColumn = $columnMap[$orderColumnIndex] ?? 'id';

            $usuarioModel = $this->usuarioModel;

            // Obtener datos
            $usuarios = $usuarioModel->getPaginated($start, $length, $search, $orderColumn, $orderDir);
            $totalRecords = $usuarioModel->countAll();
            $filteredRecords = !empty($search) ? $usuarioModel->countFiltered($search) : $totalRecords;

            // Formatear datos
            $data = [];
            foreach ($usuarios as $u) {
                $nombre = $u['nombre'] ?? '';
                $apellido = $u['apellido'] ?? '';
                $rol = strtolower($u['rol'] ?? 'user');
                $isAdmin = strpos($rol, 'admin') !== false;
                $idMoodle = $u['id_moodle'] ?? null;
                
                $data[] = [
                    'id' => (int)$u['id'],
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'email' => $u['email'] ?? '',
                    'rol' => $u['rol'] ?? 'Usuario',
                    'isAdmin' => $isAdmin,
                    'activo' => (int)($u['activo'] ?? 1),
                    'initials' => strtoupper(mb_substr($nombre, 0, 1) . mb_substr($apellido, 0, 1)),
                    'id_moodle' => $idMoodle,
                    'origen' => $idMoodle ? 'moodle' : 'local'
                ];
            }

            // Respuesta JSON segura
            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => (int)$totalRecords,
                'recordsFiltered' => (int)$filteredRecords,
                'data' => $data
            ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            
            exit; // Detener ejecución inmediatamente

        } catch (\Exception $e) {
            // Manejo de errores
            echo json_encode([
                'draw' => (int) ($_GET['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }



    /**
     * Muestra formulario de creación de usuario
     */
    public function create() {
        $this->requirePermission('crear_usuario');

        $roles = $this->rolService->obtenerTodos();

        $this->render('Usuario/CrearUsuario', [
            'pageTitle' => 'Crear Nuevo Usuario',
            'extraCSS' => 'Usuario',
            'extraJS' => 'Usuario',
            'roles' => $roles
        ]);
    }


    /**
     * Procesa el guardado de un nuevo usuario.
     */
    public function store() {
        $this->requirePermission('crear_usuario');

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->flash('error', 'Error de seguridad (CSRF).');
            $this->redirect('usuario/crear');
        }

        // Validación con Helper
        $val = ValidationHelper::make($_POST)
            ->alias('username', 'Nombre de Usuario')
            ->alias('password', 'Contraseña')
            ->alias('password_confirm', 'Confirmación')
            ->rule('nombre', 'required|min:2')
            ->rule('apellido', 'required|min:2')
            ->rule('username', 'required|min:4|alphanum')
            ->rule('email', 'required|email')
            ->rule('password', 'required|min:6|match:password_confirm');

        if ($val->fails()) {
            $this->flash('error', $val->firstError());
            $this->redirect('usuario/crear');
        }

        $clean = InputSanitizerHelper::sanitizeArray($_POST);
        
        $clean['es_admin'] = isset($_POST['es_admin']) ? 1 : 0;
        $clean['es_docente'] = isset($_POST['es_docente']) ? 1 : 0;
        $clean['es_estudiante'] = isset($_POST['es_estudiante']) ? 1 : 0;

        // Rol
        $rolId = !empty($_POST['rol_id']) ? (int)$_POST['rol_id'] : null;
        
        try {
            $this->usuarioService->crearUsuario($clean, $rolId);
            
            $this->flash('success', 'Usuario creado correctamente.');
            $this->redirect('usuario');
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('usuario/crear');
        }
    }

    // ... existing edit() ...
    public function edit($id) {
        $this->requirePermission('editar_usuario');

        $usuarioModel = $this->usuarioModel;
        $usuario = $usuarioModel->find((int)$id);

        if (!$usuario) {
            $this->flash('error', 'Usuario no encontrado.');
            $this->redirect('usuario');
        }

        $roles = $this->rolService->obtenerTodos();
        $currentRolId = $this->usuarioService->getRolId((int)$id);

        $this->render('Usuario/EditarUsuario', [
            'pageTitle' => 'Editar Usuario',
            'extraCSS' => 'Usuario',
            'extraJS' => 'Usuario',
            'usuario' => $usuario,
            'roles' => $roles,
            'currentRolId' => $currentRolId
        ]);
    }

    /**
     * Procesa la actualización.
     */
    public function update($id) {
        $this->requirePermission('editar_usuario');

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->flash('error', 'Error de seguridad (CSRF).');
            $this->redirect("usuario/editar/$id");
        }

        // Validación con Helper
        $val = ValidationHelper::make($_POST)
            ->alias('username', 'Nombre de Usuario')
            ->rule('nombre', 'required|min:2')
            ->rule('apellido', 'required|min:2')
            ->rule('username', 'required|min:4|alphanum')
            ->rule('email', 'required|email');

        // Si se envió password, validarla
        if (!empty($_POST['password'])) {
            $val->alias('password', 'Contraseña')
                ->alias('password_confirm', 'Confirmación')
                ->rule('password', 'min:6|match:password_confirm');
        }

        if ($val->fails()) {
            $this->flash('error', $val->firstError());
            $this->redirect("usuario/editar/$id");
        }

        $clean = InputSanitizerHelper::sanitizeArray($_POST);

        $clean['es_admin'] = isset($_POST['es_admin']) ? 1 : 0;
        $clean['es_docente'] = isset($_POST['es_docente']) ? 1 : 0;
        $clean['es_estudiante'] = isset($_POST['es_estudiante']) ? 1 : 0;
        $clean['activo'] = isset($_POST['activo']) ? 1 : 0;

        $rolId = !empty($_POST['rol_id']) ? (int)$_POST['rol_id'] : null;

        try {
            $this->usuarioService->actualizarUsuario((int)$id, $clean, $rolId);
            
            $this->flash('success', 'Usuario actualizado correctamente.');
            $this->redirect('usuario');
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect("usuario/editar/$id");
        }
    }

    /**
     * Elimina un usuario.
     */
    public function eliminar($id) {
        $this->requirePermission('eliminar_usuario');

        try {
            if ($this->usuarioService->eliminarUsuario((int)$id)) {
                $this->flash('success', 'Usuario eliminado satisfactoriamente.');
            } else {
                $this->flash('error', 'No se pudo eliminar el usuario.');
            }
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('usuario');
    }
}
