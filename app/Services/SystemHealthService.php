<?php
declare(strict_types=1);

namespace App\Services;

use Modules\Moodle\MoodleClient;
use App\Core\Container;

class SystemHealthService {

    private MoodleClient $moodleClient;

    public function __construct(MoodleClient $moodleClient) {
        $this->moodleClient = $moodleClient;
    }

    public function getSystemHealth(): array {
        return [
            'cpu' => $this->getCpuUsage(),
            'ram' => $this->getRamUsage(),
            'disk' => $this->getDiskUsage(),
            'moodle' => $this->getMoodleStatus(),
            'database' => $this->getDatabaseStatus()
        ];
    }

    private function getCpuUsage(): int {
        // Windows implementation via WMIC
        // This command gets the load percentage of the CPU
        $cmd = "wmic cpu get loadpercentage";
        @exec($cmd, $output);
        
        if ($output) {
            foreach ($output as $line) {
                // Look for a line with just digits
                if ($line && preg_match('/^[0-9]+$/', trim($line))) {
                    return (int) trim($line);
                }
            }
        }
        return 0;
    }

    private function getRamUsage(): array {
        // Windows implementation via WMIC
        $free = 0;
        $total = 0;
        
        // Get FreePhysicalMemory (KB) and TotalVisibleMemorySize (KB)
        @exec("wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value", $output);
        
        foreach ($output as $line) {
            if (preg_match('/^FreePhysicalMemory=(\d+)/i', trim($line), $matches)) {
                $free = (int) $matches[1]; // KB
            }
            if (preg_match('/^TotalVisibleMemorySize=(\d+)/i', trim($line), $matches)) {
                $total = (int) $matches[1]; // KB
            }
        }

        if ($total > 0) {
            $used = $total - $free;
            $percent = round(($used / $total) * 100);
            return [
                'total_gb' => round($total / 1024 / 1024, 2),
                'used_gb' => round($used / 1024 / 1024, 2),
                'free_gb' => round($free / 1024 / 1024, 2),
                'percent' => $percent
            ];
        }

        return ['total_gb' => 0, 'used_gb' => 0, 'free_gb' => 0, 'percent' => 0];
    }

    private function getDiskUsage(): array {
        $path = '.'; // Current drive
        $total = @disk_total_space($path) ?: 0;
        $free = @disk_free_space($path) ?: 0;
        $used = $total - $free;
        $percent = $total > 0 ? round(($used / $total) * 100) : 0;

        return [
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_gb' => round($used / 1024 / 1024 / 1024, 2),
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'percent' => $percent
        ];
    }

    private function getMoodleStatus(): array {
        try {
            // Using the existing healthCheck method in MoodleClient
            // It returns array with success boolean and other details
            $status = $this->moodleClient->healthCheck();
            return [
                'online' => $status['success'] ?? false,
                'latency' => $status['response_time_ms'] ?? 0,
                'version' => $status['version'] ?? 'N/A'
            ];
        } catch (\Exception $e) {
            return [
                'online' => false,
                'latency' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getDatabaseStatus(): array {
        $start = microtime(true);
        try {
             $db = Container::getInstance()->get('db');
             $db->query("SELECT 1");
             $latency = round((microtime(true) - $start) * 1000, 2);
             return [
                 'online' => true,
                 'latency' => $latency
             ];
        } catch (\Exception $e) {
            return [
                'online' => false,
                'latency' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}
