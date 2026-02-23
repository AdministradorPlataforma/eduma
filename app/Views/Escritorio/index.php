<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>

<!-- Assets Específicos -->
<link rel="stylesheet" href="<?= BASE_URL ?>css/libraries/toastr.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/libraries/sweetalert2.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/Escritorio.css?v=<?= time() ?>">

<!-- Sidebar como el bloque independiente izquierdo -->
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<!-- Main Layout como el bloque derecho que contiene Navbar + Content -->
<div class="main-layout">
    
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <div class="container-fluid py-5 px-lg-5">

            <!-- Flash Alerts -->
            <?= \App\Helpers\FlashHelper::alert('all'); ?>

            <!-- SECTION 2: Global KPIs -->
            <section class="main-stat-grid mb-5">
                <div class="kpi-modern-v2 indigo animate-up delay-1">
                    <div class="kpi-content">
                        <span class="kpi-label">Usuarios</span>
                        <h2 class="kpi-value text-slate"><?= $vm->getStats()->total_usuarios ?></h2>
                    </div>
                    <div class="kpi-icon-box">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>

                <div class="kpi-modern-v2 red animate-up delay-2">
                    <div class="kpi-content">
                        <span class="kpi-label">Notificaciones</span>
                        <h2 class="kpi-value text-slate"><?= $vm->getStats()->unread_notifs ?></h2>
                    </div>
                    <div class="kpi-icon-box">
                        <i class="bi bi-envelope-open-fill"></i>
                    </div>
                </div>

                <div class="kpi-modern-v2 purple animate-up delay-2">
                    <div class="kpi-content">
                        <span class="kpi-label">Tesis</span>
                        <h2 class="kpi-value text-slate"><?= $vm->getStats()->total_tesis ?></h2>
                    </div>
                    <div class="kpi-icon-box">
                        <i class="bi bi-book-half"></i>
                    </div>
                </div>

                <div class="kpi-modern-v2 green animate-up delay-3">
                    <div class="kpi-content">
                        <span class="kpi-label">Cursos</span>
                        <h2 class="kpi-value text-slate"><?= $vm->getStats()->total_cursos ?></h2>
                    </div>
                    <div class="kpi-icon-box">
                        <i class="bi bi-layers-fill"></i>
                    </div>
                </div>

                <div class="kpi-modern-v2 amber animate-up stagger-4">
                    <div class="kpi-content">
                        <span class="kpi-label">Cumplimiento</span>
                        <h2 class="kpi-value text-slate"><?= $vm->getStats()->cumplimiento ?>%</h2>
                    </div>
                    <div class="kpi-icon-box">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                </div>
            </section>

            <div class="row g-5">
                <!-- SECTION 3: Operations Area -->
                <main class="col-xl-8 animate-up delay-3">
                    <div class="glass-panel p-4 mb-5" aria-labelledby="deadlines-title">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 id="deadlines-title" class="fw-900 text-slate m-0">Vencimientos Próximos</h4>
                            <a href="<?= BASE_URL ?>gestion/vencimientos" class="btn btn-premium-secondary btn-sm btn-round">
                                <i class="bi bi-calendar-event me-2"></i> Calendario
                            </a>
                        </div>
                        
                        <div class="d-flex flex-column gap-3">
                            <?php if (!$vm->hasVencimientos()): ?>
                                <div class="text-center py-4 bg-light rounded-4 opacity-75">
                                    <i class="bi bi-check-circle-fill text-success fs-2 mb-2 d-block"></i>
                                    <span class="fw-700 text-muted small">Sin tareas pendientes para hoy</span>
                                </div>
                            <?php else: ?>
                                <?php foreach ($vm->getVencimientos() as $v): ?>
                                <article class="expiry-card-slim <?= $v->css_class ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="icon-sq-sm rounded-3 bg-soft-indigo">
                                                    <i class="bi bi-clock-history"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-800 text-slate small mb-1"><?= htmlspecialchars($v->nombre) ?></div>
                                                    <div class="text-muted text-xs"><?= htmlspecialchars($v->descripcion) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-5 text-end">
                                            <span class="badge-soft-<?= $v->badge_color ?> px-2 py-1 rounded text-xs fw-800 me-2">
                                                <?= $v->fecha_formato ?>
                                            </span>
                                            <button class="btn btn-premium-secondary btn-xs fw-800">Gestionar</button>
                                        </div>
                                    </div>
                                </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </main>

                <!-- SECTION 4: Sidebar Information -->
                <aside class="col-xl-4 text-sm-8">
                    <nav class="glass-panel p-4 mb-5 shadow-sm" aria-label="Accesos rápidos">
                        <h5 class="fw-900 mb-4 text-slate">Acceso Rápido</h5>
                        <div class="d-flex flex-column gap-2">
                            <div class="action-card-premium" data-url="<?= BASE_URL ?>gestion/expedientes" role="button" tabindex="0">
                                <div class="icon-circle icon-bg-indigo shadow-sm">
                                    <i class="bi bi-file-earmark-person"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-800 text-slate small">Expedientes</div>
                                    <div class="text-muted text-xs">Gestión de alumnos</div>
                                </div>
                                <i class="bi bi-chevron-right opacity-30"></i>
                            </div>
                            <div class="action-card-premium" data-url="<?= BASE_URL ?>gestion/carga-docente" role="button" tabindex="0">
                                <div class="icon-circle icon-bg-mint shadow-sm">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-800 text-slate small">Docentes</div>
                                    <div class="text-muted text-xs">Asignaciones EaD</div>
                                </div>
                                <i class="bi bi-chevron-right opacity-30"></i>
                            </div>
                            <div class="action-card-premium" data-url="<?= BASE_URL ?>investigacion" role="button" tabindex="0">
                                <div class="icon-circle icon-bg-amber shadow-sm">
                                    <i class="bi bi-book-fill"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-800 text-slate small">Tesis</div>
                                    <div class="text-muted text-xs">Gestión de Investigaciones</div>
                                </div>
                                <i class="bi bi-chevron-right opacity-30"></i>
                            </div>
                            </div>
                        </div>
                    </nav>
                </aside>
            </div> <!-- Close .row -->
        </div> <!-- Close .container-fluid -->
        
        <!-- Scripts del Gráfico (Antes del cierre de content-wrapper) -->
        <script src="<?= BASE_URL ?>js/libraries/chart.min.js"></script>
        
        <?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
<?php // No incluimos scripts globales aquí porque ya están en el Footer ?>
