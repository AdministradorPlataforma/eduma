<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\InputSanitizerHelper;
use App\Helpers\CSRFHelper;
use App\Helpers\ValidationHelper;
use App\Services\RolService;
use App\Services\PermisoService;

class RolController extends BaseController {
    
    // ... existing properties/methods ...
    private RolService $rolService;
    private PermisoService $permisoService;

    public function __construct(RolService $rolService, PermisoService $permisoService) {
        parent::__construct();
        $this->rolService = $rolService;
        $this->permisoService = $permisoService;
    }

    public function index() {
        $this->requirePermission('ver_rol');
        $roles = $this->rolService->obtenerTodos();
        $this->render('Rol/ListarRol', [
            'pageTitle' => 'Gestión de Roles',
            'extraCSS' => 'Rol',
            'extraJS' => 'Rol',
            'roles' => $roles
        ]);
    }

    public function create() {
        $this->requirePermission('crear_rol');
        $permisos = $this->permisoService->obtenerTodos();
        $this->render('Rol/CrearRol', [
            'pageTitle' => 'Crear Nuevo Rol',
            'extraCSS' => 'Rol',
            'extraJS' => 'Rol',
            'permisos' => $permisos
        ]);
    }

    public function store() {
        $this->requirePermission('crear_rol');

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->flash('error', 'Error CSRF.');
            $this->redirect('rol/crear');
        }

        $val = ValidationHelper::make($_POST)
            ->alias('nombre', 'Nombre del Rol')
            ->rule('nombre', 'required|min:3');

        if ($val->fails()) {
            $this->flash('error', $val->firstError());
            $this->redirect('rol/crear');
        }

        $clean = InputSanitizerHelper::sanitizeArray($_POST);
        $permisosSeleccionados = array_map('intval', $_POST['permisos'] ?? []);

        try {
            $this->rolService->crearRol($clean, $permisosSeleccionados);
            $this->flash('success', 'Rol creado correctamente.');
            $this->redirect('rol');
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('rol/crear');
        }
    }

    public function edit($id) {
        $this->requirePermission('editar_rol');
        
        $rol = $this->rolService->obtenerPorId((int)$id);

        if (!$rol) {
            $this->flash('error', 'Rol no encontrado.');
            $this->redirect('rol');
        }

        $permisos = $this->permisoService->obtenerTodos();
        $permisosAsignados = $this->rolService->obtenerPermisosDeRol((int)$id);

        $this->render('Rol/EditarRol', [
            'pageTitle' => 'Editar Rol',
            'extraCSS' => 'Rol',
            'extraJS' => 'Rol',
            'rol' => $rol,
            'permisos' => $permisos,
            'permisosAsignados' => $permisosAsignados
        ]);
    }

    public function update($id) {
        $this->requirePermission('editar_rol');

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->flash('error', 'Error CSRF.');
            $this->redirect("rol/editar/$id");
        }

        $val = ValidationHelper::make($_POST)
            ->alias('nombre', 'Nombre del Rol')
            ->rule('nombre', 'required|min:3');

        if ($val->fails()) {
            $this->flash('error', $val->firstError());
            $this->redirect("rol/editar/$id");
        }

        $clean = InputSanitizerHelper::sanitizeArray($_POST);
        $permisosSeleccionados = array_map('intval', $_POST['permisos'] ?? []);

        try {
            $this->rolService->actualizarRol((int)$id, $clean, $permisosSeleccionados);
            $this->flash('success', 'Rol actualizado correctamente.');
            $this->redirect('rol');
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect("rol/editar/$id");
        }
    }

    public function eliminar($id) {
        $this->requirePermission('eliminar_rol');

        try {
            if ($this->rolService->eliminarRol((int)$id)) {
                $this->flash('success', 'Rol eliminado.');
            } else {
                $this->flash('error', 'No se pudo eliminar el rol.');
            }
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('rol');
    }
}
