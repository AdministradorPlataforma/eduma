<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Services\Sync\UserSyncService;
use App\Services\Sync\EnrollmentSyncService;
use App\Services\LoggerService;
use App\Core\Container;

/**
 * Job para procesar eventos de Webhooks de Moodle de forma asíncrona.
 */
class MoodleWebhookJob implements JobInterface
{
    private array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $event = $this->payload['eventname'] ?? null;
        if (!$event) {
            return;
        }

        $container = Container::getInstance();
        
        switch ($event) {
            case '\core\event\user_created':
            case '\core\event\user_updated':
                $moodleUserId = $this->payload['objectid'] ?? null;
                if ($moodleUserId) {
                    $userSync = $container->get(UserSyncService::class);
                    $userSync->sync(true, ['only_moodle_id' => $moodleUserId]);
                }
                break;

            case '\core\event\user_enrolment_created':
            case '\core\event\user_enrolment_deleted':
                $courseId = $this->payload['other']['courseid'] ?? null;
                if ($courseId) {
                    $enrollmentSync = $container->get(EnrollmentSyncService::class);
                    $enrollmentSync->sync(0, [], [(int)$courseId]);
                }
                break;
                
            default:
                LoggerService::info("Evento de webhook no manejado en Job", ['event' => $event]);
                break;
        }
    }

    public function __serialize(): array
    {
        return ['payload' => $this->payload];
    }

    public function __unserialize(array $data): void
    {
        $this->payload = $data['payload'];
    }
}
