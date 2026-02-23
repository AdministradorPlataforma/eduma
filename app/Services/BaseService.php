<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Clase base para todos los Servicios de la aplicación.
 * Proporciona funcionalidades comunes, manejo de excepciones y utilidades
 * compartidas entre servicios.
 */
abstract class BaseService {
    // Aquí puedes inyectar Modelos compartidos, Loggers, EventDispatchers...
    // Por ahora, al ser un proyecto MVC simple, es un placeholder para futuros usos.
    
    /**
     * Lanza una excepción controlada para mensajes amigables al usuario.
     * 
     * @param string $message
     * @throws \Exception
     */
    protected function error(string $message) {
        throw new \Exception($message);
    }
}
