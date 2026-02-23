<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\Investigacion\TesisRepository;
use App\Models\Usuario\UsuarioModel;
use App\Models\BaseModel; // Assuming we need DB access for custom queries

class UniversalSearchService {
    
    private TesisRepository $tesisRepo;
    private UsuarioModel $userModel;

    public function __construct(TesisRepository $tesisRepo, UsuarioModel $userModel) {
        $this->tesisRepo = $tesisRepo;
        $this->userModel = $userModel;
    }

    public function search(string $query): array {
        if (strlen($query) < 2) return [];

        // 1. Search Tesis
        $tesis = $this->tesisRepo->searchTesis($query);
        $formattedTesis = array_map(function($t) {
            return [
                'type' => 'tesis',
                'id' => $t['id'],
                'title' => $t['titulo'],
                'subtitle' => "Código: " . ($t['codigo'] ?? 'N/A') . " • Estado: " . $t['estado'],
                'url' => BASE_URL . "investigacion/ver/" . $t['id'], // Assuming route
                'icon' => 'bi-journal-text'
            ];
        }, $tesis);

        // 2. Search Users (Students & Docentes)
        // Since UsuarioModel generic search might return admins too, let's filter or adapt
        // We'll use a custom query on userModel to target students/docentes specifically if possible, 
        // or just use the generic paginated search logic but simplified.
        
        // For simplicity and to cover "Students and Docentes", we'll query users.
        // But we want to distinguish roles.
        $users = $this->searchUsersEfficiently($query);

        $formattedUsers = array_map(function($u) {
            $roleLabel = 'Usuario';
            $icon = 'bi-person';
            $url = BASE_URL . "perfil/ver/" . $u['id']; // Generic profile route

            if ($u['es_estudiante']) {
                $roleLabel = 'Estudiante';
                $icon = 'bi-mortarboard';
                // Maybe link to student specific view if exists
            } elseif ($u['es_docente']) {
                $roleLabel = 'Docente';
                $icon = 'bi-briefcase';
            } elseif ($u['es_admin']) {
               $roleLabel = 'Administrador';
               $icon = 'bi-shield-lock';
            }

            return [
                'type' => 'usuario',
                'id' => $u['id'],
                'title' => $u['nombre'] . ' ' . $u['apellido'],
                'subtitle' => $roleLabel . " • " . $u['username'], // Could add legajo if joined
                'url' => $url,
                'icon' => $icon
            ];
        }, $users);

        return [
            'results' => array_merge($formattedTesis, $formattedUsers)
        ];
    }

    private function searchUsersEfficiently(string $term): array {
        // Query similar to UsuarioModel::getPaginated but simpler
        // We can access the DB connection through the model (it extends BaseModel)
        // Or inject PDO directly. Let's rely on model's connection.
        
        // Reflection or public accessor to get DB? Usually protected.
        // Let's implement a specific search method in UsuarioModel or just instantiate a clone QueryBuilder.
        // Actually, let's just use the `getPaginated` method of UsuarioModel since I can't easily add methods without modifying it again.
        // Warning: getPaginated returns strictly paginated structure or array?
        // Checking UsuarioModel code view: it returns `fetchAll(PDO::FETCH_ASSOC)`. Good.
        
        return $this->userModel->getPaginated(0, 5, $term); 
    }
}
