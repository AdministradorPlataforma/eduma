<?php
declare(strict_types=1);

namespace App\Core;

class Router {
    protected $routes = [];
    protected $middlewares = [];
    protected $groupStack = [];

    /**
     * Mapeo de alias de middleware a clases.
     */
    protected $routeMiddleware = [
        'auth' => \App\Middleware\AuthMiddleware::class,
        'permission' => \App\Middleware\PermissionMiddleware::class,
    ];

    public function get($uri, $action, $options = []) {
        $this->addRoute('GET', $uri, $action, $options);
    }

    public function post($uri, $action, $options = []) {
        $this->addRoute('POST', $uri, $action, $options);
    }

    public function group($attributes, $callback) {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    protected function addRoute($method, $uri, $action, $options = []) {
        // 1. Construir URI con Prefijos
        $prefix = '';
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= trim($group['prefix'], '/') . '/';
            }
        }
        
        $uri = trim($prefix . trim($uri, '/'), '/');
        if ($uri === '') $uri = ''; // Mantener root como string vacío tras trim

        // 2. Recolectar Middlewares (Stack de Grupos + Opciones de Ruta)
        $middlewares = [];
        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $middlewares[] = $group['middleware'];
            }
        }
        
        // Agregar middleware específico de la ruta si existe
        if (isset($options['middleware'])) {
            $middlewares[] = $options['middleware'];
        }

        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => $middlewares
        ];
    }

    public function dispatch($uri, $method) {
        $uri = parse_url($uri, PHP_URL_PATH);
        
        // Limpiar la URI de la base del script dinámicamente
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']); // /eduma2/public/index.php
        $basePath = str_replace('\\', '/', dirname($scriptName));       // /eduma2/public
        $projectRoot = str_ireplace('/public', '', $basePath);         // /eduma2

        // Removemos las posibles variantes del prefijo para quedarnos con la ruta limpia
        $uri = str_ireplace([$scriptName, $basePath, $projectRoot], '', $uri);
        $uri = trim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['uri'] === $uri) {
                // 1. Enforzamiento automático de CSRF para métodos que alteran datos
                $this->enforceCSRF($method, $uri);

                // 2. Ejecutar Middlewares
                if (!empty($route['middleware'])) {
                    foreach ($route['middleware'] as $mwDef) {
                        $this->handleMiddleware($mwDef);
                    }
                }
                return $this->callAction($route['action']);
            }
            
            // Soporte para parámetros dinámicos simples {id}
            $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([a-zA-Z0-9_]+)', $route['uri']);
            if ($route['method'] === $method && preg_match('#^' . $pattern . '$#', $uri, $matches)) {
                $this->enforceCSRF($method, $uri);
                array_shift($matches);
                return $this->callAction($route['action'], $matches);
            }
        }

        header("HTTP/1.0 404 Not Found");
        require_once dirname(__DIR__) . '/Views/errors/404.php';
        exit;
    }

    /**
     * Valida automáticamente el token CSRF para peticiones no-GET.
     */
    protected function enforceCSRF(string $method, string $uri): void
    {
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return;
        }

        // Excepciones para Webhooks o APIs externas
        $exceptions = [
            'api/webhook/moodle',
            'api/sync/callback'
        ];

        if (in_array($uri, $exceptions)) {
            return;
        }

        // Obtener token de POST o Headers
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        // Verificar validez (usamos verify o validateToken según la implementación real del Helper)
        // Asumo verify por consistencia con controladores recientes.
        if (!\App\Helpers\CSRFHelper::validateToken($token)) {
            
            // Detectar AJAX
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
            
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido o expirado. Recargue la página.']);
                exit;
            } else {
                // Flujo Normal: Flash + Redirect Back
                // Iniciar sesión si no está iniciada para poder usar Flash
                if (session_status() === PHP_SESSION_NONE) session_start();
                
                $_SESSION['flash_error'] = 'Tu sesión o token de seguridad ha expirado. Por favor intenta nuevamente.';
                
                $referer = $_SERVER['HTTP_REFERER'] ?? '/';
                // Evitar bucles de redirección si el referer es la misma acción POST que falló (difícil en POST->Redirect pattern, pero posible)
                header("Location: " . $referer);
                exit;
            }
        }
    }

    protected function callAction($action, $params = []) {
        // 1. Manejar cierres (Closures) directamente
        if ($action instanceof \Closure) {
            return call_user_func_array($action, $params);
        }

        // 2. Manejar array [Controlador::class, 'metodo']
        if (is_array($action) && count($action) === 2) {
            $controllerClass = $action[0];
            $method = $action[1];

            $container = \App\Core\Container::getInstance();
            $controllerInstance = $container->get($controllerClass);
            
            return call_user_func_array([$controllerInstance, $method], $params);
        }

        // 3. Manejar formato legacy 'Controlador@metodo'
        if (is_string($action) && strpos($action, '@') !== false) {
            list($controller, $method) = explode('@', $action);
            
            // Intentar con namespace App\Controllers si no tiene uno
            $controllerClass = (strpos($controller, '\\') === false) 
                ? "App\\Controllers\\" . $controller 
                : $controller;

            if (class_exists($controllerClass)) {
                $instance = new $controllerClass();
                return call_user_func_array([$instance, $method], $params);
            }
        }

        if (is_callable($action)) {
            return call_user_func_array($action, $params);
        }
    }

    /**
     * Resuelve y ejecuta un middleware.
     * Soporta formato alias (auth) y alias con parámetros (permission:ver_x).
     */
    protected function handleMiddleware($mwDef) {
        $parts = explode(':', $mwDef, 2);
        $name = $parts[0];
        $param = $parts[1] ?? null;

        if (isset($this->routeMiddleware[$name])) {
            $class = $this->routeMiddleware[$name];
            if (class_exists($class)) {
                $instance = new $class();
                if (method_exists($instance, 'handle')) {
                    // Pasamos el parámetro si existe, o null
                    // La firma del middleware debe ser handle($param = null)
                    $instance->handle($param);
                }
            }
        }
    }
}
