<?php
declare(strict_types=1);

namespace App\Exceptions\Moodle;

class MoodleConnectionException extends MoodleException {
    protected $isTimeout;

    public function __construct($message, $isTimeout = false, $code = 0, \Throwable $previous = null) {
        $this->isTimeout = $isTimeout;
        parent::__construct($message, $code, $previous);
    }

    public function isTimeout() {
        return $this->isTimeout;
    }
}
