# 🔍 AUDITORÍA TÉCNICA COMPLETA — EDUMA V2
### Fecha: 2026-02-23 | Auditor: Arquitecto de Software Sr.
### Alcance: Backend (PHP MVC), Base de Datos (MySQL), Frontend (Bootstrap 5), Sincronización Moodle, Seguridad

---

## 📋 RESUMEN EJECUTIVO

EDUMA V2 es un sistema de gestión académica con arquitectura **PHP MVC custom** + **Bootstrap 5** que sincroniza datos masivos desde Moodle (22K+ usuarios, 10K+ cursos). El sistema presenta una base sólida con buenas prácticas en varias áreas (strict types, RBAC, CSRF, CSP headers), pero tiene **deficiencias críticas** en la capa de datos, **deuda técnica significativa** en la arquitectura de servicios, y **oportunidades funcionales no explotadas** que podrían transformar la plataforma.

### Puntuación General

| Área | Puntuación | Estado |
|------|-----------|--------|
| Arquitectura MVC | 7/10 | 🟢 Buena |
| Seguridad | 6/10 | 🟡 Requiere atención |
| Base de Datos | 5/10 | 🟠 Deficiente |
| Sincronización Moodle | 8/10 | 🟢 Muy buena |
| Frontend/UX | 6/10 | 🟡 Mejorable |
| Testing | 2/10 | 🔴 Crítico |
| Escalabilidad | 5/10 | 🟠 Limitada |
| Documentación | 7/10 | 🟢 Buena (Skills) |

---

## 1. 🏗️ ARQUITECTURA MVC — ANÁLISIS

### 1.1 Estructura de Capas (Fortalezas)

El proyecto implementa una separación de responsabilidades clara:

```
Controller → Service → Repository/Model → Database
     ↓           ↓
   View      EventDispatcher → Listeners
```

**✅ Lo que funciona bien:**
- `BaseController` provee métodos estandarizados (`render()`, `jsonResponse()`, `redirect()`, `flash()`, `requirePermission()`).
- Container de DI con auto-resolución por Reflection (`Container.php`).
- Sistema de rutas limpio con groups, prefixes y middlewares (`routes/web.php`).
- Validación CSRF automática en el Router para todas las peticiones POST.
- Capa de Servicios (`BaseService`, `AuthService`, etc.) para desacoplar lógica.

### 1.2 Hallazgos y Deficiencias Arquitectónicas

#### 🔴 CRÍTICO: Falta de validación de entrada consistente
**Archivo afectado**: Múltiples controladores.

Los controladores acceden directamente a `$_POST` y `$_GET` sin usar `ValidationHelper` de forma consistente. El helper existe pero su adopción es parcial.

```php
// EJEMPLO PROBLEMÁTICO (patrón encontrado en varios controllers)
$data = $_POST; // Sin sanitizar ni validar
$this->service->guardar($data);
```

**Recomendación**: Implementar middleware de validación o forzar el uso de `ValidationHelper::make()` y `InputSanitizerHelper::sanitizeArray()` en TODOS los controladores.

---

#### 🟠 IMPORTANTE: QueryBuilder con limitaciones serias
**Archivo**: `app/Core/Database/QueryBuilder.php` (249 líneas)

El QueryBuilder tiene problemas de diseño:

1. **Reset agresivo**: Llama a `reset()` después de cada `get()`, `count()`, `update()` y `delete()`. Esto hace **imposible** reutilizar condiciones entre `count()` y `get()`, lo cual el propio `BaseModel::paginate()` reconoce en sus comentarios como un "hack temporal."

2. **Sin soporte para whereIn()**: Indispensable para queries con listas de IDs (muy frecuente en sincronización Moodle). El código actual usa SQL raw en muchos servicios.

3. **Sin soporte para subqueries/having/raw expressions**: Limita la expresividad del builder.

4. **Inyección SQL potencial en Joins**: `$onClause` se interpola directamente sin parametrización:
   ```php
   // QueryBuilder.php:37
   $this->joins[] = strtoupper($type) . " JOIN $table ON $onClause";
   ```

**Recomendación**: Extender significativamente el QueryBuilder:
- Agregar `whereIn()`, `whereNotIn()`, `whereBetween()`, `whereRaw()`
- Implementar `clone()` para poder hacer count + get sin reset
- Parametrizar los Joins

---

#### 🟡 MEDIO: Container no cachea auto-resoluciones
**Archivo**: `app/Core/Container.php` (120 líneas)

Cuando se auto-resuelve una clase (línea 107), la instancia **no se almacena** como singleton. Esto significa que cada llamada a `get(SomeService::class)` crea una nueva instancia (salvo que se haya hecho `bind()` explícito).

```php
// Container.php:107 - La instancia se crea pero no se guarda
$instance = $reflection->newInstanceArgs($dependencies);
return $instance; // ⚠️ Sin cachear
```

**Impacto**: En una petición que pasa por Controller → Service → Repository, se pueden crear múltiples instancias duplicadas de servicios pesados.

**Recomendación**: Cachear automáticamente las instancias auto-resueltas como singletons (opt-out para casos que necesiten instancias frescas).

---

#### 🟡 MEDIO: Servicios con responsabilidades mixtas
**Archivos afectados**: `MoodleSyncOptimizedService.php` (1149 líneas), `BulkDatabaseService.php` (980 líneas)

Estos servicios han crecido orgánicamente y mezclan:
- Lógica de orquestación
- Acceso directo a PDO con SQL raw
- Lógica de mapeo de datos
- Logging y telemetría

**Recomendación**: Extraer sub-servicios por dominio:
- `CategorySyncService`, `CourseSyncService`, `UserSyncService`, `GradeSyncService` (algunos ya existen parcialmente en `app/Services/Sync/`)
- `SyncTelemetryService` para métricas y logging de sincronización

---

## 2. 🔒 SEGURIDAD — ANÁLISIS

### 2.1 Fortalezas de Seguridad

| Feature | Estado | Archivo |
|---------|--------|---------|
| CSRF automático (Router) | ✅ Implementado | `Router.php:109-153` |
| CSP Headers | ✅ Completo | `index.php:74-87` |
| X-Frame-Options: DENY | ✅ | `index.php:88` |
| Rate Limiting | ✅ | `RateLimitHelper.php` |
| Session Fixation Prevention | ✅ | `AuthService.php:92` |
| Password Migration (MD5→bcrypt) | ✅ | `AuthService.php:44-59` |
| Strict Types | ✅ | Todos los archivos PHP |
| RBAC con Middleware | ✅ | `PermissionMiddleware.php` |
| Session en DB | ✅ | `DatabaseSessionHandler.php` |
| Captcha en Login | ✅ | `CaptchaHelper.php` |

### 2.2 Vulnerabilidades y Riesgos

#### 🔴 CRÍTICO: Soporte Legacy MD5/SHA1 en AuthService
**Archivo**: `app/Services/AuthService.php:44-52`

```php
if (md5($password) === $user['password']) {
     $passwordNeedsRehash = true;
     $isValid = true;
} elseif (sha1($password) === $user['password']) {
     $passwordNeedsRehash = true;
     $isValid = true;
}
```

**Problema**: Aunque se auto-migra a bcrypt en el siguiente login, mientras existan hashes MD5/SHA1 en la base de datos, un dump de BD expone passwords trivialmente crackeables (rainbow tables). Además, la comparación `md5($password)` hace el hash en cada login intento, exponiendo al servidor a timing attacks.

**Recomendación**:
1. Ejecutar una migración forzada: resetear todos los passwords MD5/SHA1 y forzar cambio de contraseña.
2. Establecer un deadline (ej: 90 días) para eliminar el soporte legacy.
3. Usar `hash_equals()` para comparaciones constantes.

---

#### 🟠 IMPORTANTE: Admin Bypass en RBAC sin scope
**Archivos**: `AuthService.php:132-144`, `PermissionMiddleware.php:33-35`

```php
// PermissionMiddleware.php:33
if ($session->isAdmin()) {
    return; // Bypass total
}
```

El flag `es_admin = 1` otorga acceso **total** a todo el sistema sin ningún rastro de auditoría ni scope. Además, en `AuthService::loginUser()` se hardcodean permisos para admin:

```php
$permissions = array_merge($permissions, [
    'ver_usuario', 'crear_usuario', ... // Lista hardcodeada
]);
```

**Problema**: No hay separación entre "SuperAdmin" y "Admin departamental". Cualquier admin tiene acceso a todo.

**Recomendación**:
1. Eliminar el bypass `isAdmin()` en el middleware.
2. Usar el rol "Super Admin" (ID 1) con **todos** los permisos asignados vía la tabla pivot `rol_permisos`.
3. Remover los permisos hardcodeados en `loginUser()`.
4. Crear roles administrativos con scope (Admin Facultad, Admin Carrera, etc.).

---

#### 🟠 IMPORTANTE: `extract()` en render()
**Archivo**: `BaseController.php:199`

```php
extract($data, EXTR_SKIP);
```

Si bien `EXTR_SKIP` evita sobrescribir variables existentes, `extract()` sigue siendo una fuente de bugs difíciles de rastrear y potencial vector de inyección si `$data` contiene claves inesperadas.

**Recomendación**: Pasar `$data` como variable de contexto `$viewData` y acceder explícitamente en las vistas: `$viewData['field']`.

---

#### 🟡 MEDIO: Acceso a `$_SESSION` directo en MenuConfigHelper
**Archivo**: `MenuConfigHelper.php:177`

```php
$userPermissions = $_SESSION['user_permissions'] ?? [];
```

Se accede a `$_SESSION` directamente en vez de usar `SessionHelper`, rompiendo la encapsulación.

---

#### 🟡 MEDIO: Webhook Moodle sin autenticación propia
**Archivo**: `routes/web.php:178`

```php
$r->post('api/webhook', [MoodleWebhookController::class, 'handle']);
```

El webhook está dentro del grupo `middleware => 'auth'` + `permission:sincronizar_moodle`. Esto significa que Moodle no puede llamar al webhook sin tener una sesión PHP válida, lo cual es incorrecto para webhooks.

**Recomendación**: Mover el webhook fuera del grupo auth y protegerlo con un shared secret o token API (`X-Moodle-Token` en header).

---

## 3. 🗄️ BASE DE DATOS — ANÁLISIS

### 3.1 Esquema Actual

```
raw_moodle_categorias → vista_estructura_academica (VIEW)
facultades → carreras → cursos
usuarios → estudiantes | docentes | administrativos
usuarios → usuario_roles → roles → rol_permisos → permisos
usuarios ←→ curso_matriculas → cursos
curso_matriculas → calificaciones
cohortes
sync_logs | audit_logs | queue_jobs
```

### 3.2 Hallazgos Críticos

#### 🔴 CRÍTICO: Falta tabla de Asistencia/Attendance
No existe una tabla para registrar asistencia de estudiantes, pese a que la conversación anterior muestra que hay una vista `panel.php` de asistencia y un `AsistenciaModel.php` que no aparece en la estructura actual de modelos.

**Recomendación**: Crear tabla normalizada:

```sql
CREATE TABLE asistencias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricula_id BIGINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    estado ENUM('presente', 'ausente', 'tardanza', 'justificado') NOT NULL,
    observaciones TEXT NULL,
    registrado_por INT UNSIGNED NULL,  -- docente o admin
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_asistencia (matricula_id, fecha),
    FOREIGN KEY (matricula_id) REFERENCES curso_matriculas(id) ON DELETE CASCADE,
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_fecha (fecha),
    INDEX idx_estado (estado)
) ENGINE=InnoDB;
```

---

#### 🔴 CRÍTICO: Sin normalización de periodos académicos
No existe una tabla para **periodos académicos** (semestres/cuatrimestres/años). Los datos temporales se extraen como texto plano de la jerarquía de categorías Moodle (`semestre_texto`, `anio_texto` en cursos).

**Impacto**: 
- No se puede filtrar por período activo de forma eficiente.
- No se puede cerrar un período (congelar calificaciones).
- No se puede generar reportes comparativos entre períodos.

**Recomendación**:
```sql
CREATE TABLE periodos_academicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,  -- '2026-1', '2025-2'
    nombre VARCHAR(100) NOT NULL,        -- 'Primer Semestre 2026'
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado ENUM('planificacion', 'activo', 'cerrado', 'archivado') DEFAULT 'planificacion',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estado (estado),
    INDEX idx_fechas (fecha_inicio, fecha_fin)
) ENGINE=InnoDB;
```

---

#### 🟠 IMPORTANTE: Tabla `calificaciones` sin índices de rendimiento
**Archivo**: `bd/modelo_bd.sql:220-238`

La tabla `calificaciones` tiene un UNIQUE KEY en `(matricula_id, id_moodle_item)` pero carece de:
- Índice en `fecha_modificacion` (para queries de calificaciones recientes)
- Índice en `calificacion_final` (para rangos/estadísticas)

Con 22K+ usuarios y potencialmente millones de registros de calificaciones, esto causará degradación.

---

#### 🟠 IMPORTANTE: Sin tabla de Notificaciones persistentes
Existe `NotificationService.php` y `NotificationController.php` pero no hay una tabla de notificaciones en el esquema SQL. Las notificaciones probablemente se almacenan de forma no persistente.

**Recomendación**:
```sql
CREATE TABLE notificaciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(50) NOT NULL,           -- 'sync_complete', 'grade_update', 'task_assigned'
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT,
    data JSON NULL,                      -- Metadatos adicionales
    leida TINYINT(1) DEFAULT 0,
    leida_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_leida (usuario_id, leida),
    INDEX idx_tipo (tipo),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;
```

---

#### 🟡 MEDIO: Migraciones sin control de versión formal
Las migraciones están dispersas en `bd/` como archivos SQL sueltos con nombres inconsistentes:
- `001_gestion_academica.sql`
- `2026_02_05_add_sync_hash_columns.sql`
- `bd/migrations/2026_02_16_create_password_resets.sql`

Existe `MigrationRunner.php` pero no hay un registro de qué migraciones se han ejecutado (tabla `migrations`).

**Recomendación**: Implementar tabla de control:
```sql
CREATE TABLE migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

---

#### 🟡 MEDIO: Sin tabla para Documentos/Archivos
La funcionalidad de "subir evidencia" (`GestionController::subirEvidencia()`) y el directorio `uploads/` y `public/uploads/` existen, pero no hay tabla para trackear los archivos subidos.

```sql
CREATE TABLE documentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    entidad_tipo VARCHAR(50) NOT NULL,     -- 'tarea', 'tesis', 'evidencia'
    entidad_id BIGINT UNSIGNED NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_almacenado VARCHAR(255) NOT NULL,
    ruta VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100),
    tamano_bytes BIGINT UNSIGNED,
    hash_sha256 VARCHAR(64),              -- Integridad
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_entidad (entidad_tipo, entidad_id)
) ENGINE=InnoDB;
```

---

## 4. 🔄 SINCRONIZACIÓN MOODLE — ANÁLISIS

### 4.1 Fortalezas

Esta es la parte **más robusta** del sistema, con ingeniería de nivel senior:

| Feature | Implementación |
|---------|---------------|
| Circuit Breaker | ✅ Persistencia en archivo + estado compartido |
| Reintentos con backoff exponencial | ✅ MoodleClient::call() |
| Requests paralelos (curl_multi) | ✅ MoodleParallelClient |
| Early-stop por alta tasa de fallas | ✅ >50% del chunk |
| Bulk INSERT/UPSERT | ✅ BulkDatabaseService |
| Delta Sync (incremental) | ✅ data_hash comparison |
| Estado en BD (no archivos) | ✅ SyncStateDbService |
| Estado Legacy JSON (fallback) | ✅ Para compatibilidad |
| Health Check de API | ✅ MoodleClient::healthCheck() |
| Sanitización XSS en calificaciones | ✅ sanitizeGradeItemName/Feedback |
| Detección HTML vs JSON en respuestas | ✅ isHtmlResponse() |
| SSE (Server-Sent Events) para progreso | ✅ streamProgress() |
| Checkpoint/Resume | ✅ saveCheckpoint/getCheckpoint |

### 4.2 Mejoras Recomendadas

#### 🟡 MEDIO: Sync Log sin rotación automática
La tabla `sync_logs` crecerá indefinidamente. `SyncStateDbService::cleanOldLogs()` existe pero no se invoca automáticamente.

**Recomendación**: Ejecutar `cleanOldLogs(30)` al inicio de cada sincronización completa, o vía el `JanitorService`.

---

#### 🟡 MEDIO: Circuit Breaker state file fuera de storage
El archivo de persistencia del circuit breaker se guarda en `storage/moodle_circuit.json`. Si `storage/` no tiene permisos de escritura, falla silenciosamente.

**Recomendación**: Migrar el estado del circuit breaker a la tabla `sync_state` en base de datos, eliminando la dependencia en archivos.

---

## 5. 🖥️ FRONTEND — ANÁLISIS

### 5.1 Estructura Actual

```
public/css/      → 88 archivos (incluye libraries/)
public/js/       → 16 archivos de módulo + libraries/
app/Views/       → 15 secciones con 37+ archivos de vista
```

### 5.2 Hallazgos

#### 🟠 IMPORTANTE: 88 archivos CSS sin bundling/minificación
No hay proceso de build. Cada página carga múltiples archivos CSS y JS sin comprimir. En una intranet esto puede ser tolerable, pero impacta el performance y mantenibilidad.

**Recomendación**: Implementar un script PHP simple de concatenación y minificación, o un Makefile que genere `app.min.css` y `app.min.js`.

---

#### 🟡 MEDIO: JavaScript sin módulos/patrón
Los archivos JS son scripts procedurales. No usan módulos ES6, clases, ni namespacing. Ejemplo: `Moodle.js` tiene **30K bytes** de código procedural.

**Recomendación**: Migrar gradualmente a un patrón de módulos simples:
```javascript
const MoodleModule = (() => {
    // privado
    const state = {};
    // público
    return { init, startSync, stopSync };
})();
document.addEventListener('DOMContentLoaded', MoodleModule.init);
```

---

## 6. 🧪 TESTING — ANÁLISIS

### 6.1 Estado Actual: Crítico

El directorio `tests/` contiene **25 archivos**, pero son principalmente scripts de diagnóstico y pruebas manuales, no tests automatizados (no hay PHPUnit, ni framework de testing).

**Recomendación priorizada**:

1. **Instalar PHPUnit** (`composer require --dev phpunit/phpunit`).
2. **Tests unitarios prioritarios**:
   - `AuthServiceTest` (autenticación, password migration)
   - `ValidationHelperTest` (reglas de validación)
   - `QueryBuilderTest` (queries construidas correctamente)
   - `CSRFHelperTest` (generación y validación de tokens)
3. **Tests de integración**:
   - `BulkDatabaseServiceTest` (UPSERT masivos)
   - `MoodleSyncOptimizedServiceTest` (fases de sincronización)

---

## 7. 🚀 PROPUESTAS DE NUEVAS FUNCIONALIDADES

### 7.1 ⭐ Módulo de Reportes Académicos (Alta prioridad)

**Justificación**: Con 22K+ usuarios y calificaciones sincronizadas, el sistema tiene datos pero **no herramientas de análisis visual**.

**Funcionalidades propuestas**:
- **Reporte de Rendimiento por Carrera**: Promedio general, distribución de notas (histograma), tasa de aprobación por materia.
- **Reporte de Rendimiento por Docente**: Comparativa de notas entre secciones del mismo curso.
- **Reporte de Deserción**: Estudiantes con N inasistencias consecutivas o calificaciones bajo umbral.
- **Reporte de Cohorte**: Seguimiento longitudinal de una generación de ingreso.
- **Exportación**: PDF y Excel con gráficos embebidos.

**Complejidad**: Media-Alta | **Impacto**: Muy alto

---

### 7.2 ⭐ Dashboard Personalizado por Rol

**Justificación**: Actualmente todos ven el mismo `Escritorio`. Un estudiante debería ver sus calificaciones; un docente, sus cursos; un admin, los KPIs del sistema.

**Propuesta**:
| Rol | Dashboard |
|-----|-----------|
| Estudiante | Mis cursos, Calificaciones, Asistencia, Tareas pendientes |
| Docente | Mis cursos, Alumnos en riesgo, Pendientes de calificación |
| Admin | KPIs globales, Sincronización, Auditoría, Usuarios activos |
| Super Admin | Todo lo anterior + Configuración del sistema |

**Complejidad**: Media | **Impacto**: Alto

---

### 7.3 ⭐ Sistema de Alertas Académicas Tempranas (SAT)

**Justificación**: Los datos de calificaciones y asistencia permiten detectar estudiantes en riesgo **antes** de que reprueben.

**Funcionalidades**:
- Algoritmo de scoring basado en: calificación actual vs promedio del curso, tendencia (mejorando/empeorando), asistencia, entrega de actividades.
- Alertas automáticas al tutor/docente cuando un estudiante cruza un umbral.
- Panel de "Estudiantes en Riesgo" para el coordinador de carrera.
- Integración con `GradePredictionService` (ya existe un stub: `PredictionController::studentScore`).

**Complejidad**: Alta | **Impacto**: Muy alto

---

### 7.4 Módulo de Horarios y Calendario Académico

**Funcionalidades**:
- Planilla de horarios por carrera/semestre.
- Vista calendario (FullCalendar.js local) con fechas de exámenes, entregas, feriados.
- Conflictos de horarios automáticamente detectados.

**Complejidad**: Media | **Impacto**: Medio

---

### 7.5 Módulo de Comunicación Interna

**Funcionalidades**:
- Mensajería interna (no email) entre docentes, estudiantes y admin.
- Anuncios por carrera/curso/global.
- Notificaciones push en el navegador (Web Notifications API).

**Complejidad**: Media | **Impacto**: Alto

---

### 7.6 Portal de Estudiante (Self-Service)

**Justificación**: Los estudiantes actualmente no tienen acceso al sistema. Sus datos se sincronizan pero no pueden consultarlos.

**Funcionalidades**:
- Login con credenciales Moodle (auth delegado).
- Consulta de calificaciones históricas.
- Consulta de situación académica (materias aprobadas, pendientes, créditos).
- Descarga de certificado de Regular/Alumno.
- Solicitudes administrativas (constancias, certificados, trámites).

**Complejidad**: Alta | **Impacto**: Transformacional

---

### 7.7 API REST para Integraciones

**Justificación**: Actualmente todo es server-rendered. Una API permitiría:
- App móvil futura
- Integraciones con otros sistemas (SGA, tesorería)
- Dashboard externo con BI tools

**Propuesta técnica**:
- Crear namespace `App\Controllers\Api\` con `ApiBaseController`.
- Autenticación JWT (library: `firebase/php-jwt`).
- Rate limiting por token.
- Versionamiento: `/api/v1/...`

**Complejidad**: Media | **Impacto**: Alto (a futuro)

---

## 8. 📋 PLAN DE ACCIÓN PRIORIZADO

### Fase 1: Correcciones Críticas (Semana 1-2)
| # | Tarea | Tipo | Riesgo |
|---|-------|------|--------|
| 1 | Eliminar soporte MD5/SHA1 y forzar reset de passwords legacy | Seguridad | 🔴 |
| 2 | Crear tabla `periodos_academicos` y migrar datos | BD | 🔴 |
| 3 | Crear tabla `asistencias` normalizada | BD | 🔴 |
| 4 | Crear tabla `notificaciones` persistente | BD | 🔴 |
| 5 | Crear tabla `documentos` para tracking de uploads | BD | 🟠 |
| 6 | Crear tabla `migrations` de control | BD | 🟠 |
| 7 | Agregar `whereIn()`, `whereBetween()` al QueryBuilder | Core | 🟠 |

### Fase 2: Mejoras Arquitectónicas (Semana 3-4)
| # | Tarea | Tipo |
|---|-------|------|
| 8 | Cachear auto-resoluciones en Container como singletons | Core |
| 9 | Extraer admin bypass de permisos y usar solo RBAC dinámico | Seguridad |
| 10 | Mover Webhook Moodle fuera del auth group con token API | Seguridad |
| 11 | Implementar `whereIn()` y clonable QueryBuilder | Core |
| 12 | Agregar índices faltantes a `calificaciones` | BD |
| 13 | Unificar acceso a sesión vía SessionHelper (eliminar `$_SESSION` directo) | Seguridad |

### Fase 3: Nuevas Funcionalidades (Semana 5-8)
| # | Tarea | Módulo |
|---|-------|--------|
| 14 | Dashboard personalizado por rol | Frontend |
| 15 | Módulo de Reportes Académicos (rendimiento por carrera) | Backend + Frontend |
| 16 | Sistema de Alertas Tempranas (SAT) | Backend + Frontend |
| 17 | Portal de Estudiante básico (consulta de calificaciones) | Full Stack |

### Fase 4: Escalabilidad (Semana 9-12)
| # | Tarea | Módulo |
|---|-------|--------|
| 18 | Instalar PHPUnit y crear suite de tests esenciales | Testing |
| 19 | API REST v1 con JWT | Backend |
| 20 | Bundling/minificación de assets CSS/JS | DevOps |
| 21 | Módulo de comunicación interna | Full Stack |

---

## 9. 📊 MÉTRICAS DEL CODEBASE AUDITADO

| Métrica | Valor |
|---------|-------|
| **Controladores** | 21 |
| **Modelos** | 9 (+ subdirectorios) |
| **Servicios** | 27 (incluyendo `Sync/`) |
| **Helpers** | 14 |
| **Vistas** | 37+ archivos en 15 secciones |
| **Archivos JS** | 16 módulos + libraries |
| **Archivos CSS** | 88 (incluyendo libraries) |
| **Tablas BD** | ~15 (producción) |
| **Migraciones pendientes/dispersas** | 13 archivos SQL |
| **Líneas PHP estimadas** | ~15,000+ |
| **Archivo más grande** | `MoodleSyncOptimizedService.php` (1149 líneas / 50KB) |
| **Tests automatizados** | 0 (solo scripts de diagnóstico) |
| **Rutas registradas** | ~50 (GET + POST) |

---

## 10. CONCLUSIÓN

EDUMA V2 es un sistema funcional con **una base arquitectónica MVC sólida** y un **motor de sincronización Moodle de nivel profesional**. Sin embargo, tiene **deuda técnica acumulada** principalmente en:

1. **Base de datos**: Tablas faltantes para funcionalidades existentes (asistencia, notificaciones, documentos) y tablas de soporte que no existen (períodos académicos, control de migraciones).
2. **Seguridad**: El bypass de admin hardcodeado y los passwords legacy son los riesgos más urgentes.
3. **Testing**: La ausencia total de tests automatizados es un riesgo operacional significativo.
4. **Explotación de datos**: El sistema tiene **millones de registros de calificaciones** sincronizados pero **cero herramientas de análisis** para la toma de decisiones académicas.

La prioridad inmediata debe ser **estabilizar la base de datos** (Fase 1), seguida de **dashboards personalizados y reportes** (Fase 3) que darán valor visible a los usuarios finales.

---

*Documento generado por Auditoría Técnica Automatizada — EDUMA V2 — 2026-02-23*
