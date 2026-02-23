<?php
declare(strict_types=1);

namespace App\ViewModels;

class DashboardViewModel {
    
    private array $rawStats;
    private array $rawVencimientos;
    private array $rawFeed;
    private array $userContext;
    private array $filters;

    public function __construct(
        array $stats, 
        array $vencimientos, 
        array $feed, 
        array $userContext,
        array $filters
    ) {
        $this->rawStats = $stats;
        $this->rawVencimientos = $vencimientos;
        $this->rawFeed = $feed;
        $this->userContext = $userContext;
        $this->filters = $filters;
    }

    public function getStats(): object {
        return (object)[
            'total_usuarios' => number_format((float)($this->rawStats['total_usuarios'] ?? 0)),
            'total_tesis' => number_format((float)($this->rawStats['total_tesis'] ?? 0)),
            'total_cursos' => $this->rawStats['total_cursos_activos'] ?? 0,
            'cumplimiento' => number_format((float)($this->rawStats['cumplimiento_promedio'] ?? 0), 1),
            'unread_notifs' => $this->rawStats['unread_notifs'] ?? 0,
            'cumplimiento_raw' => (float)($this->rawStats['cumplimiento_promedio'] ?? 0)
        ];
    }

    public function getVencimientos(): array {
        $processed = [];
        $now = time();

        foreach ($this->rawVencimientos as $v) {
            $ts = strtotime($v['fecha_vencimiento']);
            $diffParams = ($ts - $now) / 86400;
            
            // Logic moved from View
            $urgentClass = 'info';
            if ($diffParams < 3) $urgentClass = 'urgent';
            elseif ($diffParams < 7) $urgentClass = 'warning';

            $item = new \stdClass();
            $item->nombre = $v['actividad_nombre'];
            $item->descripcion = $v['descripcion'] ?? 'Evento institucional';
            $item->fecha_formato = date('d/m', $ts);
            $item->css_class = $urgentClass; // urgent, warning, info
            $item->badge_color = ($urgentClass === 'urgent') ? 'rose' : 'indigo';
            
            $processed[] = $item;
        }

        return $processed;
    }

    public function getFeed(): array {
        $processed = [];
        foreach ($this->rawFeed as $item) {
            $descLower = strtolower($item['descripcion']);
            
            // Logic moved from View
            $icon = 'bi-check-circle'; 
            $color = 'indigo';
            
            if (strpos($descLower, 'creó') !== false) { 
                $icon = 'bi-plus-circle'; 
                $color = 'mint'; 
            } elseif (strpos($descLower, 'eliminó') !== false) {
                $icon = 'bi-trash';
                $color = 'rose';
            } elseif (strpos($descLower, 'error') !== false) {
                $icon = 'bi-exclamation-triangle';
                $color = 'orange';
            }

            $obj = new \stdClass();
            $obj->modulo = $item['modulo'] ?? 'Audit';
            $obj->descripcion = $item['descripcion'];
            $obj->time = date('H:i', strtotime($item['created_at']));
            $obj->icon = $icon;
            $obj->color = $color;

            $processed[] = $obj;
        }
        return $processed;
    }

    public function getUserDisplay(): object {
        return (object)[
            'name' => $this->userContext['name'] ?? 'Usuario',
            'roles' => $this->userContext['roles'] ?? [],
        ];
    }

    public function getChartData(): array {
        $val = (float)($this->rawStats['cumplimiento_promedio'] ?? 0);
        return [
            'cumplimiento' => $val,
            'pendiente' => 100 - $val
        ];
    }

    public function hasVencimientos(): bool {
        return !empty($this->rawVencimientos);
    }

    public function hasFeed(): bool {
        return !empty($this->rawFeed);
    }
}
