---
name: EDUMA_RBAC_SYSTEM
description: Sistema de Control de Acceso Basado en Roles (RBAC) Local para EDUMA
---

# Sistema RBAC - EDUMA

Este documento detalla el funcionamiento del sistema de seguridad local que complementa los roles académicos de Moodle.

## 1. Conceptos Clave
- **Roles Local**: Agrupaciones de permisos para el personal administrativo o de gestión (ej: 'Secretaría', 'Super Admin').
- **Permisos**: Acciones atómicas definidas mediante un `slug` (ej: `usuario.crear`).
- **Banderas de Usuarios**: El sistema utiliza banderas en la tabla `usuarios` para identificar perfiles rápidos sin consultar múltiples tablas:
  - `es_admin`: Acceso al backend administrativo.
  - `es_estudiante`: Posee perfil en la tabla `estudiantes`.
  - `es_docente`: Posee perfil en la tabla `docentes`.

## 2. Estándar de Slugs de Permisos
Los slugs deben seguir el formato `entidad.accion`:
- `usuario.ver`, `usuario.crear`, `usuario.editar`, `usuario.eliminar`.
- `moodle.sync`: Permiso para ejecutar la sincronización.
- `rol.gestionar`: Permiso para entrar al módulo RBAC.

## 3. Implementación en Controlador
El `BaseController` provee métodos de ayuda:
```php
// Requiere login y el permiso específico
$this->requirePermission('usuario.ver');

// Validación de entrada
$val = \App\Helpers\ValidationHelper::make($_POST)
    ->rule('nombre', 'required|min:3');

if ($val->fails()) {
    $this->flash('error', $val->firstError());
    $this->redirect('ruta/crear');
}

// Requiere pertenecer a uno de estos roles locales (IDs)
$this->requireRoles([1, 2]); 
```

## 4. Gestión de Sesión
Durante el Login, se deben cargar los permisos del usuario en la sesión para evitar consultas recurrentes:
```php
$_SESSION['user_permissions'] = [
    'usuario.ver',
    'usuario.crear',
    // ...
];
```

## 5. Integración en UI (MenuConfigHelper)
Para mostrar u ocultar elementos del menú dinámicamente según permisos, se utiliza `App\Helpers\MenuConfigHelper`.

### Estructura del Menú
Define los ítems asociando un permiso (opcional):
```php
[
    'label' => 'Usuarios',
    'url' => 'usuario',
    'icon' => 'bi bi-people',
    'permission' => 'ver_usuario', // Permiso requerido
],
```

### Uso en Vistas (Sidebar)
```php
$menuItems = \App\Helpers\MenuConfigHelper::getMenu(); 

foreach ($menuItems as $item): ?>
    <li class="nav-item">
        <a href="<?= BASE_URL . $item['url'] ?>">
            <?= $item['label'] ?>
        </a>
    </li>
<?php endforeach; ?>
```
