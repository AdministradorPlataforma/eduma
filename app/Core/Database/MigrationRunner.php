<?php
declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Container;
use PDO;

class MigrationRunner {
    private PDO $db;
    private string $migrationsPath;

    public function __construct() {
        $this->db = Container::getInstance()->get('db');
        // Ajusta la ruta a tu estructura real: public/eduma/bd/migrations
        $this->migrationsPath = realpath(__DIR__ . '/../../../bd/migrations');
    }

    /**
     * Inicializa la tabla de control de migraciones si no existe
     */
    public function install(): void {
        // 1. Crear tabla base si no existe
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $this->db->exec($sql);

        // 2. Hotfix: Asegurar que columna 'batch' exista (para migrar de versiones previas)
        try {
            // Intentar seleccionar la columna
            $this->db->query("SELECT batch FROM migrations LIMIT 1");
        } catch (\PDOException $e) {
            // Si falla, es probable que no exista. La agregamos.
            // Código error 1054: Unknown column
            if (strpos($e->getMessage(), '1054') !== false || $e->getCode() == '42S22') {
                $this->db->exec("ALTER TABLE migrations ADD COLUMN batch INT UNSIGNED NOT NULL DEFAULT 1 AFTER migration");
            }
        }
    }

    /**
     * Ejecuta las migraciones pendientes
     * @return array Lista de migraciones ejecutadas
     */
    public function run(): array {
        $this->install();

        // 1. Obtener migraciones ya ejecutadas
        $executed = $this->db->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

        // 2. Escanear directorio
        $files = glob($this->migrationsPath . '/*.sql');
        $pending = [];

        foreach ($files as $file) {
            $filename = basename($file);
            if (!in_array($filename, $executed)) {
                $pending[] = $file;
            }
        }

        if (empty($pending)) {
            return [];
        }

        // 3. Determinar número de lote (batch)
        $stmt = $this->db->query("SELECT MAX(batch) FROM migrations");
        $currentBatch = (int)$stmt->fetchColumn();
        $nextBatch = $currentBatch + 1;

        $processed = [];

        // 4. Ejecutar cada pendiente
        foreach ($pending as $file) {
            $filename = basename($file);
            $sql = file_get_contents($file);

            try {
                // Iniciar transacción por archivo si el motor lo permite (MySQL DDL no es transaccional, pero DML sí)
                // Para ser seguros, ejecutamos tal cual.
                
                // Soporte para múltiples sentencias separadas por ;
                // PDO a veces falla con múltiples queries en un exec, dependiendo de la config.
                // Lo más seguro es usar exec() directo.
                $this->db->exec($sql);

                // Registrar ejecución
                $stmt = $this->db->prepare("INSERT INTO migrations (migration, batch) VALUES (:name, :batch)");
                $stmt->execute([':name' => $filename, ':batch' => $nextBatch]);

                $processed[] = $filename;

            } catch (\PDOException $e) {
                // Lanzar excepción para detener el proceso y reportar el error exacto
                throw new \Exception("Error migrando archivo '{$filename}': " . $e->getMessage());
            }
        }

        return $processed;
    }
    
    /**
     * Obtiene el estado actual
     */
    public function getStatus(): array {
        $this->install();
        
        $executed = $this->db->query("SELECT migration, executed_at FROM migrations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $executedMap = [];
        foreach($executed as $row) {
            $executedMap[$row['migration']] = $row['executed_at'];
        }

        $files = glob($this->migrationsPath . '/*.sql');
        $status = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $status[] = [
                'migration' => $filename,
                'executed' => isset($executedMap[$filename]),
                'executed_at' => $executedMap[$filename] ?? null
            ];
        }
        
        return $status;
    }
}
