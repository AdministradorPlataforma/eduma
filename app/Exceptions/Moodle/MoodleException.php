<?php
declare(strict_types=1);

namespace App\Exceptions\Moodle;

use Exception; // Explicitly import global Exception

class MoodleException extends Exception {
    
    protected $moodleContext;

    public function __construct(string $message, int $code = 0, array $context = [], ?\Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->moodleContext = $context;
    }

    public function getMoodleContext(): array {
        return $this->moodleContext;
    }
}
