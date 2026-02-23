<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\GradePredictionService;
use App\Helpers\ApiResponse;

class PredictionController extends BaseController
{
    private GradePredictionService $predictionService;

    public function __construct(GradePredictionService $predictionService)
    {
        parent::__construct();
        $this->predictionService = $predictionService;
    }

    /**
     * Dashboard de Predicción para Docentes.
     */
    public function teacherDashboard()
    {
        $this->requireLogin();
        $this->requirePermission('ver_cursos');

        $atRisk = $this->predictionService->getAtRiskStudents($this->getUserId());

        return $this->render('Prediccion/teacher', [
            'title' => 'Análisis de Riesgo Académico',
            'students' => $atRisk
        ]);
    }

    /**
     * API para obtener la predicción de un estudiante específico.
     */
    public function studentScore(int $courseId)
    {
        $this->requireLogin();
        $userId = $this->getUserId();
        
        $prediction = $this->predictionService->predictStudentRisk($userId, $courseId);
        return ApiResponse::success($prediction);
    }
}
