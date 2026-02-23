<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * ValidationHelper
 * 
 * Clase de utilidad para validar arrays de datos (comúnmente $_POST) de forma fluida y limpia.
 */
class ValidationHelper {
    
    private array $data;
    private array $errors = [];
    private array $aliases = [];

    /**
     * Constructor privado para forzar uso de método estático factory si se desea,
     * o público si se prefiere new ValidationHelper($data).
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Factory estático para iniciar la validación.
     */
    public static function make(array $data): self {
        return new self($data);
    }

    /**
     * Define un alias legible para el campo (para mensajes de error amigables).
     * @param string $field Nombre del campo en el array de datos
     * @param string $alias Nombre legible (ej: 'Confirmación de Password')
     */
    public function alias(string $field, string $alias): self {
        $this->aliases[$field] = $alias;
        return $this;
    }

    /**
     * Agrega múltiples reglas a un campo.
     * Ejemplo: ->rule('email', 'required|email|min:5')
     */
    public function rule(string $field, string $rules): self {
        $value = $this->data[$field] ?? null;
        $label = $this->aliases[$field] ?? ucfirst($field);
        $ruleList = explode('|', $rules);

        foreach ($ruleList as $ruleStr) {
            $params = [];
            // Separar regla de parámetros (ej: min:5)
            if (strpos($ruleStr, ':') !== false) {
                [$ruleName, $paramStr] = explode(':', $ruleStr, 2);
                $params = explode(',', $paramStr);
            } else {
                $ruleName = $ruleStr;
            }

            $method = 'check' . ucfirst($ruleName);
            
            if (method_exists($this, $method)) {
                // Pasamos valor, parámestros y toda la data por si se requiere contexto (ej: match field)
                $passed = $this->$method($value, $params, $this->data);
                
                if (!$passed) {
                    $this->addError($field, $ruleName, $label, $params);
                    // Detenemos validación de este campo al primer fallo para no acumular mensajes redundantes
                    break;
                }
            }
        }
        return $this;
    }

    /**
     * Retorna true si hubo errores.
     */
    public function fails(): bool {
        return !empty($this->errors);
    }

    /**
     * Retorna true si todo pasó correctamente.
     */
    public function passes(): bool {
        return empty($this->errors);
    }

    /**
     * Obtiene todos los errores.
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * Obtiene el primer mensaje de error (útil para flash messages simples).
     */
    public function firstError(): ?string {
        if (empty($this->errors)) return null;
        
        $firstField = array_key_first($this->errors);
        return $this->errors[$firstField][0] ?? null;
    }

    // --- Lógica Interna y Mensajes ---

    private function addError(string $field, string $rule, string $label, array $params) {
        $p1 = $params[0] ?? '';
        
        $messages = [
            'required' => "El campo '$label' es obligatorio.",
            'email' => "El '$label' no es una dirección de correo válida.",
            'min' => "El '$label' debe tener al menos $p1 caracteres.",
            'max' => "El '$label' no puede exceder los $p1 caracteres.",
            'numeric' => "El '$label' debe ser un valor numérico.",
            'integer' => "El '$label' debe ser un número entero.",
            'alpha' => "El campo '$label' solo puede contener letras.",
            'alphanum' => "El '$label' solo admite letras y números.",
            'match' => "El valor de '$label' no coincide con el campo $p1.",
        ];

        $this->errors[$field][] = $messages[$rule] ?? "El campo '$label' contiene un error.";
    }

    // --- Reglas de Validación ---

    private function checkRequired($value): bool {
        if (is_array($value)) {
            return !empty($value);
        }
        return !is_null($value) && trim((string)$value) !== '';
    }

    private function checkEmail($value): bool {
        // Permitir vacío si no es required (la regla required se valida aparte)
        if (empty($value)) return true; 
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function checkMin($value, array $params): bool {
        $min = (int)($params[0] ?? 0);
        return strlen((string)$value) >= $min;
    }

    private function checkMax($value, array $params): bool {
        $max = (int)($params[0] ?? 0);
        return strlen((string)$value) <= $max;
    }

    private function checkNumeric($value): bool {
        if (empty($value)) return true;
        return is_numeric($value);
    }

    private function checkInteger($value): bool {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function checkAlpha($value): bool {
        if (empty($value)) return true;
        return ctype_alpha((string)$value);
    }

    private function checkAlphanum($value): bool {
        if (empty($value)) return true;
        return ctype_alnum((string)$value);
    }

    // Valida que el campo coincida con otro (ej: password confirm)
    private function checkMatch($value, array $params, array $allData): bool {
        $targetField = $params[0] ?? '';
        $targetValue = $allData[$targetField] ?? null;
        return $value === $targetValue;
    }

    private function checkRegex($value, array $params): bool {
        if (empty($value)) return true;
        // El parámetro viene como string, ej: "/^[a-z.]+$/"
        // Como los params se dividen por coma, regex con comas podría fallar, pero para usos simples sirve.
        // Ojo: en rule() explode usa comas. Para regex compleja mejor pasarla directo o usar otro delimitador.
        // Asumiremos que el regex se pasa como primer parámetro completo si no tiene comas.
        $pattern = $params[0] ?? '';
        return (bool)preg_match($pattern, (string)$value);
    }
}
