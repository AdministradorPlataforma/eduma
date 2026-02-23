<?php
declare(strict_types=1);

namespace App\Listeners\Investigacion;

use App\Events\Investigacion\TesisCreatedEvent;
use App\Services\NotificationService;

class TesisNotificationListener {
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService) {
        $this->notificationService = $notificationService;
    }

    public function handle(TesisCreatedEvent $event): void {
        $titulo = $event->data['titulo'] ?? 'Tesis Nueva';
        $this->notificationService->send(
            $event->userId,
            'Nueva Tesis Registrada',
            "Se ha registrado con éxito la tesis: $titulo",
            'success'
        );
    }
}
