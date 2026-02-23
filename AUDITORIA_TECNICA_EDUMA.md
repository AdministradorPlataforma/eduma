# 🔍 Auditoría Técnica Integral — EDUMA v3.3
**Fecha:** 2026-02-16  
**Auditor:** Arquitecto de Software Senior  
**Alcance:** Backend PHP 7.4, MySQL, MVC, Seguridad, Rendimiento, UX Funcional  

---

## PARTE I: ANÁLISIS CRÍTICO — Problemas y Debilidades Detectadas

---

### 1. 🏗️ Estructura del Proyecto (MVC y Carpetas)

| # | Problema | Severidad | Archivo(s) Afectado(s) |
|---|---------|-----------|------------------------|
| 1.1 | **DashboardModel viola la arquitectura**: Extiende `BaseModel` con `$table = 'cursos'` como placeholder, pero ejecuta queries directas multi-tabla. Debería ser un **Repository** o **Service**, no un Model. | Media | `app/Models/DashboardModel.php` |
| 1.2 | **Repositorios infrautilizados**: Solo existe `app/Repositories/Investigacion/`. El patrón Repository está documentado en los skills pero no se usa consistentemente. Modelos como `DashboardModel` y `UsuarioController.datatable()` hacen SQL directo. | Media | `app/Repositories/` (casi vacío) |
| 1.3 | **`Constants.php` define rutas con lowercase que no coinciden**: `VIEW_PATH` apunta a `'views'` en minúscula, pero la carpeta real es `Views` (PascalCase). Esto puede fallar silenciosamente en Linux. | Alta | `config/Constants.php:64-66` |
| 1.4 | **Módulo `Gestion` tiene sub-carpeta dentro de Models pero no en Repositories**: `app/Models/Gestion/GestionModel.php` existe, pero no hay `app/Repositories/Gestion/GestionRepository.php` registrado en el tree (aunque la skill lo menciona). Inconsistencia entre documentación y realidad. | Baja | `app/Repositories/` |
| 1.5 | **Controlador `MoodleController` es un "God Controller"**: 758 líneas con 21 métodos. Mezcla lógica de sincronización, cleanup, health check y streaming SSE. Debería dividirse en sub-controladores. | Alta | `app/Controllers/MoodleController.php` |
| 1.6 | **Carpeta `app/Commands/`**: Existe con 1 archivo, pero no hay un sistema de CLI Commands formal. Los scripts en `/scripts/` actúan independiente. | Baja | `app/Commands/` |
| 1.7 | **DTOs, ViewModels**: Carpetas con 1 archivo cada una. Patrón iniciado pero no adoptado en el resto del sistema. | Baja | `app/DTOs/`, `app/ViewModels/` |

---

### 2. 🔐 Seguridad

| # | Problema | Severidad | Detalle |
|---|---------|-----------|---------|
| 2.1 | **⚠️ CRÍTICO: Comparación de contraseña en texto plano** | **CRÍTICA** | `AuthService.php:42` — `if ($password === $user['password'])` permite login con contraseñas almacenadas sin hashear. Esto es una migración legacy pero **no debería estar en producción indefinidamente**. Si la base de datos se filtra, todas las contraseñas legacy son visibles. |
| 2.2 | **⚠️ CRÍTICO: `.env` expuesto en el mismo directorio accesible** | **CRÍTICA** | El `.env` contiene `DB_PASSWORD=UMA2025`, `MOODLE_TOKEN=5e3187...`, `APP_SECRET=eduma_PROD_secret_96ce5efc076b`. Aunque `.htaccess` redirige a `/public/`, un misconfigure podría exponer estos secretos. No hay `.env.example` sin credenciales reales. |
| 2.3 | **`APP_DEBUG=true` en producción** | **ALTA** | `.env:10` tiene `APP_DEBUG=true` con `APP_ENV=production`. Esto expone stack traces, rutas del servidor y detalles de BD en errores. Línea `index.php:34-40` muestra errores detallados basándose en este flag. |
| 2.4 | **Rate Limiting basado en SESIÓN** — Ineficaz | Alta | `RateLimitHelper.php` almacena intentos en `$_SESSION`. Un atacante simplemente no envía la cookie de sesión y obtiene sesiones nuevas ilimitadas. Debe ser almacenamiento en BD o filesystem basado en IP real. |
| 2.5 | **CSRF token no rota después del login** | Media | `CSRFHelper.php` genera un token por sesión y lo reutiliza. Después del login exitoso, el token debería regenerarse para evitar ataques de session fixation combinados. |
| 2.6 | **Permisos hardcoded en `AuthService.loginUser()`** | Alta | Líneas 126-132: Permisos del admin se hardcodean en un array literal. Si se agrega un nuevo módulo, hay que editar este archivo. Debería ser 100% dinámico desde la BD. |
| 2.7 | **`session.cookie_strict` no existe** | Baja | `index.php:60` — `ini_set('session.cookie_strict', '1')` — Esta directiva no existe en PHP. Debería ser `session.cookie_samesite`. |
| 2.8 | **No hay protección contra Session Fixation activa** | Media | No se llama `session_regenerate_id()` después del login exitoso ni se destruye la sesión antigua. |
| 2.9 | **Ruta `/setup/rbac` accesible sin autenticación** | Alta | `routes/web.php:33-36` — Cualquier persona puede acceder a `/setup/rbac` y ejecutar `RbacSetupService::runSetup()`, que potencialmente modifica roles y permisos. Sin middleware de protección. |
| 2.10 | **Papelera de Reciclaje sin permisos** | Media | `routes/web.php:114-118` — Las rutas de `recycle-bin` solo requieren `auth`, no permiso específico. Cualquier usuario logueado podría restaurar o purgar registros. |
| 2.11 | **`/sistema` sin permisos** | Media | `routes/web.php:110-111` — El módulo de Sistema y su Janitor solo requieren `auth`. |
| 2.12 | **Bypass de middlewares en rutas con parámetros dinámicos** | Alta | `Router.php:78-98` — El dispatch primero intenta match exacto sin ejecutar middlewares del patrón dinámico. Luego intenta `preg_match` pero **no ejecuta todo el middleware stack** del grupo porque solo resuelve la ruta  individual. Sin embargo, al revisar el código otra vez, sí ejecuta middlewares en la línea 95-97 para el patrón dinámico. Este riesgo es menor de lo esperado. |

---

### 3. 🗄️ Base de Datos MySQL

| # | Problema | Severidad | Detalle |
|---|---------|-----------|---------|
| 3.1 | **Falta de índice en `usuarios.username`**: La columna tiene `UNIQUE` constraint pero no hay índice explícito (MySQL lo crea implícitamente, pero para búsquedas parciales con `LIKE`, un fulltext sería mejor). | Baja | `modelo_bd.sql:93` |
| 3.2 | **Tabla `calificaciones` sin índice FK directo en `matricula_id`**: Solo tiene `UNIQUE KEY uk_calificacion_item (matricula_id, id_moodle_item)`, que cubre queries con ambas columnas pero no optimiza `WHERE matricula_id = X` aislado al 100%. | Baja | `modelo_bd.sql:236` |
| 3.3 | **`audit_logs.details` usa tipo JSON**: MySQL 5.6 no soporta JSON nativo (requiere 5.7+). Si el sistema corre en MySQL 5.6 o MariaDB < 10.2, esto falla. | Media | `modelo_bd.sql:352` |
| 3.4 | **No existe tabla `notificaciones`**: El sistema tiene `NotificationService` y `NotificationController`, pero no hay tabla de notificaciones en el modelo de BD. Las notificaciones probablemente se generan en memoria o cache, sin persistencia. | Alta | No existe |
| 3.5 | **No existe tabla `sessions`**: `DatabaseSessionHandler.php` existe pero la tabla `sessions` no está en `modelo_bd.sql`. Hay una migración separada `2026_02_09_create_sessions_table.sql`, pero no está integrada al esquema principal. | Media | `bd/` |
| 3.6 | **Tabla `gestion_actividades_maestras` referenciada pero no definida**: `DashboardModel:180` ejecuta `SELECT * FROM gestion_actividades_maestras` pero esta tabla no existe en el esquema SQL. | Alta | `DashboardModel.php:180` |
| 3.7 | **Sin migraciones automatizadas**: Los archivos SQL en `/bd/` son scripts manuales sin control de versiones de esquema. No hay un sistema de migraciones ordenado (ej: timestamps + tabla `migrations`). | Media | `bd/` |
| 3.8 | **`FOREIGN_KEY_CHECKS = 0` al inicio y `COMMIT` al final sin `BEGIN`**: El esquema SQL hace `COMMIT` sin transacción explícita previa. | Baja | `modelo_bd.sql:364-365` |
| 3.9 | **Falta índice compuesto en `curso_matriculas`** para queries de rendimiento: `(usuario_id, estado)` y `(curso_id, rol_moodle, estado)` serían beneficiosos para consultas del dashboard. | Media | `modelo_bd.sql:195` |
| 3.10 | **No hay tabla `password_resets`**: No existe flujo de "olvidé mi contraseña" ni tabla temporal para tokens de reseteo. | Alta | No existe |

---

### 4. ⚙️ Código PHP (Controladores, Servicios, Modelos)

| # | Problema | Severidad | Detalle |
|---|---------|-----------|---------|
| 4.1 | **`AuthController` tiene método `logInUser()` muerto**: Líneas 98-118 definen un método privado que **nunca se usa** (la lógica real está en `AuthService::loginUser()`). Código muerto. | Baja | `AuthController.php:98-118` |
| 4.2 | **`BaseModel.filterData()` pasa todo si `allowedFields` está vacío**: Línea 240-241. Si un modelo olvida definir `$allowedFields`, **cualquier campo** puede ser inyectado. Debería lanzar excepción o usar lista blanca estricta. | Alta | `BaseModel.php:240` |
| 4.3 | **`QueryBuilder.count()` resetea el estado**: Como está documentado en comentarios del `BaseModel::paginate()`, el `count()` destruye las condiciones WHERE. Esto obliga a duplicar condiciones (líneas 71-96 del BaseModel). | Media | `QueryBuilder.php:203-212` |
| 4.4 | **`DashboardModel::getProximosVencimientos()` usa SQL sin preparar**: `$this->db->query("... LIMIT $limit")` — Aunque `$limit` es un int del parámetro tipado, es inconsistente con el estándar de prepared statements. | Baja | `DashboardModel.php:180` |
| 4.5 | **Container no cachea instancias auto-resueltas**: `Container::get()` con auto-resolución (Reflection) no guarda la instancia en `$this->instances`. Cada `get()` crea una instancia nueva, potencialmente duplicando objetos. | Media | `Container.php:107-109` |
| 4.6 | **Formato `Controller@method` legacy instancia sin Container**: `Router::callAction()` línea 165 — usa `new $controllerClass()` sin inyección de dependencias. Si alguna ruta usa este formato, el controlador no recibirá sus dependencias. | Media | `Router.php:165` |
| 4.7 | **`ExportarExcelController.php` casi vacío** (233 bytes): Controlador placeholder sin implementación real. | Baja | `app/Controllers/ExportarExcelController.php` |
| 4.8 | **`BulkDatabaseService.php` es masivo** (39KB): Un solo archivo con toda la lógica de operaciones masivas. Difícil de mantener y testear. | Media | `app/Services/BulkDatabaseService.php` |

---

### 5. 📊 Rendimiento y Escalabilidad

| # | Problema | Severidad | Detalle |
|---|---------|-----------|---------|
| 5.1 | **Notificaciones se cargan en CADA render**: `BaseController::render()` líneas 175-197 consultan `NotificationService` en cada carga de vista. Para usuarios con alta actividad, esto agrega latencia. El caché de 5 minutos ayuda, pero es per-request (no true cache). | Media | `BaseController.php:175-197` |
| 5.2 | **No hay sistema de caché real**: `CacheHelper` probablemente usa archivos o sesión. Para escalar (más de 100 usuarios concurrentes), se necesita Redis/Memcached o al menos un caché APC. | Media | `app/Helpers/CacheHelper.php` |
| 5.3 | **Conexión a BD se crea 2 veces en `index.php`**: Líneas 64 y 105-107 crean instancias separadas de `Config\Database`. La de la sesión no pasa por el Container. | Baja | `public/index.php:64, 105` |
| 5.4 | **`MoodleSyncOptimizedService` es un monolito de 1149 líneas**: Aunque está bien organizado internamente, al crecer se volverá inmanejable. Debería usar el patrón Strategy o Pipeline para las fases. | Media | `app/Services/MoodleSyncOptimizedService.php` |
| 5.5 | **Sin connection pooling ni persistent connections**: Cada request crea una nueva conexión PDO. En entorno de alta carga, esto puede saturar MySQL. | Baja | `config/Database.php` |

---

### 6. 🖥️ Experiencia de Usuario (UX Funcional)

| # | Problema | Severidad | Detalle |
|---|---------|-----------|---------|
| 6.1 | **No existe "Olvidé mi contraseña"**: No hay flujo de recuperación de contraseña. Los usuarios deben contactar al admin. | Alta | No implementado |
| 6.2 | **Sin búsqueda global accesible**: Existe `SearchController` y `UniversalSearchService`, pero no hay ruta en `web.php` para la búsqueda. | Media | No enrutado |
| 6.3 | **Sin exportación universal**: `ExportarExcelController` está vacío. `ExportService` existe pero solo con lógica parcial. | Media | No funcional |
| 6.4 | **Módulo de Predicción con endpoints API sin vista propia**: `PredictionController::studentScore()` es una API sin interfaz de usuario asociada. | Baja | Parcial |
| 6.5 | **Sin historial de cambios visible para el usuario**: `audit_logs` existe pero solo el admin con permiso `ver_auditoria` puede verla. Los usuarios no pueden ver su propio historial de actividad. | Baja | Diseño actual |

---

## PARTE II: PROPUESTA DE NUEVAS FUNCIONES

---

### A. FUNCIONES FUNCIONALES (Lo que puede hacer el usuario/cliente)

#### A.1 — 🔑 Recuperación de Contraseña Self-Service
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Flujo completo de "Olvidé mi contraseña" con envío de email y token temporal seguro. Incluye vista de solicitud, validación de email, generación de token con expiración (30min), y formulario de nueva contraseña. |
| **Beneficio** | Reduce carga del administrador. Permite a los 22,000+ usuarios recuperar acceso sin intervención humana. |
| **Prioridad** | **🔴 Alta** |
| **Implementación** | 1. Crear tabla `password_resets (email, token_hash, created_at, used_at)` con índice + TTL. 2. `AuthController::showForgotPassword()`, `sendResetLink()`, `showResetForm($token)`, `resetPassword()`. 3. `PasswordResetService` con lógica de negocio. 4. Template de email HTML con el enlace de reseteo. 5. Configurar PHPMailer o similar con SMTP de la institución. |

#### A.2 — 📊 Panel de Analíticas Académicas para Docentes
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Dashboard interactivo donde los docentes ven: estadísticas de calificaciones de sus cursos, distribución de notas (histograma), tasa de aprobación, estudiantes en riesgo (predicción), comparativa entre períodos académicos. Gráficos interactivos con Chart.js. |
| **Beneficio** | Transforma datos brutos en información accionable. Permite intervención temprana sobre estudiantes en riesgo. |
| **Prioridad** | **🔴 Alta** |
| **Implementación** | 1. Crear `AnalyticsController` y `AnalyticsService`. 2. Repository con queries optimizadas de calificaciones agrupadas. 3. Vista con glass panels y charts dinámicos (Chart.js local). 4. Endpoint API `/analytics/course/{id}/stats` para datos AJAX. 5. Integrar con `GradePredictionService` existente. |

#### A.3 — 📁 Repositorio de Documentos Académicos
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Sistema para subir, categorizar y compartir documentos académicos (planes de estudio, programas, resoluciones). Organizado por facultad/carrera. Con control de versiones básico (v1, v2...) y búsqueda. |
| **Beneficio** | Centraliza la documentación institucional. Elimina la dispersión en carpetas compartidas o emails. |
| **Prioridad** | **🟡 Media** |
| **Implementación** | 1. Tabla `documentos (id, titulo, descripcion, categoria, facultad_id, carrera_id, version, archivo_path, uploaded_by, created_at)`. 2. `DocumentoController` + `DocumentoService`. 3. Almacenamiento en `/uploads/documentos/` con validación de tipo MIME. 4. Vista con filtros por categoría y búsqueda. |

#### A.4 — 📅 Calendario Académico Interactivo
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Calendario visual con eventos académicos (inicio/fin de cursado, exámenes, tareas de gestión, vencimientos). Integra datos de `gestion_control_seguimiento` y permite crear eventos manuales. |
| **Beneficio** | Vista unificada de todas las fechas clave. Reduce tareas vencidas por falta de visibilidad. |
| **Prioridad** | **🟡 Media** |
| **Implementación** | 1. Librería FullCalendar.js (local). 2. Tabla `eventos_academicos (id, titulo, fecha_inicio, fecha_fin, tipo, facultad_id, color, creado_por)`. 3. API endpoints para CRUD JSON. 4. Integración automática con tareas de gestión existentes. |

#### A.5 — 💬 Sistema de Mensajería Interna
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Mensajería entre usuarios del sistema (admin → docente, secretaria → estudiante). Inbox con mensajes leídos/no leídos, respuestas en hilo, y opción de mensaje masivo por facultad/carrera. |
| **Beneficio** | Canal de comunicación oficial interno. Reduce dependencia de email externo y WhatsApp. |
| **Prioridad** | **🟡 Media** |
| **Implementación** | 1. Tablas `mensajes (id, remitente_id, asunto, cuerpo, tipo, created_at)` + `mensaje_destinatarios (mensaje_id, usuario_id, leido_at)`. 2. `MensajeController/Service`. 3. Integrar con `NotificationService` existente. |

---

### B. FUNCIONES ADMINISTRATIVAS

#### B.1 — 📋 Dashboard Administrativo de KPIs en Tiempo Real
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Panel ejecutivo con: tasa de retención estudiantil, tasa de graduación por carrera, docentes activos vs. inactivos, carga horaria, estadísticas de uso del sistema (logins/día), y métricas de sincronización Moodle. Todo con widgets actualizables vía AJAX. |
| **Beneficio** | Visibilidad operativa para la alta dirección. Toma de decisiones basada en datos. |
| **Prioridad** | **🔴 Alta** |
| **Implementación** | 1. `AdminDashboardService` con queries agregadas optimizadas. 2. Vistas SQL materializadas (`CREATE VIEW v_kpi_retencion`, `v_kpi_graduacion`). 3. Charts con datasets múltiples. 4. Caché de KPIs con TTL de 1 hora. |

#### B.2 — 🔄 Gestión de Migraciones de Base de Datos
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Sistema formal de migraciones: tabla `migrations` que trackea qué scripts SQL se ejecutaron. Comando CLI `php bin/migrate.php` que ejecuta migraciones pendientes en orden. Comando `rollback` para revertir. |
| **Beneficio** | Elimina errores por scripts SQL duplicados o fuera de orden. Esencial para despliegues confiables. |
| **Prioridad** | **🔴 Alta** |
| **Implementación** | 1. Tabla `migrations (id, filename, batch, executed_at)`. 2. Refactorear `MigrationRunner.php` existente en Core/Database. 3. Directorio unificado `bd/migrations/` con naming `YYYY_MM_DD_HHMMSS_descripcion.sql`. 4. Script CLI en `bin/migrate.php`. |

#### B.3 — 📝 Log de Auditoría Avanzado con Explorer
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Mejorar el `AuditController` existente con: filtros avanzados (por usuario, acción, fecha, recurso), exportación a Excel/CSV, timeline visual por usuario, y detección de patrones anómalos (ej: muchos intentos de acceso denegado). |
| **Beneficio** | Cumplimiento normativo. Detección proactiva de amenazas. |
| **Prioridad** | **🟡 Media** |
| **Implementación** | 1. Mejorar vista existente con DataTables server-side (ya usado en Usuarios). 2. Agregar filtros combinados. 3. Endpoint de exportación con `ExportService`. 4. Widget de "Alertas de Seguridad" en el Dashboard. |

#### B.4 — 👥 Gestión Avanzada de Perfiles (Estudiante/Docente/Admin)
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | CRUD completo para perfiles extendidos: editar datos de estudiantes (legajo, carrera, año ingreso), docentes (título, especialidad, contrato), y administrativos (cargo, departamento). Con vista de vinculación visual entre usuario y sus perfiles. |
| **Beneficio** | Datos de perfil actualmente incompletos o solo editables vía SQL. |
| **Prioridad** | **🟡 Media** |
| **Implementación** | 1. Tabs en la vista de edición de usuario. 2. Sub-formularios para cada perfil. 3. `PerfilService` con update parcial por tipo. |

#### B.5 — 📊 Exportación Universal Multi-formato
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Completar `ExportService` para que cualquier listado del sistema (usuarios, cursos, calificaciones, auditoría) pueda exportarse a Excel, CSV y PDF con un botón estándar. |
| **Beneficio** | Los usuarios actualmente no pueden extraer datos del sistema fácilmente. |
| **Prioridad** | **🟡 Media** |
| **Implementación** | 1. Completar `ExportarExcelController`. 2. Usar PhpSpreadsheet (ya disponible como dependencia potencial). 3. Trait `Exportable` para servicios. 4. Botones de exportación estándar en todas las vistas de listado. |

---

### C. FUNCIONES DE SEGURIDAD

#### C.1 — 🔒 Rate Limiting Persistente (BD/Filesystem)
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Reemplazar el Rate Limiting basado en sesión por almacenamiento en tabla `rate_limits (ip, key, attempts, blocked_until, created_at)`. Inmune a manipulación de cookies. |
| **Beneficio** | **Cierra la vulnerabilidad actual** donde basta con eliminar la cookie de sesión para evadir el bloqueo. |
| **Prioridad** | **🔴 Alta** |
| **Implementación** | 1. Crear tabla `rate_limits`. 2. Refactorizar `RateLimitHelper` para usar PDO. 3. Job de limpieza periódica de registros antiguos (integrar con `JanitorService`). |

#### C.2 — 🛡️ Regeneración de Session ID Post-Login
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Llamar a `session_regenerate_id(true)` inmediatamente después de la autenticación exitosa para prevenir Session Fixation. |
| **Beneficio** | Cierra vector de ataque de fixación de sesión. Implementación trivial. |
| **Prioridad** | **🔴 Alta** |
| **Implementación** | Agregar `session_regenerate_id(true);` en `AuthService::loginUser()` antes de establecer variables de sesión. ~3 líneas de código. |

#### C.3 — 🔐 Eliminación de Contraseñas Legacy en Texto Plano
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Script de migración forzada: hashear todas las contraseñas que aún están en texto plano en la BD. Luego eliminar la comparación directa `$password === $user['password']` del `AuthService`. |
| **Beneficio** | **Elimina la vulnerabilidad más crítica del sistema.** Si la BD se filtra, las contraseñas legacy son visibles en claro. |
| **Prioridad** | **🔴 Alta** |
| **Implementación** | 1. Script CLI `/scripts/migrate_passwords.php` que itere usuarios y hashee el campo `password` si no empieza con `$2y$`. 2. Remover la rama `if ($password === $user['password'])` del `AuthService::authenticate()`. 3. Forzar cambio de contraseña en el próximo login para usuarios migrados. |

#### C.4 — 🔏 2FA (Autenticación de Dos Factores) para Admins
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | TOTP (Google Authenticator compatible) obligatorio para usuarios con `es_admin = 1`. Flujo: login normal → pantalla de código TOTP → acceso. |
| **Beneficio** | Protección adicional para cuentas privilegiadas. Estándar en sistemas institucionales modernos. |
| **Prioridad** | **🟡 Media** |
| **Implementación** | 1. Tabla `user_2fa (usuario_id, secret_key, enabled, recovery_codes, created_at)`. 2. Librería `OTPHP/OTPHP` o similar. 3. Flujo de setup con QR code. 4. Middleware `TwoFactorMiddleware`. |

#### C.5 — 📝 Política de Contraseñas Configurable
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Agregar: expiración de contraseñas (cada 90 días), historial (no reusar las últimas 5), caracteres especiales obligatorios, longitud mínima configurable. |
| **Beneficio** | Cumplimiento con políticas de seguridad institucional. |
| **Prioridad** | **🟡 Media** |
| **Implementación** | 1. Tabla `password_history (usuario_id, password_hash, created_at)`. 2. Columna `password_changed_at` en `usuarios`. 3. Middleware que verifique expiración en cada request autenticado. |

#### C.6 — 🚫 Proteger Rutas Administrativas Expuestas
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Agregar middleware `permission:admin_sistema` a rutas `/sistema`, `/recycle-bin` y eliminar/proteger `/setup/rbac`. |
| **Beneficio** | Cierra vectores de acceso no autorizado a funciones administrativas críticas. |
| **Prioridad** | **🔴 Alta** |
| **Implementación** | Modificar `routes/web.php`: envolver las rutas dentro de grupos con middleware de permiso. Eliminar o proteger la ruta `/setup/rbac` detrás de un flag de entorno. ~10 líneas. |

---

### D. FUNCIONES DE AUTOMATIZACIÓN

#### D.1 — ⏰ Scheduler de Tareas (Cron Jobs)
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Sistema de tareas programadas: sincronización Moodle automática (cada 6h), limpieza de logs antiguos (semanal), verificación de integridad de calificaciones (diaria), envío de alertas de gestión (diario). |
| **Beneficio** | Elimina dependencia de ejecución manual. Garantiza datos actualizados sin intervención humana. |
| **Prioridad** | **🔴 Alta** |
| **Implementación** | 1. Tabla `scheduled_tasks (id, name, command, cron_expression, last_run, next_run, enabled)`. 2. Script `bin/scheduler.php` ejecutado por cron del SO cada minuto. 3. Cada tarea hereda de `AbstractScheduledTask`. 4. UI en `/sistema` para ver/configurar tareas. |

#### D.2 — 📧 Sistema de Notificaciones por Email
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Envío automático de emails para: reseteo de contraseña, alertas de gestión (tarea por vencer), resumen semanal de actividad para decanos, notificación de sincronización completada/fallida, alerta de seguridad (login desde nueva IP). |
| **Beneficio** | Comunicación proactiva. Los usuarios no necesitan entrar al sistema para enterarse de eventos críticos. |
| **Prioridad** | **🟡 Media** |
| **Implementación** | 1. `EmailService` con PHPMailer. 2. Templates HTML en `app/Views/Emails/`. 3. Tabla `email_queue (id, to, subject, body, status, sent_at, error)`. 4. Worker de cola que despacha emails pendientes. 5. Configuración SMTP en `.env`. |

#### D.3 — 🔄 Webhooks Bidireccionales con Moodle
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Completar la integración de webhooks: cuando un evento ocurre en Moodle (nueva matrícula, calificación actualizada), Moodle notifica a EDUMA automáticamente vía webhook. Actualmente existe `MoodleWebhookController` pero con implementación mínima (1.6KB). |
| **Beneficio** | Sincronización en tiempo real. Elimina la necesidad de syncs completos frecuentes. |
| **Prioridad** | **🟡 Media** |
| **Implementación** | 1. Completar `MoodleWebhookController::handle()` con parsing de eventos. 2. Configurar plugin de Webhooks en Moodle (local_webhooks). 3. Validación HMAC del payload. 4. Queue de procesamiento para no bloquear el webhook. |

#### D.4 — 📊 Generación Automática de Reportes Periódicos
| Aspecto | Detalle |
|---------|---------|
| **Descripción** | Reportes PDF/Excel generados automáticamente: rendimiento académico mensual por carrera, informe de cumplimiento de gestión, reporte de uso del sistema. Enviados por email a stakeholders configurables. |
| **Beneficio** | Elimina trabajo manual de generación de reportes. Garantiza informes consistentes y puntuales. |
| **Prioridad** | **🟢 Baja** |
| **Implementación** | 1. `ReportGeneratorService`. 2. Templates con `PdfService` existente. 3. Integrar con Scheduler (D.1) y Email (D.2). 4. Configuración de destinatarios. |

---

## PARTE III: RECOMENDACIONES TÉCNICAS DE IMPLEMENTACIÓN

---

### Prioridad Inmediata (Sprint 1-2) — "Parcheo de Seguridad"

```
Semana 1:
├── C.3: Migrar contraseñas legacy a hash ◄ CRÍTICO
├── C.2: session_regenerate_id() post-login ◄ 3 líneas
├── C.6: Proteger rutas /sistema, /recycle-bin, /setup/rbac ◄ 10 líneas
├── Fix: APP_DEBUG=false en producción
├── Fix: Crear .env.example sin credenciales reales
└── Fix: Corregir session.cookie_strict → session.cookie_samesite

Semana 2:
├── C.1: Rate Limiting en BD (reemplazar sesión)
├── Fix 1.3: Corregir rutas VIEW_PATH, CONTROLLER_PATH, MODEL_PATH en Constants.php
├── Fix 2.5: Rotar CSRF token post-login
└── Fix 2.9: Eliminar/proteger ruta /setup/rbac
```

### Prioridad Alta (Sprint 3-4) — "Funcionalidad Core"

```
Sprint 3:
├── A.1: Recuperación de contraseña (flujo completo)
├── B.2: Sistema de migraciones (MigrationRunner + tabla)
├── B.1: Dashboard KPIs mejorado
└── D.1: Scheduler de tareas

Sprint 4:
├── A.2: Panel analíticas para docentes
├── C.1: Rate Limiting persistente (ya se hizo en sprint 1-2)
├── B.5: Exportación universal (completar ExportService)
└── Fix: Refactorizar DashboardModel → DashboardRepository
```

### Prioridad Media (Sprint 5-8) — "Escalabilidad y Features"

```
Sprint 5-6:
├── A.3: Repositorio de documentos
├── A.4: Calendario académico
├── B.3: Auditoría avanzada con Explorer
├── B.4: Gestión avanzada de perfiles
└── D.2: Sistema de emails

Sprint 7-8:
├── A.5: Mensajería interna
├── C.4: 2FA para admins
├── C.5: Política de contraseñas
├── D.3: Webhooks bidireccionales
└── Refactorizar MoodleController (dividir en sub-controllers)
```

### Prioridad Baja (Backlog) — "Polish y Futuro"

```
├── D.4: Reportes automáticos
├── Fix: Adoptar patrón Repository en todos los módulos
├── Fix: Implementar DTOs/ViewModels consistentemente
├── Fix: Separar BulkDatabaseService en sub-servicios
├── Búsqueda global (enrutar SearchController)
├── Preview de Predicción con UI
└── Tests automatizados (PHPUnit con fixtures)
```

---

## PARTE IV: RESUMEN EJECUTIVO

### Fortalezas del Sistema Actual
1. ✅ **Arquitectura MVC bien definida** con separación clara de capas (Controller → Service → Repository → Model).
2. ✅ **Sistema RBAC completo** con permisos granulares, middleware y menú dinámico.
3. ✅ **Sincronización Moodle robusta** (v3.3) con procesamiento paralelo, circuit breaker y bulk operations.
4. ✅ **Seguridad base sólida**: CSRF automático en Router, CSP headers, XSS protection headers, prepared statements.
5. ✅ **Container DI con auto-wiring**: Resolución automática de dependencias por Reflection.
6. ✅ **Diseño premium** documentado con estándares glassmorphism, animaciones y tipografía moderna.
7. ✅ **Sesiones en BD** (`DatabaseSessionHandler`) para escalabilidad.
8. ✅ **Sistema de Eventos** desacoplado con EventDispatcher.

### Deudas Técnicas Más Urgentes
1. 🔴 **Contraseñas en texto plano** todavía permitidas en login
2. 🔴 **`APP_DEBUG=true`** en producción
3. 🔴 **Rate Limiting eludible** (basado en sesión)
4. 🔴 **Rutas administrativas sin protección** (`/setup/rbac`, `/sistema`, `/recycle-bin`)
5. 🔴 **Sin flujo de recuperación de contraseña**

### Métricas del Codebase
| Métrica | Valor |
|---------|-------|
| Controladores | 19 |
| Servicios | 23+ |
| Modelos | 9 |
| Repositorios | 1 (Investigación) |
| Helpers | 13 |
| Vistas | 34+ carpetas |
| Migraciones SQL | 10 archivos |
| Tablas BD principales | ~20 |
| Rutas definidas | ~55 |
| Líneas de código más grande | `MoodleSyncOptimizedService.php` (1,149 líneas) |

---

*Este análisis fue generado tras revisar exhaustivamente: estructura de archivos, 25+ archivos PHP de código fuente, esquema SQL completo, configuración de entorno, rutas, middlewares, helpers de seguridad y la documentación técnica (skills) del proyecto.*
