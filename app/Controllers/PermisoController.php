<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\InputSanitizerHelper;
use App\Helpers\CSRFHelper;
use App\Helpers\ValidationHelper;
use App\Services\PermisoService;

class PermisoController extends BaseController {
    
    private PermisoService $permisoService;

    public function __construct(PermisoService $permisoService) {
        parent::__construct();
        $this->permisoService = $permisoService;
    }

    public function index() {
        $this->requirePermission('ver_permiso');

        $permisos = $this->permisoService->obtenerTodos();

        $this->render('Permiso/ListarPermiso', [
            'pageTitle' => 'Gestión de Permisos',
            'extraCSS' => 'Permiso',
            'extraJS' => 'Permiso',
            'permisos' => $permisos
        ]);
    }

    public function create() {
        $this->requirePermission('crear_permiso');

        $this->render('Permiso/CrearPermiso', [
            'pageTitle' => 'Crear Nuevo Permiso',
            'extraCSS' => 'Permiso',
            'extraJS' => 'Permiso'
        ]);
    }

    public function store() {
        $this->requirePermission('crear_permiso');

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->flash('error', 'Error CSRF.');
            $this->redirect('permiso/crear');
        }

        $val = ValidationHelper::make($_POST)
            ->alias('slug', 'Identificador (Slug)')
            ->alias('descripcion', 'Descripción')
            ->rule('slug', 'required|min:3|regex:/^[a-z.]+$/')
            ->rule('descripcion', 'required|min:5');

        if ($val->fails()) {
            $this->flash('error', $val->firstError());
            $this->redirect('permiso/crear');
        }

        $clean = InputSanitizerHelper::sanitizeArray($_POST);

        try {
            $this->permisoService->crearPermiso($clean);
            $this->flash('success', 'Permiso registrado correctamente.');
            $this->redirect('permiso');
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('permiso/crear');
        }
    }

    public function edit($id) {
        $this->requirePermission('editar_permiso');
        
        $permiso = $this->permisoService->obtenerPorId((int)$id);

        if (!$permiso) {
            $this->flash('error', 'Permiso no encontrado.');
            $this->redirect('permiso');
        }

        $this->render('Permiso/EditarPermiso', [
            'pageTitle' => 'Editar Permiso',
            'extraCSS' => 'Permiso',
            'extraJS' => 'Permiso',
            'permiso' => $permiso
        ]);
    }

    public function update($id) {
        $this->requirePermission('editar_permiso');

        if (!CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
            $this->flash('error', 'Error CSRF.');
            $this->redirect("permiso/editar/$id");
        }

        $val = ValidationHelper::make($_POST)
            ->alias('slug', 'Identificador (Slug)')
            ->alias('descripcion', 'Descripción')
            ->rule('slug', 'required|min:3|regex:/^[a-z.]+$/')
            ->rule('descripcion', 'required|min:5');

        if ($val->fails()) {
            $this->flash('error', $val->firstError());
            $this->redirect("permiso/editar/$id");
        }

        $clean = InputSanitizerHelper::sanitizeArray($_POST);

        try {
            $this->permisoService->actualizarPermiso((int)$id, $clean);
            $this->flash('success', 'Permiso actualizado correctamente.');
            $this->redirect('permiso');
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect("permiso/editar/$id");
        }
    }

    public function eliminar($id) {
        $this->requirePermission('eliminar_permiso');

        try {
            if ($this->permisoService->eliminarPermiso((int)$id)) {
                $this->flash('success', 'Permiso eliminado.');
            } else {
                $this->flash('error', 'No se pudo eliminar el permiso.');
            }
        } catch (\Exception $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('permiso');
    }
}
