<?php
/**
 * PoC: Verificación de Seguridad en Sanitización de Calificaciones
 * Simula la inyección de datos maliciosos para validar filtros XSS e integridad
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\LoggerService;
use App\Services\MoodleSyncOptimizedService;

// Mock simplificado del servicio para acceder a los métodos privados vía reflexión
// o simplemente duplicamos la lógica de sanitización aquí para probarla aisladamente
// ya que instanciar todo el servicio requiere DB y conexiones reales.

class SecurityTest {
    
    public function run() {
        echo "=== PoC: Test de Sanitización y Validación (v3.2) ===\n\n";
        
        $this->testFeedbackSanitization();
        $this->testGradeValidation();
        $this->testDateParsing();
        
        echo "\n=== Fin del Test ===\n";
    }
    
    // Copia exacta de la lógica implementada en MoodleSyncOptimizedService
    private function sanitizeGradeFeedback(string $feedback): string {
        if (empty($feedback)) return '';
        $feedback = html_entity_decode($feedback, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $allowedTags = '<p><br><strong><b><em><i><ul><ol><li><span>';
        $feedback = strip_tags($feedback, $allowedTags);
        $feedback = preg_replace('/<([a-z]+)\s+[^>]*?(on\w+|style|class|id|data-[a-z]+)=["\'][^"\']*["\'][^>]*>/i', '<$1>', $feedback);
        $feedback = preg_replace('/javascript\s*:/i', '', $feedback);
        $feedback = preg_replace('/data\s*:/i', '', $feedback);
        $feedback = preg_replace('/expression\s*\(/i', '', $feedback);
        return mb_substr($feedback, 0, 5000, 'UTF-8');
    }

    private function testFeedbackSanitization() {
        echo "[Test 1] Sanitización de Feedback (XSS)\n";
        
        $payloads = [
            'Normal' => [
                'input' => '<p>Buen trabajo</p>',
                'expect' => '<p>Buen trabajo</p>'
            ],
            'Script Básico' => [
                'input' => '<script>alert(1)</script>Hola',
                'expect' => 'Hola'
            ],
            'Evento OnClick' => [
                'input' => '<a href="#" onclick="stealCookies()">Click me</a>',
                'expect' => 'Click me' // strip_tags elimina <a> porque no está permitido
            ],
            'Atributo en tag permitido' => [
                'input' => '<b onmouseover="alert(1)">Negrita peligrosa</b>',
                'expect' => '<b>Negrita peligrosa</b>' // regex debe limpiar atributos
            ],
            'Javascript URI' => [
                'input' => '<a href="javascript:alert(1)">Link</a>',
                'expect' => 'Link'
            ],
            'Estilo malicioso' => [
                'input' => '<span style="background:url(javascript:alert(1))">Span</span>',
                'expect' => '<span>Span</span>'
            ]
        ];
        
        foreach ($payloads as $name => $test) {
            $result = $this->sanitizeGradeFeedback($test['input']);
            $pass = ($result === $test['expect']);
            $status = $pass ? "✓ PASS" : "✗ FAIL";
            
            echo "  $name: $status\n";
            if (!$pass) {
                echo "    In:  {$test['input']}\n";
                echo "    Out: $result\n";
                echo "    Exp: {$test['expect']}\n";
            }
        }
    }
    
    private function testGradeValidation() {
        echo "\n[Test 2] Validación de Rangos de Notas\n";
        
        $scenarios = [
            ['raw' => 85, 'max' => 100, 'valid' => true],
            ['raw' => -5, 'max' => 100, 'valid' => false], // Negativo -> se corrige a 0
            ['raw' => 105, 'max' => 100, 'valid' => false], // > Max -> se corrige a max
            ['raw' => 'hack', 'max' => 100, 'valid' => false], // No numérico
            ['raw' => 100.01, 'max' => 100, 'valid' => true], // Tolerancia 0.01 -> se redondea
        ];
        
        foreach ($scenarios as $s) {
            $gradeVal = null;
            $raw = $s['raw'];
            $max = $s['max'];
            
            // Lógica simulada
            if (is_numeric($raw)) {
                $rawFloat = floatval($raw);
                if ($rawFloat >= 0 && $rawFloat <= ($max + 0.01)) {
                    $gradeVal = round($rawFloat, 4);
                    $resultValid = true;
                } else {
                    $resultValid = false;
                }
            } else {
                $resultValid = false;
            }
            
            $pass = ($resultValid === $s['valid']);
            $status = $pass ? "✓ PASS" : "✗ FAIL";
            echo "  Nota $raw (Max $max): $status (Es válido: " . ($resultValid ? 'Si' : 'No') . ")\n";
        }
    }
    
    private function testDateParsing() {
        echo "\n[Test 3] Validación de Fechas\n";
        
        $ts = time();
        $valid = $this->parseGradeDate($ts);
        echo "  Timestamp actual: " . ($valid ? "✓ Válido ($valid)" : "✗ Inválido") . "\n";
        
        $invalid = $this->parseGradeDate('no-es-fecha');
        echo "  String texto: " . ($invalid === null ? "✓ Rechazado correctamente" : "✗ Aceptado incorrectamente") . "\n";
    }
    
    private function parseGradeDate($timestamp): ?string {
        if ($timestamp === null || $timestamp === '') return null;
        if (!is_numeric($timestamp)) return null;
        $ts = (int)$timestamp;
        if ($ts < 946684800 || $ts > 4102444800) return null;
        return date('Y-m-d H:i:s', $ts);
    }
}

(new SecurityTest())->run();
