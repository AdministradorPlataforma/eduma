<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\NotificationService;
use App\Services\LoggerService;

class NotificationController extends BaseController {

    private NotificationService $service;

    public function __construct(NotificationService $service) {
        parent::__construct();
        $this->service = $service;
    }

    public function leer(int $id) {
        $this->requireLogin();
        
        $success = $this->service->markAsRead($id, $this->getUserId());
        
        if ($this->isAjax()) {
            if ($success) $this->jsonSuccess(['message' => 'Leída']);
            else $this->jsonError('No se pudo marcar como leída');
        }

        $this->redirect('escritorio');
    }

    public function leerTodas() {
        $this->requireLogin();
        
        $this->service->markAllAsRead($this->getUserId());
        
        if ($this->isAjax()) {
            $this->jsonSuccess(['message' => 'Todas leídas']);
        }
        
        $this->redirect('escritorio');
    }
}
