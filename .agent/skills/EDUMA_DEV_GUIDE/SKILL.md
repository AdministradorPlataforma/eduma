---
name: EDUMA_DEV_GUIDE
description: Guía de desarrollo oficial para el proyecto EDUMA (PHP MVC + Frontend Modular)
---

# Guía de Desarrollo EDUMA

Este documento define los estándares de arquitectura y frontend para el proyecto EDUMA.

## 1. Arquitectura de Archivos (La Verdad Absoluta)
Todas las modificaciones deben seguir esta estructura:
- `/config/`: Configuraciones (`database.php`, `moodle_ws.php`).
- `/public/`: Root web. Contiene `index.php`, `css/` y `js/`.
- `/app/Controllers/`: Controladores. Deben heredar de `BaseController`.
- `/app/Models/`: Modelos de datos (Capa de persistencia pura). Deben heredar de `BaseModel`.
- `/app/Repositories/`: Capa intermediaria para consultas complejas y relaciones de negocio.
- `/app/Events/`: Definición de eventos del sistema (clases DTO).
- `/app/Listeners/`: Lógica de respuesta a eventos específicos.
- `/app/Views/`: Vistas dinámicas (Estudiantes, Docentes, Administrativos).
- `/app/Views/Layouts/`: Componentes globales (Header, Sidebar, Footer, Navbar).
- `/app/Helpers/`: Clases de utilidad y seguridad.
- `/app/Middleware/`: Middlewares del sistema (Auth, Permisos).
- `/app/Services/`: Lógica de Negocio, Transacciones y Orquestación.
- `/app/Core/`: Núcleo del framework (Router, Container, EventDispatcher).
- `/modules/`: Módulos de lógica extendida (ej: Sincronizador).
- `/routes/web.php`: Definición de rutas limpias.
- `/config/`: Configuración global, constantes y mapeo de eventos (`events.php`).
- `/public/css/libraries/`: Librerías CSS locales (Prohibido CDNs).
- `/public/js/libraries/`: Librerías JS locales (Prohibido CDNs).


## 2. Regla de Oro: Naming & Capitalization
Para asegurar la compatibilidad con el Autoloader y sistemas Linux/Sistemas Sensibles:
- **Directorios Raíz**: Minúsculas (`app/`, `config/`, `modules/`, `routes/`, `public/`).
- **Subdirectorios de App**: PascalCase (`app/Controllers`, `app/Models`, `app/Views`, `app/Middleware`).
- **Namespaces**: Siguen el estándar PSR-4 manteniendo la convención de clases, aunque apunten a carpetas en minúscula.
  - `namespace App\Controllers;` -> Mapea a `app/Controllers/`.
  - `namespace Config;` -> Mapea a `config/`.
- **Clases/Archivos PHP**: PascalCase (`UsuarioModel.php`, `AuthController.php`).
- **Assets (CSS/JS)**: PascalCase (`Main.css`, `Auth.js`, `Usuario.css`). Siempre deben coincidir con el nombre de la Entidad o Vista.
- **Tipado Estricto**: Todo archivo PHP de lógica (Controllers, Models, Helpers, Core) DEBE empezar con `declare(strict_types=1);` inmediatamente después de `<?php`.
- **Excepción**: Los archivos de configuración de constantes pueden ser `constants.php`.

## 3. Identidad Híbrida y RBAC
EDUMA utiliza un sistema de identidad dual:
1. **Sync Moodle**: Usuarios vinculados mediante `id_moodle`.
2. **Local Management**: Gestión de usuarios administrativos 100% locales con RBAC (Roles y Permisos).
3. **Perfiles**: Datos extendidos en tablas `estudiantes`, `docentes` y `administrativos`.

## 4. Entorno Técnico
- **PHP**: Versión 7.4.33 (en `C:\wamp64\bin\php\php7.4.33\php.exe`).
- **Servidor**: Wamp64.
- **Base de Datos**: MySQL (EDUMA V2 Arquitectura Optimizada).

## 5. Gestión de Dependencias y Autoload
El proyecto utiliza **Composer** para la gestión de paquetes y la carga automática de clases siguiendo el estándar **PSR-4**.

### Autoloading
- **Namespace Raíz**: `App\` mapea a la carpeta `app/`.
- **Estructura de Namespaces**:
  - Controladores: `namespace App\Controllers;`
  - Modelos: `namespace App\Models;`
  - Repositorios: `namespace App\Repositories;`
  - Servicios: `namespace App\Services;`
  - Eventos: `namespace App\Events;`
  - Listeners: `namespace App\Listeners;`
  - Configuración: `namespace Config;`
  - Helpers: `namespace App\Helpers;`
  - Middlewares: `namespace App\Middleware;`
- **Registro de Clases**: Después de crear una nueva clase, si no se carga automáticamente, ejecutar:
  ```bash
  composer dump-autoload
  ```

### Requerimientos
- El archivo `public/index.php` debe incluir obligatoriamente el autoload de vendor:
  ```php
  require_once __DIR__ . '/../vendor/autoload.php';
  ```

## 6. Lógica de Negocio y Servicios
EDUMA utiliza una "Capa de Servicios" para desacoplar la lógica de negocio de los Controladores.

### Reglas para Servicios:
1. **Ubicación**: `/app/Services/`
2. **Herencia**: Todos los servicios extienden de `App\Services\BaseService`.
3. **Responsabilidad**:
   - **Controlador**: Valida entrada HTTP y llama al Servicio.
   - **Servicio**: Contiene todas las reglas de negocio, transacciones atómicas y despacho de eventos.
   - **Repository**: Maneja la persistencia y consultas SQL complejas (Joins).
   - **Modelo**: Representación simple de la tabla (CRUD básico vía `BaseModel`).
4. **Instanciación**: Inyectados automáticamente por el `Container`.

## 7. Patrón Repository y Modelos
Para mantener el sistema escalable y limpio de SQL "hardcoded" en los servicios:
- **BaseModel**: Provee `QueryBuilder` automático. Todo nuevo modelo DEBE heredar de él.
- **Repository**: Se encarga de las consultas complejas y la sincronización de tablas pivote (N:N). Los Servicios solo deben hablar con Repositorios.

## 8. Sistema de Eventos (Event Dispatcher)
EDUMA utiliza un sistema de eventos para desacoplar procesos secundarios (Efectos Colaterales):
1. **Definición**: Los eventos residen en `app/Events/`.
2. **Listeners**: Los componentes que reaccionan residen en `app/Listeners/`.
3. **Mapeo**: Se realiza en `config/events.php`.
4. **Despacho**: Se usa `EventDispatcher::getInstance()->dispatch(new MyEvent($data))`.

*Uso típico*: Al crear un recurso, disparar un evento para que los Listeners se encarguen de notificar al usuario, registrar auditoría o enviar correos, sin bloquear el flujo principal del servicio.

## 7. Ecosistema Vista-Asset (Parejas de Archivos)
Cada vista principal establecida en `App/Views/` debe tener sus assets correspondientes en `public/`:
- Vista: `App/Views/Modulo/Vista.php`
- Estilos: `public/css/Modulo.css`
- Lógica JS: `public/js/Modulo.js`

## 8. Sistema de Layouts
Las vistas NO deben incluir etiquetas `<html>`, `<head>` o `<body>` completas. Deben limitarse al contenido central envuelto en `<main class="content-wrapper">`.

Estructura requerida en cada vista:
```php
<?php include_once '../../Views/Layouts/Header.php'; ?>
<?php include_once '../../Views/Layouts/Navbar.php'; ?>
<?php include_once '../../Views/Layouts/Sidebar.php'; ?>

<main class="content-wrapper">
    <!-- Contenido específico aquí -->
</main>

<?php include_once '../../Views/Layouts/Footer.php'; ?>
```

## 9. Estándar Visual
- **Framework**: Bootstrap 5 (Clases utilitarias).
- **Personalización**: Los archivos CSS en `public/css/` deben usarse para la identidad visual premium (colores corporativos, degradados, micro-interacciones).
- **Componentes Globales**: Los estilos de componentes reutilizables (KPIs, Glass Panels, Action Cards, Badges Soft) deben residir en `Main.css` para disponibilidad global.
- **Tipografía**: Fuentes modernas (Inter, Roboto, etc.).
- **Prohibido**: NO usar el atributo `style="..."` en las vistas HTML.
- **Scripts Externos**: Prohibido usar `<script>` inline con lógica compleja. Toda la lógica de la vista debe residir en su archivo `public/js/Modulo.js` correspondiente.
- **Pasaje de Datos**: Usar atributos `data-*` en el HTML para pasar variables del backend al JavaScript externo (ej: `<canvas data-values="...">`).
- **Recursos Locales (No CDNs)**: Para garantizar la independencia y velocidad de la intranet, todas las librerías externas (Bootstrap, jQuery, SweetAlert2, Charts, etc.) DEBEN estar alojadas localmente en `public/css/libraries/` o `public/js/libraries/`. <mark>ESTÁ PROHIBIDO USAR CDNs.</mark>

## 10. BaseController y Middlewares
- **BaseController**: Provee métodos base como `$this->render()` y helper `$this->session`, pero la seguridad principal se ha movido a Middlewares.
- **Rutas y Middlewares**:
  - Se definen en `routes/web.php`.
  - Uso de auth: `$router->group(['middleware' => 'auth'], ...)`
  - Uso de permisos: `$router->group(['middleware' => 'permission:ver_usuario'], ...)`

## 11. Helpers de Seguridad (Obligatorios)
Para garantizar la integridad del sistema, se DEBEN utilizar los siguientes helpers:

### Protección CSRF (`CSRFHelper`)
- **Uso en Vistas (Formularios)**:
  ```php
  <form method="POST">
      <?= \App\Helpers\CSRFHelper::csrfField(); ?>
      ...
  </form>
  ```
- **Validación en Controlador**:
  ```php
  if (!\App\Helpers\CSRFHelper::validateToken($_POST['csrf_token'])) {
      die("Intento de ataque CSRF detectado.");
  }
  ```

### Saneamiento de Datos (`InputSanitizerHelper`)
- **Limpieza de POST**:
  ```php
  $cleanData = \App\Helpers\InputSanitizerHelper::sanitizeArray($_POST);
  ```

### Políticas de Contraseñas (`PasswordValidator`)
- **Verificación**: `PasswordValidator::verify($password, $hash);`
- **Hash**: `PasswordValidator::hash($password);`

### Prevención de Fuerza Bruta (`RateLimitHelper`)
- **Control**: `RateLimitHelper::check($ip_o_usuario);`

### Menú Dinámico (`MenuConfigHelper`)
- **Uso**: `MenuConfigHelper::getMenu()` filtra automáticamente por permisos.

### Validación Fluent (`ValidationHelper`)
- **Uso**: 
  ```php
  $val = ValidationHelper::make($_POST)->rule('email', 'required|email');
  if ($val->fails()) { ... }
  ```

## 12. Pruebas y Diagnóstico (Carpeta /tests/)
Para mantener el proyecto limpio y profesional, el directorio raíz NUNCA debe contener scripts de prueba, benchmarks o archivos temporales de diagnóstico.
- **Ubicación obligatoria**: `/tests/`.
- **Estandar de Carga**: Los archivos en `/tests/` deben requerir `vendor/autoload.php` y `app/Core/Autoloader.php` para funcionar en CLI.
- **Limpieza**: Cualquier archivo `.php` en la raíz que no sea parte del Core o el Entry Point será movido automáticamente a `/tests/`.

Estructura de un test:
```php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';
// Lógica de prueba...
```

## 13. Gestión de Identidad de Usuarios

### Origen de Usuarios

EDUMA maneja dos orígenes de usuarios:

| Origen | Campo `id_moodle` | Descripción |
|--------|-------------------|-------------|
| **Moodle** | `NOT NULL` | Sincronizado desde Moodle LMS |
| **Local** | `NULL` | Creado directamente en EDUMA |

### Vista de Usuarios

El listado de usuarios (`/usuario`) incluye una columna **"Origen"** con badges visuales:
- 🟠 ☁️ **Moodle**: Usuario sincronizado
- 🔵 💾 **Local**: Usuario creado localmente

### Servicios de Perfiles

#### `UserProfileService`
Gestiona la creación automática de perfiles extendidos:

```php
$profileService = new \App\Services\UserProfileService();

// Crear perfil según rol
$profileService->crearPerfilesParaUsuario($userId, ['student']);

// Sincronizar todos los perfiles faltantes
$profileService->sincronizarPerfilesDesdeMatriculas();

// Obtener usuario con todos sus perfiles
$data = $profileService->obtenerUsuarioConPerfiles($userId);
```

#### `SyncCleanupService`
Gestiona usuarios/cursos eliminados en Moodle:

```php
$cleanupService = new \App\Services\SyncCleanupService();

// Ver resumen de huérfanos
$summary = $cleanupService->obtenerResumenHuerfanos();

// Ejecutar limpieza (soft delete)
$result = $cleanupService->ejecutarLimpiezaCompleta();
```

### Tablas de Perfiles

| Tabla | Relación | Campos Clave |
|-------|----------|--------------|
| `estudiantes` | `usuario_id → usuarios.id` | `legajo`, `carrera_principal_id`, `anio_ingreso` |
| `docentes` | `usuario_id → usuarios.id` | `titulo_profesional`, `especialidad`, `tipo_contrato` |
| `administrativos` | `usuario_id → usuarios.id` | `cargo`, `departamento` |
| `audit_calificaciones` | `N/A` | `id`, `calificacion_id`, `valor_anterior`, `valor_nuevo`, `accion`, `created_at` |

## 14. Estándar de Diseño "Premium Masterpiece"

EDUMA V2 exige una estética ultra-premium que garantice una experiencia de usuario (UX) excepcional y moderna.

### Componentes Visuales Clave:
1. **Banner de Bienvenida**: Uso de gradientes líquidos (`linear-gradient(135deg, #0d6efd 0%, #6610f2 100%)`) y formas abstractas animadas.
2. **KPIs Modernos (`kpi-modern-v2`)**: Pantallas con tarjetas que incluyen:
   - Iconos flotantes con `backdrop-filter: blur(10px)`.
   - Bordes sutiles y sombras profundas (`0 10px 30px rgba(0,0,0,0.08)`).
   - Micro-animaciones al hacer hover (`transform: translateY(-5px)`).
3. **Glass Panels (`glass-panel-v2`)**: Contenedores con fondo semi-transparente, desenfoque de fondo y bordes de cristal.
4. **Tipografía**: Jerarquía clara usando fuentes modernas (Inter/Outfit) y pesos diferenciados.
5. **Colores**: Paletas curadas, no usar colores saturados primarios (ej: usar `#0d6efd` en lugar de `blue`).

### Reglas de Implementación:
- **Animaciones**: Usar `Intersection Observer` para disparar animaciones de entrada (`fade-up`, `zoom-in`) cuando los elementos son visibles.
- **Iconografía**: Uso consistente de Font Awesome 6 (duotone preferido para estados premium).
- **Responsive**: Layouts 100% fluidos con Flexbox y Grid, diseñados con enfoque "Mobile-first".
- **Interactividad**: Uso de librerías locales como `SweetAlert2` para feedback y `Chart.js` para visualización de datos con themes personalizados.

