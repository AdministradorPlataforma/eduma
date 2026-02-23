---
name: EDUMA_MOODLE_SYNC
description: Guía técnica para el proceso de sincronización Moodle-EDUMA (V3.3.1 - Optimizado)
---

# Lógica de Sincronización - EDUMA V3.3.1 (Optimizada)

Este documento describe la integración optimizada de datos con Moodle para volúmenes grandes (22K+ usuarios, 10K+ cursos, 5K+ cohortes).

## 0. Reglas Críticas de Desarrollo
- **Resolución de Dependencias**: Al crear servicios que utilicen `PDO` u otras clases nativas, DEBEN importarse explícitamente (`use PDO;`) para que el `Container` de EDUMA pueda resolverlas correctamente a través de Reflection.
- **Circuit Breaker**: Siempre consultar el estado del circuito antes de realizar llamadas masivas para evitar saturación de red en caso de falla del backend Moodle.


## 1. Arquitectura de Sincronización v3.0

### 1.1 Componentes Principales

```
modules/Moodle/
├── MoodleClient.php          # Cliente HTTP estándar con circuit breaker
├── MoodleParallelClient.php  # ⭐ NUEVO: Cliente con curl_multi (paralelo)

app/Services/
├── MoodleSyncService.php         # Servicio legacy (compatible)
├── MoodleSyncOptimizedService.php # ⭐ NUEVO: Servicio optimizado
├── BulkDatabaseService.php       # ⭐ NUEVO: Operaciones masivas BD
├── SyncStateService.php          # Estado en archivo (legacy)
├── SyncStateDbService.php        # ⭐ NUEVO: Estado en BD

app/Jobs/
├── SyncMoodleDataJob.php         # Job legacy
├── SyncMoodleOptimizedJob.php    # ⭐ NUEVO: Job optimizado
├── BatchDispatcherJob.php        # Dispatcher de batches
├── SyncEntityBatchJob.php        # Worker por entidad

scripts/
├── run_sync.php              # ⭐ NUEVO: Ejecutor directo CLI
├── sync_worker.php           # ⭐ NUEVO: Worker de cola multi-proceso
```

### 1.2 Mejoras de Rendimiento

| Aspecto | v2.0 (Anterior) | v3.0 (Nuevo) |
|---------|-----------------|--------------|
| Requests HTTP | Secuenciales | Paralelos (curl_multi, 10 simultáneos) |
| INSERT/UPDATE | Individuales | Bulk (500 registros/operación) |
| Algoritmo usuarios | O(n²) - Itera cursos | O(n) - Directo |
| Estado | Archivo JSON | Base de datos |
| Workers | 1 proceso | Múltiples paralelos |
| Batch size | 20-30 | 100 |

## 2. Jerarquía de Categorías (Sin cambios)

- **Nivel 1**: Periodo Académico
- **Nivel 2**: Modalidad
- **Nivel 3**: Facultad
- **Nivel 4**: Carrera
- **Nivel 5**: Curso/Año
- **Nivel 6**: Semestre

## 3. Configuración Optimizada

### Archivo: `config/moodle_ws.php`

```php
// Batches optimizados para grandes volúmenes
USER_BATCH_SIZE = 100;      // Aumentado de 20
COURSE_BATCH_SIZE = 100;    // Aumentado de 30
GRADE_BATCH_SIZE = 10;      // Aumentado de 5

// Procesamiento paralelo
PARALLEL_REQUESTS = 10;     // Requests simultáneos
PARALLEL_TIMEOUT = 30;      // Timeout por request

// Bulk INSERT
BULK_INSERT_SIZE = 500;     // Registros por operación
BULK_INSERT_MAX = 1000;     // Máximo

// Sincronización incremental
SYNC_LOOKBACK_HOURS = 24;   // Para modo delta
FULL_SYNC_INTERVAL_DAYS = 7;
```

## 4. Modos de Sincronización

### 4.1 Sincronización Completa (`all`)
```php
$service = new MoodleSyncOptimizedService();
$result = $service->sincronizarTodo(force: false);
```

Fases ejecutadas:
1. Categorías (5%)
2. Cursos (15-25%)
3. Usuarios (30-70%)
4. Matrículas (70-85%)
5. Cohortes (85-100%)

### 4.2 Sincronización Delta
Solo cambios de las últimas 24 horas:
```php
$result = $service->sincronizarDelta();
```

### 4.3 Por Entidad
```php
$service->sincronizarCategorias();
$service->sincronizarCursosOptimizado();
$service->sincronizarUsuariosOptimizado(force: true);
$service->sincronizarMatriculasOptimizado();
$service->sincronizarCohortesOptimizado();
$service->sincronizarCalificacionesOptimizado($courseIds);
```

## 5. Uso desde CLI

### Sincronización Directa
```bash
# Sync completo
php scripts/run_sync.php all

# Solo usuarios
php scripts/run_sync.php users

# Solo delta (cambios recientes)
php scripts/run_sync.php delta

# Forzar re-sincronización
php scripts/run_sync.php users --force
```

### Worker de Cola
```bash
# Ejecutar un job
php scripts/sync_worker.php

# Modo daemon (continuo)
php scripts/sync_worker.php --daemon

# Múltiples workers
php scripts/sync_worker.php --daemon --worker-id=W1 &
php scripts/sync_worker.php --daemon --worker-id=W2 &
```

## 6. API Endpoints

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/moodle` | Vista principal |
| GET | `/moodle/health` | Health check |
| POST | `/moodle/sync/{entity}` | Sync entidad específica |
| POST | `/moodle/async/start` | Iniciar sync completo |
| POST | `/moodle/async/stop` | Detener sync |
| GET | `/moodle/async/status` | Estado actual |
| POST | `/moodle/reset-circuit` | Resetear circuit breaker |

### Parámetros de `/moodle/async/start`
```javascript
{
  "csrf_token": "...",
  "optimized": true,  // Usar nuevo sistema (default: true)
  "type": "all",      // all, delta, users, courses, etc.
  "force": false,     // Forzar re-sync completo
  "regenerate_passwords": false // Forzar regeneración de passwords (o creación si faltan)
}
```

## 7. Tablas de Base de Datos

### Nuevas tablas v3.0:
- `sync_batches` - Tracking de ejecuciones
- `sync_metrics` - Métricas de rendimiento
- `sync_logs` - Logs detallados
- `sync_status` - Estado por entidad

### Índices optimizados:
- `idx_usuarios_sync` (id_moodle, data_hash, last_sync_at)
- `idx_cursos_sync_visible` (visible, id_moodle)
- `idx_matriculas_lookup` (curso_id, usuario_id)
- `idx_calificaciones_upsert` (matricula_id, id_moodle_item)

## 8. Diagnóstico y Monitoreo

### Vista de Dashboard
```sql
SELECT * FROM v_sync_dashboard;
```

### Logs Recientes
```sql
SELECT * FROM sync_logs 
WHERE created_at >= NOW() - INTERVAL 1 HOUR
ORDER BY created_at DESC;
```

### Limpieza Programada
```sql
CALL cleanup_sync_data(30); -- Mantener 30 días
```

## 9. Estimaciones de Tiempo

Con la configuración optimizada:

| Entidad | Cantidad | Tiempo Estimado |
|---------|----------|-----------------|
| Categorías | ~500 | < 10 segundos |
| Cursos | 10,000 | ~ 2-3 minutos |
| Usuarios | 22,000 | ~ 5-8 minutos |
| Matrículas | ~100,000 | ~ 10-15 minutos |
| **Total** | - | **~20-30 minutos** |

(vs. 2-4 horas con sistema anterior)

## 10. Mejoras v3.1 (2026-02-06)

### Nuevas características

| Mejora | Descripción | Impacto |
|--------|-------------|---------|
| Circuit Breaker Paralelo | El `MoodleParallelClient` ahora comparte el circuit breaker con `MoodleClient` | Evita 7000+ requests inútiles si Moodle está caído |
| Early-Stop en fallas | Si >50% de un chunk falla, se aborta el proceso | Ahorra tiempo y recursos |
| Reintentos Selectivos | Solo se reintentan requests con errores de conexión (no API) | Recupera datos perdidos |
| Logging Agrupado | Errores similares se agrupan en una sola línea de log | Reduce spam en logs |
| Backoff Exponencial | Tiempo creciente entre reintentos | Reduce carga en Moodle caído |
| Columna `rol` | Nueva columna en `curso_matriculas` para distinguir estudiantes/docentes | Permite filtrar matrículas por rol |

### Nuevas tablas

- `sync_metrics`: Métricas detalladas por fase de sincronización
- `sync_error_summary`: Resumen agrupado de errores

### Comandos de verificación

```bash
# Verificar estado de mejoras
php scripts/verify_sync_improvements.php

# Ejecutar migración (si no se ha aplicado)
php scripts/run_migration.php
```

## 11. Troubleshooting

### "Circuit breaker abierto"
```bash
# Esperar 60 segundos o resetear manualmente
POST /moodle/reset-circuit
```

### Memoria agotada
```php
// Aumentar en php.ini o script
ini_set('memory_limit', '1G');
```

### Jobs atascados
```sql
-- Liberar jobs abandonados
UPDATE queue_jobs 
SET status = 'pending', reserved_at = NULL 
WHERE status = 'running' 
AND reserved_at < NOW() - INTERVAL 30 MINUTE;
```

### Early-stop activado
Si ves "Early-stop activado" en los logs, significa que hubo demasiados errores consecutivos:
1. Verificar conectividad con Moodle (`/moodle/health`)
2. Revisar `sync_error_summary` para ver el tipo de error
3. Corregir el problema y reiniciar la sincronización

### Errores de columna missing
Si ves "Unknown column 'rol' in 'field list'":
```bash
# Ejecutar migración
php scripts/run_migration.php
```

## 12. Mejoras de Seguridad v3.2 (2026-02-06)

### Auditoría de Calificaciones

| Mejora | Descripción | Impacto |
|--------|-------------|---------|
| Validación robusta | Tipos, rangos y longitudes validadas | Previene datos corruptos/maliciosos |
| Sanitización XSS | `sanitizeGradeFeedback()` elimina scripts | Previene XSS almacenado |
| Hash de integridad | Columna `data_hash` con SHA-256 | Detecta manipulación de BD |
| Tabla de auditoría | `audit_calificaciones` con trigger | Trazabilidad académica |
| SSL forzado en prod | `shouldVerifySSL()` ignora config | Previene MITM |
| Rate limiting | Límite de 2000 pares en grades | Previene DoS |

### Migración requerida
```bash
# Ejecutar migración de seguridad
mysql -u root eduma_prueba_2 < database/migrations/2026_02_06_security_grades_integrity.sql
```

### Verificar integridad de calificaciones
```sql
-- Ver estado de integridad
CALL verificar_integridad_calificaciones();

-- Ver calificaciones con problemas
SELECT * FROM v_calificaciones_integridad WHERE estado_integridad != 'ok';
```

### Configuración requerida en .env
```env
# Salt para hash de integridad (CAMBIAR en producción)
APP_SECRET=tu_secreto_seguro_aqui

# SSL obligatorio en producción (no puede deshabilitarse)
APP_ENV=production
```

## 13. Mejoras de Sincronización v3.3 (2026-02-06)

### Nuevos Servicios

| Archivo | Descripción | Uso Principal |
|---------|-------------|---------------|
| `UserProfileService.php` | Gestión de perfiles estudiantes/docentes | Crea registros en `estudiantes`/`docentes` automáticamente |
| `SyncCleanupService.php` | Limpieza de entidades huérfanas | Detecta y suspende usuarios/cursos eliminados en Moodle |

### Arquitectura Actualizada

```
app/Services/
├── MoodleSyncOptimizedService.php  # v3.3: +2 nuevas fases
├── BulkDatabaseService.php         # v3.3: Hash comparison en categorías
├── UserProfileService.php          # ⭐ NUEVO: Perfiles automáticos
├── SyncCleanupService.php          # ⭐ NUEVO: Soft delete huérfanos
```

### Flujo de Sincronización Actualizado

```
FASE 1 (5%)   → Categorías (con hash detection)
FASE 2 (15%)  → Cursos bulk
FASE 3 (30%)  → Usuarios bulk
FASE 4 (70%)  → Matrículas paralelas + actualización de flags
FASE 4.5 (83%)→ ⭐ NUEVO: Crear perfiles estudiantes/docentes
FASE 5 (88%)  → Cohortes
FASE 6 (95%)  → ⭐ NUEVO: Verificar entidades huérfanas
```

### API de Limpieza (Nuevos Endpoints)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/moodle/cleanup/summary` | Resumen de entidades huérfanas |
| `POST` | `/moodle/cleanup/execute` | Ejecutar limpieza completa |
| `POST` | `/moodle/cleanup/enrollments` | Limpiar solo matrículas huérfanas |
| `POST` | `/moodle/cleanup/reactivate-user` | Reactivar usuario suspendido |
| `POST` | `/moodle/cleanup/reactivate-course` | Reactivar curso oculto |
| `GET` | `/moodle/cleanup/orphan-users` | Listar usuarios suspendidos |
| `GET` | `/moodle/cleanup/orphan-courses` | Listar cursos ocultos |

### Estrategia de Soft Delete

| Entidad | Campo de Estado | Valor Activo | Valor Inactivo |
|---------|-----------------|--------------|----------------|
| Usuarios | `suspended` | `0` | `1` |
| Cursos | `visible` | `1` | `0` |
| Matrículas | `estado` | `'activo'` | `'suspendido'` |

### Creación Automática de Perfiles

Cuando un usuario se matricula por primera vez:
- Si `rol = 'student'` → Crea registro en `estudiantes`
- Si `rol = 'editingteacher'/'teacher'/'manager'` → Crea registro en `docentes`

```php
// Uso individual
$profileService = new UserProfileService();
$profileService->crearPerfilesParaUsuario($userId, ['student']);

// Uso masivo (después de matrículas)
$profileService->sincronizarPerfilesDesdeMatriculas();
```

### Uso de Limpieza

```php
$cleanupService = new SyncCleanupService();

// Obtener resumen sin ejecutar cambios
$summary = $cleanupService->obtenerResumenHuerfanos();

// Ejecutar limpieza completa
$result = $cleanupService->ejecutarLimpiezaCompleta();

// Reactivar entidad específica
$cleanupService->reactivarUsuario($idMoodle);
$cleanupService->reactivarCurso($idMoodle);
```

### Migración Requerida

```bash
# Ejecutar migración de mejoras v3.3
mysql -u root -p eduma < database/migrations/2026_02_06_sync_hash_improvements.sql
```

### Nuevas Vistas SQL

- `v_sync_dashboard`: Muestra totales activos/suspendidos por entidad
- `v_usuarios_perfiles`: Usuarios con información de perfiles extendidos

### Verificación de Integridad

```sql
-- Dashboard de sincronización
SELECT * FROM v_sync_dashboard;

-- Usuarios sin perfil esperado
SELECT u.id, u.username, u.es_estudiante, u.es_docente
FROM usuarios u
LEFT JOIN estudiantes e ON u.id = e.usuario_id
LEFT JOIN docentes d ON u.id = d.usuario_id
WHERE (u.es_estudiante = 1 AND e.id IS NULL)
   OR (u.es_docente = 1 AND d.id IS NULL);
```
