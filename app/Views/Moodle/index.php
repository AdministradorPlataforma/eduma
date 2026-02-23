<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>

<!-- Assets Específicos -->
<link rel="stylesheet" href="<?= BASE_URL ?>css/libraries/sweetalert2.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/Moodle.css?v=<?= time() ?>">

<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <div class="container-fluid py-5 px-lg-5">
            
            <!-- Header con Health Check -->
            <div class="row g-4 mb-4">
                <!-- Estado de Conexión Moodle -->
                <div class="col-xl-6 col-lg-6 animate-up">
                    <div class="glass-panel p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="health-indicator" id="health-indicator">
                                <div class="health-dot loading"></div>
                            </div>
                            <div>
                                <h5 class="fw-800 text-slate mb-0">Conexión Moodle</h5>
                                <small class="text-muted" id="health-sitename">Verificando...</small>
                            </div>
                        </div>
                        <div id="health-details" class="health-details">
                            <div class="health-row">
                                <span class="label">Versión:</span>
                                <span class="value" id="health-version">-</span>
                            </div>
                            <div class="health-row">
                                <span class="label">Usuario API:</span>
                                <span class="value" id="health-username">-</span>
                            </div>
                            <div class="health-row">
                                <span class="label">Funciones:</span>
                                <span class="value" id="health-functions">-</span>
                            </div>
                        </div>
                        <button id="btn-health-refresh" class="btn btn-soft-primary btn-sm mt-3">
                            <i class="bi bi-arrow-clockwise me-1"></i>Verificar
                        </button>
                    </div>
                </div>

                <!-- Circuit Breaker Status -->
                <div class="col-xl-6 col-lg-6 animate-up stagger-1">
                    <div class="glass-panel p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="circuit-indicator" id="circuit-indicator">
                                <i class="bi bi-lightning-charge-fill"></i>
                            </div>
                            <div>
                                <h5 class="fw-800 text-slate mb-0">Circuit Breaker</h5>
                                <small class="text-muted">Protección contra fallos</small>
                            </div>
                        </div>
                        <div id="circuit-details" class="health-details">
                            <div class="health-row">
                                <span class="label">Estado:</span>
                                <span class="value" id="circuit-status">
                                    <span class="badge bg-soft-success text-success">CERRADO</span>
                                </span>
                            </div>
                            <div class="health-row">
                                <span class="label">Fallos consecutivos:</span>
                                <span class="value" id="circuit-failures">0 / 10</span>
                            </div>
                            <div class="health-row d-none" id="circuit-reset-row">
                                <span class="label">Reset automático:</span>
                                <span class="value text-warning" id="circuit-reset-time">-</span>
                            </div>
                        </div>
                        <button id="btn-circuit-reset" class="btn btn-soft-danger btn-sm mt-3 d-none">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Manual
                        </button>
                    </div>
                </div>

                <!-- Espacio libre para futuro (o reajuste de columnas) -->
                <!-- <div class="col-xl-4 col-lg-12 animate-up stagger-2"> ... eliminada por redundancia ... </div> -->
            </div>

            <!-- Consola de Sincronización Inteligente - V3.5 (Unificada) -->
            <div class="glass-panel p-4 mb-4 animate-up stagger-3" id="sync-console">
                <!-- Header de la Consola -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="console-icon">
                            <i class="bi bi-cloud-arrow-down-fill text-indigo"></i>
                        </div>
                        <div>
                            <h4 class="fw-800 text-slate mb-0">Consola de Sincronización</h4>
                            <small id="console-subtitle" class="text-muted">Lista para operar • v3.5</small>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 align-items-center">
                        <div id="sync-status-badge" class="sync-status-badge idle">
                            <span class="pulse-dot"></span>
                            <span class="status-text">Inactivo</span>
                        </div>
                        <button id="btn-sync-stop" class="btn btn-danger btn-lg shadow-sm d-none">
                            <i class="bi bi-stop-circle-fill me-2"></i>Detener
                        </button>
                    </div>
                </div>

                <!-- CAPA 1: Configuración (Visible cuando está Idle) -->
                <div id="sync-config-layer" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-slate small">Tipo de Sincronización</label>
                        <select id="sync-type" class="form-select form-select-lg">
                            <option value="all">🔄 Completa</option>
                            <option value="delta">⚡ Incremental</option>
                            <option value="categories">📁 Categorías</option>
                            <option value="courses">🎓 Cursos</option>
                            <option value="users">👥 Usuarios</option>
                            <option value="enrollments">📋 Matrículas</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold text-slate small">Ajustes</label>
                        <div class="d-flex gap-3 align-items-center glass-inset p-2 rounded-3 h-auto">
                            <div class="form-check form-switch custom-switch-sm mb-0">
                                <input class="form-check-input" type="checkbox" id="sync-force">
                                <label class="form-check-label small" for="sync-force">Forzar</label>
                            </div>
                            <div class="form-check form-switch custom-switch-sm mb-0">
                                <input class="form-check-input" type="checkbox" id="sync-regenerate-passwords">
                                <label class="form-check-label small" for="sync-regenerate-passwords">Claves</label>
                            </div>
                            <div class="ms-auto">
                                <button type="button" id="btn-reset-processes" class="btn btn-xs btn-soft-danger" title="Reset Total">
                                    <i class="bi bi-radioactive"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button id="btn-sync-start" class="btn btn-indigo btn-lg w-100 shadow-sm">
                            <i class="bi bi-rocket-takeoff-fill me-2"></i>Iniciar
                        </button>
                    </div>
                </div>

                <!-- CAPA 2: Monitor Real-Time (Visible cuando está Running) -->
                <div id="sync-monitor-layer" class="d-none">
                    <!-- Progress Section -->
                    <div class="row align-items-center mb-4">
                        <div class="col-md-9">
                            <div class="d-flex justify-content-between mb-2">
                                <span id="sync-message" class="fw-800 text-indigo small">Sincronizando...</span>
                                <span id="sync-percent" class="fw-800 text-slate small">0%</span>
                            </div>
                            <div class="progress progress-lg">
                                <div id="sync-progressbar" class="progress-bar bg-gradient-indigo progress-bar-striped progress-bar-animated" role="progressbar" style="--progress: 0%"></div>
                            </div>
                            <div id="sync-phase" class="small text-muted mt-2">Analizando jerarquía...</div>
                        </div>
                        <div class="col-md-3">
                            <div class="perf-card-mini">
                                <div class="perf-value" id="perf-rate">--</div>
                                <div class="perf-label">regs/seg</div>
                            </div>
                        </div>
                    </div>

                    <!-- Live Metrics Row -->
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="metric-box">
                                <span class="label">Transcurrido</span>
                                <span class="value" id="perf-elapsed">00:00</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="metric-box">
                                <span class="label">Procesados</span>
                                <span class="value text-indigo" id="stat-processed">0</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="metric-box">
                                <span class="label">Actualizados</span>
                                <span class="value text-success" id="stat-updated">0</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="metric-box">
                                <span class="label">Errores</span>
                                <span class="value text-danger" id="stat-errors">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Log Panel -->
            <div class="glass-panel p-4 animate-up stagger-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-800 text-slate mb-0">
                        <i class="bi bi-terminal-fill me-2"></i>Registro de Actividad
                    </h5>
                    <div class="d-flex gap-2">
                        <button id="btn-export-log" class="btn btn-soft-info btn-sm">
                            <i class="bi bi-download me-1"></i>Exportar
                        </button>
                        <button id="btn-clear-log" class="btn btn-soft-secondary btn-sm">
                            <i class="bi bi-trash me-1"></i>Limpiar
                        </button>
                    </div>
                </div>
                <div id="sync-log" class="sync-log">
                    <div class="log-entry info">
                        <span class="log-time"><?= date('H:i:s') ?></span>
                        <span class="log-msg">Sistema optimizado v3.0 listo. Esperando comandos...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- CSRF Token para JS -->
<div id="csrf-container" data-token="<?= \App\Helpers\CSRFHelper::getToken() ?>"></div>

<script src="<?= BASE_URL ?>js/libraries/sweetalert2.all.min.js"></script>
<script src="<?= BASE_URL ?>js/Moodle.js?v=<?= time() ?>"></script>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
