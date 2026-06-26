<?php
declare(strict_types=1);

namespace App\Services\Moodle;

use App\Services\LoggerService;
use PDO;

/**
 * Servicio especializado en sincronización de Categorías y Mapeo Académico.
 */
class CategorySyncService extends MoodleBaseSyncService {

    /**
     * FASE 1: Sincronizar Categorías
     */
    public function sincronizar(): array {
        $this->stats['start_time'] = microtime(true);
        
        try {
            $categories = $this->client->getCategories();
            $result = $this->bulkDb->bulkUpsertCategories($categories);
            
            $this->stats = array_merge($this->stats, $result);
            $this->stats['total_from_moodle'] = count($categories);
            $this->stats['time_seconds'] = round(microtime(true) - $this->stats['start_time'], 2);
            
            LoggerService::info("Categorías sincronizadas", $this->stats);
            return $this->stats;
            
        } catch (\Exception $e) {
            LoggerService::error("Error sincronizando categorías", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Mapea automáticamente Facultades, Carreras y Cursos basándose en la estructura de Moodle.
     */
    public function mapearEstructuraAcademicaAuto(): array {
        $mappingStats = ['facultades' => 0, 'carreras' => 0, 'cursos_mapeados' => 0];

        try {
            // 1. Consolidar Facultades (Depth 3)
            try {
                $sqlFac = "INSERT INTO facultades (id_moodle_categoria, nombre)
                           SELECT MIN(id), name FROM raw_moodle_categorias 
                           WHERE depth = 3 GROUP BY name
                           ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)";
                $this->db->exec($sqlFac);
                $mappingStats['facultades'] = $this->db->query("SELECT COUNT(*) FROM facultades")->fetchColumn();
            } catch (\Exception $e) {
                LoggerService::error("Error mapeando facultades", ['error' => $e->getMessage()]);
            }

            // 2. Consolidar Carreras (Depth 4) y vincular a Facultades locales
            try {
                $sqlCar = "INSERT INTO carreras (id_moodle_categoria, nombre, facultad_id)
                           SELECT MIN(rc.id), rc.name, f.id
                           FROM raw_moodle_categorias rc
                           JOIN raw_moodle_categorias rcf ON rc.parent_id = rcf.id
                           JOIN facultades f ON rcf.name = f.nombre
                           WHERE rc.depth = 4
                           GROUP BY rc.name, f.id
                           ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), facultad_id = VALUES(facultad_id)";
                $this->db->exec($sqlCar);
                $mappingStats['carreras'] = $this->db->query("SELECT COUNT(*) FROM carreras")->fetchColumn();
            } catch (\Exception $e) {
                LoggerService::error("Error mapeando carreras", ['error' => $e->getMessage()]);
            }

            // 3. Mapeo Automático de Cursos mediante Path Analysis
            try {
                $sqlCursos = "UPDATE cursos c
                              JOIN raw_moodle_categorias rc ON c.id_categoria_moodle = rc.id
                              JOIN (
                                  SELECT rc_c.id, rc_c.name 
                                  FROM raw_moodle_categorias rc_c 
                                  WHERE rc_c.depth = 4
                              ) as cat_mapped ON (rc.path LIKE CONCAT('%/', cat_mapped.id, '/%') 
                                                  OR rc.path LIKE CONCAT('%/', cat_mapped.id))
                              JOIN carreras loc_c ON cat_mapped.name = loc_c.nombre
                              SET c.carrera_id = loc_c.id
                              WHERE c.carrera_id IS NULL OR c.carrera_id != loc_c.id";
                
                $stmt = $this->db->prepare($sqlCursos);
                $stmt->execute();
                $mappingStats['cursos_mapeados'] = $stmt->rowCount();
            } catch (\Exception $e) {
                LoggerService::error("Error mapeando cursos automáticos", ['error' => $e->getMessage()]);
            }

            LoggerService::info("Mapeo académico automático finalizado", $mappingStats);
            return $mappingStats;

        } catch (\Exception $e) {
            LoggerService::error("Error en mapeo académico automático", ['error' => $e->getMessage()]);
            return $mappingStats;
        }
    }
}
