<?php
declare(strict_types=1);

namespace App\Exceptions\Moodle;

/**
 * Excepción dedicada para señalar que el usuario solicitó detener la sincronización.
 * 
 * Esta excepción NO debe ser capturada por catch genéricos (\Exception).
 * Solo debe ser capturada explícitamente por el orquestador de sincronización
 * para ejecutar un apagado limpio (graceful shutdown).
 * 
 * @version 1.0
 */
class StopSyncException extends \RuntimeException {
    
    public function __construct(string $context = '') {
        $message = 'USER_STOP_REQUESTED';
        if ($context) {
            $message .= " ($context)";
        }
        parent::__construct($message, 0, null);
    }
}
