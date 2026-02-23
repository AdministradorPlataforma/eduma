<?php
declare(strict_types=1);

namespace App\Services\Sync;

use App\Services\BaseService;
use App\Services\SyncStateDbService;
use App\Services\UserProfileService;
use App\Services\SyncCleanupService;
use App\Services\LoggerService;

/**
 * Fachada de Sincronización con Moodle (Orquestador)
 */
class MoodleSyncFacade extends BaseService
{
    private UserSyncService $userSync;
    private GradeSyncService $gradeSync;
    private EnrollmentSyncService $enrollmentSync;
    private SyncStateDbService $stateService;
    private UserProfileService $profileService;
    private SyncCleanupService $cleanupService;

    public function __construct(
        UserSyncService $userSync,
        GradeSyncService $gradeSync,
        EnrollmentSyncService $enrollmentSync,
        SyncStateDbService $stateService,
        UserProfileService $profileService,
        SyncCleanupService $cleanupService
    ) {
        $this->userSync = $userSync;
        $this->gradeSync = $gradeSync;
        $this->enrollmentSync = $enrollmentSync;
        $this->stateService = $stateService;
        $this->profileService = $profileService;
        $this->cleanupService = $cleanupService;
    }

    /**
     * Ejecuta la sincronización completa orquestando los sub-servicios.
     */
    public function sincronizarTodo(bool $force = false, array $options = []): array
    {
        $shouldResume = $options['resume'] ?? false;
        $checkpoint = $this->stateService->getCheckpoint();

        if ($shouldResume && empty($checkpoint)) {
            $shouldResume = false;
        }

        if (!$shouldResume) {
            $this->stateService->clearCheckpoint();
            $this->stateService->startSync('all');
            $completedPhases = [];
            $stats = ['processed' => 0, 'updated' => 0, 'errors' => 0, 'phases' => []];
        } else {
            $this->stateService->startSync('all', true);
            $stats = $checkpoint['stats'] ?? ['processed' => 0, 'updated' => 0, 'errors' => 0, 'phases' => []];
            $completedPhases = $checkpoint['completed_phases'] ?? [];
        }

        try {
            // Fase: Usuarios
            if (!in_array('users', $completedPhases)) {
                $userRes = $this->userSync->sync($force, $options);
                $this->accumulateStats($stats, $userRes, 'users');
                $completedPhases[] = 'users';
                $this->saveCheckpoint($completedPhases, $stats);
            }

            if ($this->stateService->shouldStop()) return $stats;

            // Fase: Matrículas
            if (!in_array('enrollments', $completedPhases)) {
                $startOffset = ($shouldResume && isset($checkpoint['enrollments_offset'])) ? (int)$checkpoint['enrollments_offset'] : 0;
                $enrollRes = $this->enrollmentSync->sync($startOffset, $completedPhases);
                $this->accumulateStats($stats, $enrollRes, 'enrollments');
                
                if (!$enrollRes['aborted']) {
                    $completedPhases[] = 'enrollments';
                    $this->saveCheckpoint($completedPhases, $stats);
                }
            }

            if ($this->stateService->shouldStop()) return $stats;

            // Fase: Calificaciones
            if (!in_array('grades', $completedPhases)) {
                $gradeRes = $this->gradeSync->sync();
                $this->accumulateStats($stats, $gradeRes, 'grades');
                $completedPhases[] = 'grades';
                $this->saveCheckpoint($completedPhases, $stats);
            }

            // Finalización y limpieza
            $this->stateService->completeSync();
            $this->stateService->clearCheckpoint();
            $stats['status'] = 'completed';

            return $stats;

        } catch (\Exception $e) {
            $this->stateService->errorSync($e->getMessage());
            LoggerService::error("Falla en orquestación de sincronización", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function accumulateStats(array &$global, array $local, string $phase): void
    {
        $global['phases'][$phase] = $local;
        $global['processed'] += $local['processed'] ?? ($local['courses_processed'] ?? 0);
        $global['updated'] += $local['updated'] ?? 0;
        $global['errors'] += $local['errors'] ?? 0;
    }

    private function saveCheckpoint(array $completed, array $stats): void
    {
        $this->stateService->saveCheckpoint(end($completed), [
            'completed_phases' => $completed,
            'stats' => $stats
        ]);
        $this->stateService->updateStats((int)$stats['processed'], (int)$stats['updated'], (int)$stats['errors']);
    }
}
