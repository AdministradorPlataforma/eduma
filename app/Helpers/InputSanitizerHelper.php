<?php
declare(strict_types=1);

namespace App\Helpers;

class InputSanitizerHelper {
    /**
     * Sanea un string para prevenir XSS y ataques básicos.
     */
    public static function sanitizeString($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }

    /**
     * Sanea un array completo de inputs (ej: $_POST).
     */
    public static function sanitizeArray(array $data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } else {
                $sanitized[$key] = self::sanitizeString($value);
            }
        }
        return $sanitized;
    }

    /**
     * Valida y sanea un email.
     */
    public static function sanitizeEmail($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Valida y sanea un entero.
     */
    public static function sanitizeInt($int) {
        return filter_var($int, FILTER_SANITIZE_NUMBER_INT);
    }
}
