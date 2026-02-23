<?php
declare(strict_types=1);

namespace App\Services\Sync;

use App\Services\BaseService;
use Modules\Moodle\MoodleParallelClient;
use App\Services\BulkDatabaseService;
use App\Services\SyncStateDbService;
use App\Services\LoggerService;
use Config\MoodleWS;
use PDO;

class EnrollmentSyncService extends BaseService
{
    private MoodleParallelClient $parallelClient;
    private BulkDatabaseService $bulkDb;
    private SyncStateDbService $stateService;
    private PDO $db;

    public function __construct(
        MoodleParallelClient $parallelClient, 
        BulkDatabaseService $bulkDb, 
        SyncStateDbService $stateService,
        PDO $db
    ) {
        $this->parallelClient = $parallelClient;
        $this->bulkDb = $bulkDb;
        $this->stateService = $stateService;
        $this->db = $db;
    }

    /**
     * Sincroniza matrículas (enrollments) curso por curso.
     */
    public function sync(int $startOffset = 0, array $completedPhases = []): array
    {
        $startTime = microtime(true);
        $globalStats = [
            'processed' => 0,
            'updated' => 0,
            'errors' => 0,
            'aborted' => false
        ];
        
        try {
            $stmt = $this->db->query("SELECT id_moodle FROM cursos WHERE visible = 1 ORDER BY id_moodle ASC");
            $courseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($courseIds)) return $globalStats;
            
            $parallelRequests = 1; 
            $this->parallelClient->setMaxParallel($parallelRequests);
            $this->parallelClient->setTimeout(180);

            $batchSize = $parallelRequests * 5;
            $totalCoursesReal = count($courseIds);

            if ($startOffset > 0) {
                $courseIds = array_slice($courseIds, $startOffset);
            }
            
            $chunks = array_chunk($courseIds, $batchSize);
            $processedCourses = $startOffset;
            
            foreach ($chunks as $courseChunk) {
                if ($this->stateService->shouldStop()) {
                    $globalStats['aborted'] = true;
                    break;
                }
                
                $requests = [];
                foreach ($courseChunk as $courseId) {
                    $requests[] = [
                        'key' => (string)$courseId,
                        'function' => MoodleWS::FUNCTIONS['GET_ENROLLED_USERS'],
                        'params' => ['courseid' => $courseId]
                    ];
                }
                
                $parallelResponse = $this->parallelClient->executeParallel($requests);
                if (!empty($parallelResponse['aborted'])) {
                    $globalStats['aborted'] = true;
                    break;
                }
                
                $enrolledByChunk = $parallelResponse['results'] ?? [];
                foreach ($enrolledByChunk as $courseId => $enrolledUsers) {
                    if (is_array($enrolledUsers)) {
                        $activeMoodleIds = array_column($enrolledUsers, 'id');
                        $suspended = $this->bulkDb->bulkSuspendOrphanEnrollments((int)$courseId, $activeMoodleIds);

                        $courseEnrollments = [];
                        foreach ($enrolledUsers as $user) {
                            $role = 'student';
                            if (isset($user['roles'])) {
                                foreach ($user['roles'] as $r) {
                                    if (in_array($r['shortname'] ?? '', ['editingteacher', 'teacher', 'manager'])) {
                                        $role = 'teacher';
                                        break;
                                    }
                                }
                            }
                            $courseEnrollments[] = ['course_id' => (int)$courseId, 'user_id' => $user['id'], 'role' => $role];
                        }

                        if (!empty($courseEnrollments)) {
                            $bulkResult = $this->bulkDb->bulkUpsertEnrollments($courseEnrollments);
                            $globalStats['processed'] += $bulkResult['processed'];
                            $globalStats['updated'] += ($bulkResult['updated'] ?? 0) + ($suspended ?? 0);
                            $globalStats['errors'] += $bulkResult['errors'];
                        }
                    }
                }
                
                $processedCourses += count($courseChunk);
                $percent = 70 + (int)(($processedCourses / $totalCoursesReal) * 10);
                $this->stateService->updateProgress($percent, "Matrículas: $processedCourses/$totalCoursesReal cursos...");

                $this->stateService->saveCheckpoint('enrollments', [
                    'completed_phases' => $completedPhases,
                    'enrollments_offset' => $processedCourses
                ]);
            }
            
            if ($globalStats['processed'] > 0) {
                $this->actualizarFlagsRolesUsuarios();
            }
            
            $globalStats['time_seconds'] = round(microtime(true) - $startTime, 2);
            LoggerService::info("Sincronización de matrículas finalizada", $globalStats);
            
            return $globalStats;
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando matrículas", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function actualizarFlagsRolesUsuarios(): void
    {
        $sql = "UPDATE usuarios u
                LEFT JOIN (
                    SELECT usuario_id, 
                           MAX(CASE WHEN rol = 'student' THEN 1 ELSE 0 END) as is_student,
                           MAX(CASE WHEN rol = 'teacher' THEN 1 ELSE 0 END) as is_teacher
                    FROM curso_matriculas
                    GROUP BY usuario_id
                ) m ON u.id = m.usuario_id
                SET u.es_estudiante = COALESCE(m.is_student, 0),
                    u.es_docente = COALESCE(m.is_teacher, 0)";
        $this->db->exec($sql);
    }
}
