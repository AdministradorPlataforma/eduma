<?php
declare(strict_types=1);

namespace App\Exceptions\Moodle;

class MoodleApiException extends MoodleException {
    protected $moodleErrorCode;

    public function __construct($message, $moodleErrorCode = null, $code = 0, \Throwable $previous = null) {
        $this->moodleErrorCode = $moodleErrorCode;
        parent::__construct($message, (int)$code, [], $previous);
    }

    public function getMoodleErrorCode() {
        return $this->moodleErrorCode;
    }
}
