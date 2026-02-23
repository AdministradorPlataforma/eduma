CREATE TABLE IF NOT EXISTS task_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(191) NOT NULL,
    status ENUM('success', 'failure', 'running') NOT NULL DEFAULT 'running',
    output TEXT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL,
    duration_ms INT UNSIGNED DEFAULT 0,
    INDEX idx_task_name (task_name),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
