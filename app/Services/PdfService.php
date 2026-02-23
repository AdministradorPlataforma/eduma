<?php
declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class PdfService {

    public function generateActaAprobacion(array $tesis): void {
        
        // 1. Configurar Dompdf
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        
        $dompdf = new Dompdf($options);

        // 2. Generar QR Code
        // URL de validación hipotética
        $validationUrl = BASE_URL . "validar/acta/" . ($tesis['codigo'] ?? $tesis['id']);
        
        $qrOptions = new QROptions([
            'version'    => 5,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel'   => QRCode::ECC_L,
        ]);
        
        $qrcode = (new QRCode($qrOptions))->render($validationUrl);

        // 3. Preparar HTML
        $logoPath = BASE_URL . 'images/logo_eduma.png'; // Asegurarse de tener un logo o usar DataURI
        // Para simplificar, usaremos un placeholder de texto si no hay logo
        
        $fecha = date('d/m/Y');
        $titulo = strtoupper($tesis['titulo']);
        $estudiantes = $tesis['estudiantes_nombres'] ?? 'ALUMNO NO ASIGNADO';
        $tutores = $tesis['tutores_nombres'] ?? 'TUTOR NO ASIGNADO';
        $codigo = $tesis['codigo'] ?? 'S/C';

        $html = "
        <html>
        <head>
            <style>
                body { font-family: 'Helvetica', sans-serif; margin: 40px; color: #333; }
                .header { text-align: center; margin-bottom: 50px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
                .subtitle { font-size: 16px; text-transform: uppercase; letter-spacing: 2px; }
                .content { line-height: 1.8; font-size: 14px; text-align: justify; margin-bottom: 50px; }
                .highlight { font-weight: bold; }
                .signatures { margin-top: 100px; width: 100%; text-align: center; }
                .signature-box { display: inline-block; width: 40%; border-top: 1px solid #333; margin: 0 4%; padding-top: 10px; }
                .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 10px; text-align: center; color: #777; border-top: 1px solid #ddd; padding-top: 10px; }
                .qr-box { position: absolute; top: 20px; right: 20px; width: 100px; height: 100px; }
            </style>
        </head>
        <body>
            <div class='qr-box'>
                <img src='$qrcode' width='100' height='100' />
            </div>

            <div class='header'>
                <div class='title'>UNIVERSIDAD EDUMA</div>
                <div class='subtitle'>FACULTAD DE INGENIERÍA Y CIENCIAS</div>
            </div>

            <div style='text-align: center; margin-bottom: 40px;'>
                <h3>ACTA DE APROBACIÓN DE TESIS</h3>
                <p>REF: $codigo</p>
            </div>

            <div class='content'>
                <p>En la ciudad de Córdoba, a los $fecha, se hace constar que el trabajo de tesis titulado:</p>
                
                <p style='text-align: center; font-style: italic; font-weight: bold; margin: 20px 0;'>
                    “ $titulo ”
                </p>

                <p>Presentado por el/los alumno(s): <span class='highlight'>$estudiantes</span></p>
                
                <p>Bajo la dirección del tutor: <span class='highlight'>$tutores</span></p>

                <p>Ha sido evaluado y <strong>APROBADO</strong> por el comité académico designado, cumpliendo con todos los requisitos exigidos por el reglamento vigente de esta institución.</p>
                
                <p>Se extiende la presente acta para los fines que los interesados estimen convenientes.</p>
            </div>

            <div class='signatures'>
                <div class='signature-box'>
                    <p>Director de Carrera</p>
                </div>
                <div class='signature-box'>
                    <p>Secretario Académico</p>
                </div>
            </div>

            <div class='footer'>
                Documento generado electrónicamente por el Sistema de Gestión Académica EDUMA.<br>
                Validación: $validationUrl<br>
                Fecha de Generación: " . date('Y-m-d H:i:s') . "
            </div>
        </body>
        </html>
        ";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $dompdf->stream("Acta_Tesis_$codigo.pdf", ["Attachment" => false]);
        exit;
    }
}
