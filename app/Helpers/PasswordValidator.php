<?php
declare(strict_types=1);

namespace App\Helpers;

class PasswordValidator {
    /**
     * Valida la fortaleza de una contraseña.
     * Reglas por defecto: 8 char, 1 mayus, 1 minus, 1 num.
     */
    public static function validate($password, $min_length = 8) {
        $errors = [];

        if (strlen($password) < $min_length) {
            $errors[] = "La contraseña debe tener al menos $min_length caracteres.";
        }

        if (!preg_match("#[0-9]+#", $password)) {
            $errors[] = "La contraseña debe incluir al menos un número.";
        }

        if (!preg_match("#[a-z]+#", $password)) {
            $errors[] = "La contraseña debe incluir al menos una letra minúscula.";
        }

        if (!preg_match("#[A-Z]+#", $password)) {
            $errors[] = "La contraseña debe incluir al menos una letra mayúscula.";
        }

        return [
            'isValid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Crea un hash seguro de la contraseña.
     */
    public static function hash($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verifica una contraseña contra su hash.
     */
    public static function verify($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Verifica si el hash necesita ser regenerado (costo antiguo o algoritmo diferente).
     */
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
