<?php
declare(strict_types=1);

namespace App\Listeners\Investigacion;

use App\Events\Investigacion\TesisCreatedEvent;
use App\Services\LoggerService;

class TesisAuditListener {
    private LoggerService $logger;

    public function __construct(LoggerService $logger) {
        $this->logger = $logger;
    }

    public function handle(TesisCreatedEvent $event): void {
        $this->logger->info("Nueva tesis registrada", [
            'tesis_id' => $event->tesisId,
            'user_id' => $event->userId,
            'titulo' => $event->data['titulo'] ?? ''
        ], "Tesis:{$event->tesisId}");
    }
}
