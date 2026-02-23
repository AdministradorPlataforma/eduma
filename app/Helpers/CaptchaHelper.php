<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Helper para la generación y validación de Captchas.
 * Utiliza la librería GD para generar imágenes con ruido y validación vía sesión.
 */
class CaptchaHelper {
    
    // Configuración por defecto
    private static $fontPath = __DIR__ . '/../../Public/css/fonts/Poppins/Poppins-Bold.ttf';
    private static $sessionKey = 'captcha_code';
    
    /**
     * Genera la imagen del Captcha y detiene la ejecución del script para mostrarla.
     * 
     * @param int $width Ancho de la imagen
     * @param int $height Alto de la imagen
     * @param int $characters Cantidad de caracteres
     * @return void
     */
    public static function render(int $width = 150, int $height = 50, int $characters = 5): void {
        self::initSession();
        
        $code = self::generateCode($characters);
        $_SESSION[self::$sessionKey] = $code;
        
        $image = imagecreatetruecolor($width, $height);
        
        // Colores
        $backgroundColor = imagecolorallocate($image, 255, 255, 255); // Blanco
        $textColor = imagecolorallocate($image, 51, 51, 51); // Gris oscuro premium
        $noiseColor = imagecolorallocate($image, 200, 200, 200); // Gris claro para ruido
        
        // Rellenar fondo
        imagefilledrectangle($image, 0, 0, $width, $height, $backgroundColor);
        
        // Añadir ruido (puntos)
        for ($i = 0; $i < $width * $height * 0.1; $i++) {
            imagefilledellipse($image, mt_rand(0, $width), mt_rand(0, $height), 1, 1, $noiseColor);
        }
        
        // Añadir ruido (líneas)
        for ($i = 0; $i < 5; $i++) {
            imageline($image, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), $noiseColor);
        }
        
        // Escribir texto
        $fontSize = 20;
        
        // Verificar si existe la fuente, si no usar fuente por defecto
        if (file_exists(self::$fontPath)) {
            // Calcular posición centrada aproximada
            $textBox = imagettfbbox($fontSize, 0, self::$fontPath, $code);
            $textWidth = $textBox[2] - $textBox[0];
            $x = ($width - $textWidth) / 2;
            $y = ($height + $fontSize) / 2; // Ajuste vertical simple
            
            imagettftext($image, $fontSize, 0, (int)$x, (int)$y, $textColor, self::$fontPath, $code);
        } else {
            // Fallback si no hay fuente TTF
            $x = ($width - (imagefontwidth(5) * strlen($code))) / 2;
            $y = ($height - imagefontheight(5)) / 2;
            imagestring($image, 5, (int)$x, (int)$y, $code, $textColor);
        }
        
        // Enviar headers y output
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        imagepng($image);
        imagedestroy($image);
        exit;
    }
    
    /**
     * Valida el código ingresado por el usuario.
     * 
     * @param string $input El código enviado por el usuario
     * @return bool True si es correcto, False si no
     */
    public static function validate(string $input): bool {
        self::initSession();
        
        if (empty($_SESSION[self::$sessionKey]) || empty($input)) {
            return false;
        }
        
        // Validación case-insensitive
        $isValid = strtoupper($input) === strtoupper($_SESSION[self::$sessionKey]);
        
        // Limpiar el captcha después de un intento para evitar reuso (seguridad)
        unset($_SESSION[self::$sessionKey]); 
        
        return $isValid;
    }
    
    /**
     * Genera un código aleatorio evitando caracteres confusos.
     */
    private static function generateCode(int $length): string {
        // Excluimos 0, O, I, 1, l para evitar confusión
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; 
        $code = '';
        $max = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[mt_rand(0, $max)];
        }
        
        return $code;
    }
    
    private static function initSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
