<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Jobs\MoodleWebhookJob;
use App\Services\QueueService;
use App\Helpers\ApiResponse;
use App\Services\LoggerService;

/**
 * Controlador para recibir eventos en tiempo real desde Moodle (Webhooks).
 */
class MoodleWebhookController extends BaseController
{
    private QueueService $queueService;

    public function __construct(QueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    /**
     * Endpoint principal para recibir el webhook.
     */
    public function handle()
    {
        // Validar Token de seguridad
        $token = $_GET['token'] ?? $_SERVER['HTTP_X_MOODLE_TOKEN'] ?? null;
        $expectedToken = \Config\Env::get('MOODLE_WEBHOOK_TOKEN');

        if (!$token || $token !== $expectedToken) {
            LoggerService::warning("Intento de acceso no autorizado al Webhook de Moodle", ['ip' => $_SERVER['REMOTE_ADDR']]);
            return ApiResponse::error('Unauthorized', 401);
        }

        $payload = json_decode(file_get_contents('php://input'), true);

        if (!$payload || !isset($payload['eventname'])) {
            return ApiResponse::error('Payload inválido');
        }

        $event = $payload['eventname'];
        LoggerService::info("Webhook recibido de Moodle (Encolando): $event", ['objectid' => $payload['objectid'] ?? null]);

        // Encolar el proceso para que sea asíncrono y reentrante
        $job = new MoodleWebhookJob($payload);
        $this->queueService->dispatch($job);

        return ApiResponse::success([], 'Evento encolado correctamente');
    }
}
