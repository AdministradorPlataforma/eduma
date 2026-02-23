<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\Investigacion\TesisRepository;
use App\Events\Investigacion\TesisCreatedEvent;
use App\Core\Events\EventDispatcher;
use App\Helpers\InputSanitizerHelper;
use App\Helpers\SessionHelper;
use PDO;
use Exception;

class InvestigacionService extends BaseService {
    private TesisRepository $repository;
    private PDO $db;

    public function __construct(TesisRepository $repository, PDO $db) {
        $this->repository = $repository;
        $this->db = $db;
    }

    /**
     * Registra una nueva tesis con transaccionalidad atómica y despacho de eventos.
     */
    public function registrarTesis(\App\DTOs\Investigacion\TesisDTO $dto, int $directorId): int {
        try {
            $this->db->beginTransaction();

            $estudiantesIds = $dto->estudiantesIds;
            $docentesIds = $dto->docentesIds;

            if (empty($dto->titulo) || empty($estudiantesIds) || empty($docentesIds)) {
                throw new Exception("Datos incompletos: Título, estudiantes y docentes son requeridos.");
            }

            // 2. Procesar archivos
            $archivoPath = null;
            if ($dto->archivo && $dto->archivo['error'] === UPLOAD_ERR_OK) {
                $archivoPath = $this->subirArchivo($dto->archivo);
            }

            $archivoTesisPath = null;
            if ($dto->archivoTesis && $dto->archivoTesis['error'] === UPLOAD_ERR_OK) {
                $archivoTesisPath = $this->subirArchivo($dto->archivoTesis, 'documento_');
            }

            // 3. Preparar datos
            $codigo = 'TES-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)) . '-' . date('Y');
            
            $tesisData = [
                'titulo' => InputSanitizerHelper::sanitizeString($dto->titulo),
                'descripcion' => InputSanitizerHelper::sanitizeString($dto->descripcion),
                'director_id' => $directorId,
                'archivo_path' => $archivoPath,
                'archivo_tesis_path' => $archivoTesisPath,
                'estado' => $dto->estado,
                'codigo' => $codigo
            ];

            // 4. Persistir vía Repository
            $tesisId = $this->repository->create($tesisData, $estudiantesIds, $docentesIds);

            $this->db->commit();

            // 5. Despachar Evento
            EventDispatcher::getInstance()->dispatch(new TesisCreatedEvent(
                $tesisId, 
                $tesisData, 
                (new SessionHelper())->getUserId() ?? 0
            ));

            return $tesisId;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza una tesis existente.
     */
    public function actualizarTesis(int $id, \App\DTOs\Investigacion\TesisDTO $dto): bool {
        try {
            $this->db->beginTransaction();

            $existing = $this->repository->find($id);
            if (!$existing) {
                throw new Exception("Tesis no encontrada.");
            }

            $updateData = [];
            if (!empty($dto->titulo)) $updateData['titulo'] = InputSanitizerHelper::sanitizeString($dto->titulo);
            if (!empty($dto->descripcion)) $updateData['descripcion'] = InputSanitizerHelper::sanitizeString($dto->descripcion);
            if (!empty($dto->estado)) $updateData['estado'] = $dto->estado;

            // Archivo Formulario
            if ($dto->archivo && $dto->archivo['error'] === UPLOAD_ERR_OK) {
                if (!empty($existing['archivo_path'])) {
                    $old = __DIR__ . '/../../public/' . $existing['archivo_path'];
                    if (file_exists($old)) unlink($old);
                }
                $updateData['archivo_path'] = $this->subirArchivo($dto->archivo);
            }

            // Archivo Tesis
            if ($dto->archivoTesis && $dto->archivoTesis['error'] === UPLOAD_ERR_OK) {
                if (!empty($existing['archivo_tesis_path'])) {
                    $old = __DIR__ . '/../../public/' . $existing['archivo_tesis_path'];
                    if (file_exists($old)) unlink($old);
                }
                $updateData['archivo_tesis_path'] = $this->subirArchivo($dto->archivoTesis, 'documento_');
            }

            $estudiantesIds = !empty($dto->estudiantesIds) ? $dto->estudiantesIds : null;
            $docentesIds = !empty($dto->docentesIds) ? $dto->docentesIds : null;

            $success = $this->repository->update($id, $updateData, $estudiantesIds, $docentesIds);

            $this->db->commit();
            return $success;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function eliminarTesis(int $id): bool {
        try {
            $this->db->beginTransaction();
            
            $existing = $this->repository->find($id);
            if (!$existing) throw new Exception("Tesis no encontrada.");

            if (!empty($existing['archivo_path'])) {
                $path = __DIR__ . '/../../public/' . $existing['archivo_path'];
                if (file_exists($path)) unlink($path);
            }

            $deleted = $this->repository->delete($id);
            
            $this->db->commit();
            return $deleted;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function getTesisById(int $id): ?array {
        $tesis = $this->repository->find($id);
        if ($tesis) {
            $tesis['estudiantes'] = $this->repository->getEstudiantesList($id);
            $tesis['docentes'] = $this->repository->getDocentesList($id);
        }
        return $tesis;
    }

    private function subirArchivo(array $file, string $prefix = 'tesis_'): string {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $uploadDir = __DIR__ . '/../../public/uploads/tesis/';
        
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = uniqid($prefix, true) . '.' . $extension;
        $targetPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Error al mover archivo.");
        }

        return 'uploads/tesis/' . $filename;
    }

    // Delegamos la búsquedas al repositorio
    public function buscarEstudiantes(string $term): array {
        return $this->repository->searchEstudiantes($term);
    }

    public function buscarDocentes(string $term): array {
        return $this->repository->searchDocentes($term);
    }

    // Deprecated but kept for backward compatibility if needed elsewhere
    public function obtenerEstudiantesParaSelect(): array {
        // Redirigir a búsqueda general vacía o deprecado
        return [];
    }

    public function obtenerTutoresParaSelect(): array {
        return [];
    }

}
