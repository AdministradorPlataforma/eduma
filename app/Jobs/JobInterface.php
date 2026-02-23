<?php
declare(strict_types=1);

namespace App\Jobs;

interface JobInterface {
    public function handle(): void;
}
