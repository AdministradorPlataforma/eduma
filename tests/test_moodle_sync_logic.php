<?php
// Tests de verificación v2 para lógica de Moodle Sync

echo "--- INICIO DE VERIFICACIÓN v2 ---\n";

function sanitizeGradeFeedback_Improved($feedback) {
    if (empty($feedback)) return '';

    // Decodificar entidades
    $feedback = html_entity_decode($feedback, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // 1. Eliminar bloques peligrosos completos (script, style, object, iframe)
    // El modificador 's' permite que el punto coincida con saltos de línea
    $feedback = preg_replace('/<(script|style|object|iframe)[^>]*?>.*?<\/\1>/si', '', $feedback);

    // 2. Lista blanca estricta de tags permitidos
    $allowedTags = '<p><br><strong><b><em><i><ul><ol><li><span>';

    // 3. Eliminar tags no permitidos
    $feedback = strip_tags($feedback, $allowedTags);

    // 4. Eliminar TODOS los atributos excepto 'class' (opcional)
    // Para máxima seguridad en feedback de notas, eliminamos todo atributo.
    // Esta regex captura <tag ...> y lo reemplaza por <tag>
    // (?:\s+[^>]*)? maneja los atributos opcionales
    $feedback = preg_replace('/<([a-z][a-z0-9]*)(?:\s+[^>]*)?>/i', '<$1>', $feedback);

    return trim($feedback);
}

$payloads = [
    '<script>alert(1)</script>' => '',
    '<p onclick="alert(1)">Hola</p>' => '<p>Hola</p>',
    '<a href="javascript:alert(1)">Click</a>' => 'Click',
    '<b>Negrita</b>' => '<b>Negrita</b>',
    '<img src=x onerror=alert(1)>' => '',
    '<span style="color:red">Texto</span>' => '<span>Texto</span>',
    '<script>alert("multiline\nattack")</script>' => '',
    '<iframe><p>Malicioso</p></iframe>' => ''
];

$failed = 0;
foreach ($payloads as $input => $expected) {
    $result = sanitizeGradeFeedback_Improved($input);
    if ($result !== $expected) {
        echo "❌ Fallo: Input: '" . substr(htmlspecialchars($input), 0, 50) . "...'\n";
        echo "   Esperado: '$expected'\n";
        echo "   Obtenido: '$result'\n";
        $failed++;
    } else {
        echo "✅ Pasó: " . substr(htmlspecialchars($input), 0, 50) . "...\n";
    }
}

if ($failed === 0) {
    echo "\n>>> TODAS LAS PRUEBAS PASARON EXITOSAMENTE <<<\n";
} else {
    echo "\n>>> HUBO $failed FALLOS <<<\n";
    exit(1);
}
