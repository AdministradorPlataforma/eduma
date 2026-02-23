---
name: EDUMA_DATABASE_MODELS
description: Estándar para la creación de modelos y gestión de base de datos en EDUMA V2
---

# Estándar de Modelos - EDUMA

Este documento define cómo interactuar con la base de datos y construir la capa de modelos en PHP 7.4 bajo la **Arquitectura Híbrida** (Sync Moodle + Gestión Local).

## 1. Identidad Híbrida
El sistema permite usuarios sincronizados de Moodle y usuarios 100% locales (ej: Administrativos).
- **Moodle**: `id_moodle` tiene valor. El `auth_method` suele ser `moodle` o `manual`.
- **Local**: `id_moodle` es `NULL`. Acceso exclusivo mediante login local.

## 2. Perfiles Extendidos
Los datos comunes están en `usuarios`. Los datos específicos en tablas relacionadas 1:1:
- `estudiantes`: Legajo, carrera, año ingreso.
- `docentes`: Título, especialidad, contrato.
- `administrativos`: Cargo, departamento.

## 2. Estructura de Persistencia (BaseModel & Repositories)
EDUMA utiliza una arquitectura de tres capas para los datos:
1. **BaseModel**: Clase base que hereda de `App\Models\BaseModel`, provee `QueryBuilder` y filtra campos permitidos automáticamente.
2. **Repository**: Clase en `App\Repositories\` que maneja la lógica de consultas complejas, JOINS y relaciones N:N.
3. **Container**: Gestiona la inyección de dependencias (`PDO` -> `Model` -> `Repository`).

```php
<?php
declare(strict_types=1);

namespace App\Models\Usuario;

use App\Models\BaseModel;

class UsuarioModel extends BaseModel {
    protected string $table = 'usuarios';
    protected array $allowedFields = ['nombre', 'email', 'password'];
}
```

## 3. Reglas de Inyección y Autowiring
El `Container` de EDUMA resuelve automáticamente las dependencias si se declaran en el constructor. Siempre se debe importar la clase PDO (`use PDO;`) en los archivos que la requieran para que el Autoloader funcione correctamente.


## 3. Reglas de Oro
- **Consultas Preparadas**: Siempre usar `prepare()` y `execute()` con placeholders para prevenir SQL Injection.
- **Tipado (PHP 7.4)**: Aunque limitado, usar `public`, `private`, `protected` y comentarios de tipo `@var`.
- **Gestión de Errores**: Capturar excepciones de PDO y registrarlas usando `error_log()`.
- **Transacciones**: Usar `$this->db->beginTransaction()` y `commit()` para operaciones que afecten múltiples tablas (ej: Sincronización).

## 5. Sistema RBAC (Roles y Permisos)
Para la autorización local, se debe consultar el sistema RBAC:
- `roles`: Definición de roles (Admin, Secretaria, etc).
- `permisos`: Acciones atómicas (`usuario.crear`, `moodle.sync`).
- `rol_permisos`: Mapeo Rol <-> Permiso.
- `usuario_roles`: Mapeo Usuario <-> Rol.

**Consulta de Permiso Sugerida:**
```sql
SELECT COUNT(*) FROM rol_permisos rp
JOIN usuario_roles ur ON rp.rol_id = ur.rol_id
JOIN permisos p ON rp.permiso_id = p.id
WHERE ur.usuario_id = :user_id AND p.slug = :perm_slug
```

## 6. Gestión y Seguimiento Académico
Tablas para el módulo de control de tareas y evidencias.

### `gestion_control_seguimiento`
Define las tareas recurrentes asignadas a facultades.
- `id`: PK
- `facultad_id`: FK a `facultades`.
- `producto_documento`: Nombre del entregable.
- `destino`: Destinatario.
- `dia_plazo_mes`: Día límite (1-31).
- `frecuencia`: 'mensual' o 'semestral'.
- `responsable_cargo`: Cargo responsable (ej: 'Decano').

### `gestion_evidencias`
Registra el cumplimiento de las tareas.
- `id`: PK
- `tarea_id`: FK a `gestion_control_seguimiento`.
- `usuario_id`: FK a `usuarios` (quien sube).
- `fecha_subida`: Timestamp.
- `estado`: 'CUMPLE' / 'NO CUMPLE'.
- `url_adjunto`: Path al archivo.

