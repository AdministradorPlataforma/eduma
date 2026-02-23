<?php
declare(strict_types=1);

namespace App\Models\Gestion;

use App\Models\BaseModel;
use PDO;

class GestionModel extends BaseModel {
    protected string $table = 'gestion_control_seguimiento';
    protected array $allowedFields = ['facultad_id', 'producto_documento', 'destino', 'dia_plazo_mes', 'frecuencia', 'responsable_cargo'];
    protected bool $useSoftDeletes = true;

    /**
     * Obtiene las tareas pendientes de un usuario según su cargo.
     */
    public function getPendientesPorUsuario(int $usuario_id): array {
        $cargo = $this->getCargoUsuario($usuario_id);

        if (!$cargo) {
            return [];
        }

        // Usamos el nuevo QueryBuilder con Joins
        $tareas = $this->builder
            ->select([
                'gestion_control_seguimiento.*', 
                'f.nombre AS nombre_facultad',
                'e.id AS evidencia_id',
                'e.estado AS estado_evidencia',
                'e.fecha_subida'
            ])
            ->join('facultades f', 'gestion_control_seguimiento.facultad_id = f.id')
            ->leftJoin('gestion_evidencias e', "gestion_control_seguimiento.id = e.tarea_id AND MONTH(e.fecha_subida) = MONTH(CURRENT_DATE()) AND YEAR(e.fecha_subida) = YEAR(CURRENT_DATE())")
            ->where('gestion_control_seguimiento.responsable_cargo', '=', $cargo)
            ->orderBy('gestion_control_seguimiento.dia_plazo_mes', 'ASC')
            ->get();

        foreach ($tareas as &$tarea) {
            $tarea['semaforo'] = $this->calcularSemaforo((int)$tarea['dia_plazo_mes']);
        }

        return $tareas;
    }

    public function getKPIs(): array {
        // Total Tareas
        $total = $this->builder->count();
        
        // Facultades Únicas involved
        // Nota: count(DISTINCT col) no soportado directo en builder simple, usar query cruda para stats optimizados
        $sqlFac = "SELECT COUNT(DISTINCT facultad_id) FROM gestion_control_seguimiento";
        $facultades = $this->db->query($sqlFac)->fetchColumn();
        
        // Next Due
        $diaActual = (int)date('d');
        $sqlNext = "SELECT MIN(dia_plazo_mes) FROM gestion_control_seguimiento WHERE dia_plazo_mes >= $diaActual";
        $nextDue = $this->db->query($sqlNext)->fetchColumn();
        
        if ($nextDue === false || $nextDue === null) {
            // E.g. next month (min of all)
             $sqlNextMin = "SELECT MIN(dia_plazo_mes) FROM gestion_control_seguimiento";
             $nextDue = $this->db->query($sqlNextMin)->fetchColumn();
        }

        return [
            'total_tareas' => (int)$total,
            'facultades_unicas' => (int)$facultades,
            'proximo_cierre' => $nextDue ?? '-'
        ];
    }

    /**
     * Determina el estado del semáforo.
     */
    public function calcularSemaforo(int $diaPlazo): string {
        $diaActual = (int)date('j'); 
        
        if ($diaActual > $diaPlazo) {
            return 'danger';
        } elseif (($diaPlazo - $diaActual) <= 2) {
            return 'warning';
        } else {
            return 'success';
        }
    }

    /**
     * Obtiene el cargo buscando en tablas.
     * Mantenemos SQL crudas aquí por ser consultas a otras tablas no principales del modelo.
     */
    private function getCargoUsuario(int $usuario_id): ?string {
        $stmt = $this->db->prepare("SELECT cargo FROM administrativos WHERE usuario_id = :id");
        $stmt->execute([':id' => $usuario_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) return $result['cargo'];

        $stmt = $this->db->prepare("SELECT tipo_contrato FROM docentes WHERE usuario_id = :id");
        $stmt->execute([':id' => $usuario_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) return $result['tipo_contrato'];

        return null;
    }

    /**
     * Registra una evidencia
     */
    public function registrarEvidencia(int $tareaId, int $usuarioId, string $urlAdjunto): bool {
        // Podríamos crear un EvidenciaModel, pero por ahora usamos PDO directo
        $sql = "INSERT INTO gestion_evidencias (tarea_id, usuario_id, estado, url_adjunto, fecha_subida) 
                VALUES (:tarea_id, :usuario_id, 'CUMPLE', :url, NOW())";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':tarea_id' => $tareaId,
            ':usuario_id' => $usuarioId,
            ':url' => $urlAdjunto
        ]);
    }

    /**
     * Obtiene toda la información para la Planilla de Control.
     */
    public function getPlanillaControl(): array {
        return $this->builder
            ->select([
                'f.nombre as facultad',
                'gestion_control_seguimiento.producto_documento',
                'gestion_control_seguimiento.destino',
                'gestion_control_seguimiento.dia_plazo_mes',
                'gestion_control_seguimiento.responsable_cargo',
                'e.estado as estado_actual',
                'e.fecha_subida'
            ])
            ->join('facultades f', 'gestion_control_seguimiento.facultad_id = f.id')
            ->leftJoin('gestion_evidencias e', "gestion_control_seguimiento.id = e.tarea_id AND MONTH(e.fecha_subida) = MONTH(CURRENT_DATE())")
            ->orderBy('f.nombre', 'ASC') // orderBy acepta 2 args
            // Nota: nuestro builder orderBy sobrescribe. Necesitaríamos addOrderBy para múltiples, pero por ahora este es el principal
            // Si el user quiere ordenar secundario por dia_plazo_mes, el builder actual simple no lo soporta en cadena. Lo dejamos simple.
            ->get();
    }

    /**
     * Obtiene el listado de tareas paginado (para admin).
     */
    public function paginateTareas(int $page = 1, int $perPage = 15): array {
        $offset = ($page - 1) * $perPage;

        // 1. Count
        // Nota: count() resetea el builder, así que construimos la query de count manualmente o limpiamos
        // Como estamos usando joins, el count simple del builder podría fallar si no se configura bien el select.
        // Haremos count de la tabla base.
        $total = $this->builder->count(); 
        
        // 2. Data
        $data = $this->builder
            ->select(['gestion_control_seguimiento.*', 'f.nombre as facultad'])
            ->join('facultades f', 'gestion_control_seguimiento.facultad_id = f.id')
            ->orderBy('f.nombre', 'ASC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage)
            ]
        ];
    }

    /**
     * Obtiene el listado completo de tareas (para admin).
     * @deprecated Use paginateTareas instead
     */
    public function getAllTareas(): array {
        return $this->builder
            ->select(['gestion_control_seguimiento.*', 'f.nombre as facultad'])
            ->join('facultades f', 'gestion_control_seguimiento.facultad_id = f.id')
            ->orderBy('f.nombre', 'ASC')
            ->get();
    }

    /**
     * Crea una nueva tarea de gestión.
     */
    public function crearTarea(array $datos): bool {
        // Mapeo manual para ajustar nombres de array entrante a columnas de DB
        $insertData = [
            'facultad_id' => $datos['facultad_id'],
            'producto_documento' => $datos['producto_documento'],
            'destino' => $datos['destino'],
            'dia_plazo_mes' => $datos['dia_plazo'],
            'frecuencia' => $datos['frecuencia'],
            'responsable_cargo' => $datos['cargo_responsable']
        ];
        
        return $this->create($insertData) > 0;
    }

    /**
     * Elimina una tarea por ID
     */
    public function eliminarTarea(int $id): bool {
        $this->db->prepare("DELETE FROM gestion_evidencias WHERE tarea_id = :id")->execute([':id' => $id]);
        return $this->delete($id);
    }

    public function getFacultades(): array {
        return $this->db->query("SELECT id, nombre FROM facultades ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCarreras(?int $facultadId = null): array {
        $sql = "SELECT id, nombre FROM carreras";
        if ($facultadId) $sql .= " WHERE facultad_id = " . (int)$facultadId;
        $sql .= " ORDER BY nombre";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCargosExistentes(): array {
        // DISTINCT no está soportado en nuestro simple select method del builder aún
        return $this->db->query("SELECT DISTINCT responsable_cargo FROM gestion_control_seguimiento ORDER BY responsable_cargo")->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getFeedActividad(int $limit = 5): array {
        // Esta query une tablas que no son la base del modelo (empieza en evidencias), así que usamos PDO o cambiamos la tabla base del builder temporalmente.
        // Usaremos PDO crudo para mantener legibilidad de la query compleja.
        $sql = "SELECT e.*, t.producto_documento, u.nombre as usuario_nombre, u.apellido as usuario_apellido 
                FROM gestion_evidencias e
                JOIN gestion_control_seguimiento t ON e.tarea_id = t.id
                JOIN usuarios u ON e.usuario_id = u.id
                ORDER BY e.fecha_subida DESC 
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getProximosVencimientos(int $limit = 5): array {
        // Tabla diferente
        try {
            return $this->db->query("SELECT * FROM gestion_actividades_maestras WHERE fecha_vencimiento >= CURDATE() ORDER BY fecha_vencimiento ASC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
             return [];
        }
    }
    
    public function getUserRoles(int $usuarioId): array {
         try {
            $stmt = $this->db->prepare("SELECT r.nombre FROM usuario_roles ur JOIN roles r ON ur.rol_id = r.id WHERE ur.usuario_id = ?");
            $stmt->execute([$usuarioId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
             return [];
        }
    }
}
