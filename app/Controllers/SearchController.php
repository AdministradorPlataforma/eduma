<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\UniversalSearchService;

class SearchController extends BaseController {

    private UniversalSearchService $searchService;

    public function __construct(UniversalSearchService $searchService) {
        parent::__construct();
        $this->searchService = $searchService;
    }

    public function query() {
        $this->requireLogin();
        
        try {
            $query = trim($_GET['q'] ?? '');
            
            if (strlen($query) < 2) {
                echo json_encode(['results' => []]);
                return;
            }

            $results = $this->searchService->search($query);
            
            header('Content-Type: application/json');
            echo json_encode($results);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
