<?php
declare(strict_types=1);

/**
 * Registro de Eventos y Listeners de EDUMA
 */

use App\Core\Events\EventDispatcher;
use App\Events\Investigacion\TesisCreatedEvent;
use App\Listeners\Investigacion\TesisNotificationListener;
use App\Listeners\Investigacion\TesisAuditListener;

$dispatcher = EventDispatcher::getInstance();

// Tesis
$dispatcher->listen(TesisCreatedEvent::class, [TesisNotificationListener::class, 'handle']);
$dispatcher->listen(TesisCreatedEvent::class, [TesisAuditListener::class, 'handle']);

return $dispatcher;
