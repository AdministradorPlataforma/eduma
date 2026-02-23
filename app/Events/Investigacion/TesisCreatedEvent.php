<?php
declare(strict_types=1);

namespace App\Events\Investigacion;

class TesisCreatedEvent {
    public int $tesisId;
    public array $data;
    public int $userId;

    public function __construct(int $tesisId, array $data, int $userId) {
        $this->tesisId = $tesisId;
        $this->data = $data;
        $this->userId = $userId;
    }
}
