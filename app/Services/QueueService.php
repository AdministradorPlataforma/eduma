<?php
declare(strict_types=1);

namespace App\Services;

use App\Jobs\JobInterface;
use App\Models\QueueJobModel;

class QueueService extends BaseService {
    
    private QueueJobModel $jobModel;
    private const MAX_ATTEMPTS = 3;

    public function __construct(QueueJobModel $jobModel) {
        $this->jobModel = $jobModel;
    }

    public function dispatch(JobInterface $job): int {
        $serializedJob = serialize($job);
        $id = $this->jobModel->createJob($serializedJob);
        LoggerService::info("Job encolado", ['job_id' => $id, 'class' => get_class($job)]);
        return $id;
    }

    public function work(): bool {
        $jobData = $this->jobModel->getNextPending(self::MAX_ATTEMPTS);

        if (!$jobData) {
            return false;
        }

        $jobId = (int)$jobData['id'];

        try {
            $job = unserialize($jobData['handler']);

            if (!($job instanceof JobInterface)) {
                throw new \Exception("Job handler is not an instance of JobInterface");
            }

            LoggerService::debug("Procesando Job", ['job_id' => $jobId, 'class' => get_class($job)]);
            
            $job->handle();
            
            $this->jobModel->markAsCompleted($jobId);
            LoggerService::info("Job completado con éxito", ['job_id' => $jobId]);

        } catch (\Throwable $e) {
            $attempts = (int)($jobData['attempts'] ?? 0) + 1;
            $status = ($attempts >= self::MAX_ATTEMPTS) ? 'failed (max attempts)' : 'failed (will retry)';
            
            $this->jobModel->markAsFailed($jobId, $e->getMessage());
            
            LoggerService::error("Error procesando Job", [
                'job_id' => $jobId,
                'status' => $status,
                'attempt' => $attempts,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        return true;
    }
}
