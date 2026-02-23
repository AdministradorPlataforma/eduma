<?php
declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ExportService {
    
    /**
     * Exporta el listado de tesis a un archivo Excel (.xlsx)
     * @param array $data Los datos de las tesis
     */
    public function exportTesisToExcel(array $data): void {
        // 1. Crear nueva hoja de cálculo
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Listado de Tesis');

        // 2. Definir Cabeceras
        $headers = ['ID', 'Código', 'Título', 'Estudiante(s)', 'Tutor(es)', 'Estado', 'Fecha Registro'];
        $columnIndex = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($columnIndex . '1', $header);
            $columnIndex++;
        }

        // 3. Estilo de Cabecera (Azul Indigo Premium de EDUMA)
        $headerRange = 'A1:G1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '6366F1'], // Indigo Accent
            ],
        ]);

        // 4. Llenado de Datos
        $row = 2;
        foreach ($data as $t) {
            $sheet->setCellValue('A' . $row, $t['id']);
            $sheet->setCellValue('B' . $row, $t['codigo'] ?? 'N/A');
            $sheet->setCellValue('C' . $row, $t['titulo']);
            $sheet->setCellValue('D' . $row, $t['estudiantes_nombres'] ?? 'No asignado');
            $sheet->setCellValue('E' . $row, $t['tutores_nombres'] ?? 'No asignado');
            $sheet->setCellValue('F' . $row, $t['estado']);
            $sheet->setCellValue('G' . $row, date('d/m/Y H:i', strtotime($t['created_at'])));

            // Alineación centrada para ID y Estado
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $row++;
        }

        // 5. Ajustes de Formato (Auto-size y bordes)
        foreach (range('A', 'G') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $tableRange = 'A1:G' . ($row - 1);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // 6. Preparar descarga
        $filename = "Reporte_Tesis_" . date('Y-m-d_Hi') . ".xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
