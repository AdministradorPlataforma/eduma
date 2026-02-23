<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Investigacion\TesisModel;
use App\Models\Gestion\GestionModel;
use App\Helpers\SessionHelper;

class RecycleBinController extends BaseController {

    private TesisModel $tesisModel;
    private GestionModel $gestionModel;

    public function __construct(TesisModel $tesisModel, GestionModel $gestionModel) {
        parent::__construct();
        $this->tesisModel = $tesisModel;
        $this->gestionModel = $gestionModel;
    }

    public function index() {
        $this->requirePermission('papelera.gestionar');

        $deletedTesis = $this->tesisModel->onlyDeleted()->findAll();
        $deletedGestion = $this->gestionModel->onlyDeleted()->findAll();

        $this->render('Sistema/Papelera', [
            'pageTitle' => 'Papelera de Reciclaje',
            'tesis' => $deletedTesis,
            'gestion' => $deletedGestion
        ]);
    }

    public function restore() {
        $this->requirePermission('papelera.gestionar');
        $type = $_GET['type'] ?? '';
        $id = (int)($_GET['id'] ?? 0);

        if (!$type || !$id) {
            $this->flash('error', 'Parámetros inválidos');
            $this->redirect('recycle-bin');
        }

        $success = false;
        if ($type === 'tesis') {
            $success = $this->tesisModel->restore($id);
        } elseif ($type === 'gestion') {
            $success = $this->gestionModel->restore($id);
        }

        if ($success) {
            $this->flash('success', 'Elemento restaurado exitosamente');
        } else {
            $this->flash('error', 'No se pudo restaurar el elemento');
        }

        $this->redirect('recycle-bin');
    }

    public function purge() {
        $this->requirePermission('papelera.gestionar');
        $type = $_GET['type'] ?? '';
        $id = (int)($_GET['id'] ?? 0);

        if (!$type || !$id) {
            $this->flash('error', 'Parámetros inválidos');
            $this->redirect('recycle-bin');
        }

        $success = false;
        if ($type === 'tesis') {
            $success = $this->tesisModel->delete($id, true);
        } elseif ($type === 'gestion') {
            $success = $this->gestionModel->delete($id, true);
        }

        if ($success) {
            $this->flash('success', 'Elemento eliminado permanentemente');
        } else {
            $this->flash('error', 'No se pudo eliminar el elemento');
        }

        $this->redirect('recycle-bin');
    }
}
