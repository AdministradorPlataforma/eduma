<?php 
$pageTitle = 'Sistema y Configuración';
$extraCSS = 'Sistema';
$extraJS = 'Sistema';
include_once __DIR__ . '/../Layouts/Header.php'; 
?>

<!-- Assets Específicos Adicionales (Si no se manejan por extraCSS/extraJS) -->
<link rel="stylesheet" href="<?= BASE_URL ?>css/Escritorio.css?v=<?= time() ?>">

<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <div class="container-fluid py-5 px-lg-5">

            <!-- System Health Dashboard -->
            <section class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-900 text-slate m-0">Dashboard de Salud</h4>
                        <p class="text-muted small mb-0">Monitoreo en tiempo real de recursos y servicios</p>
                    </div>
                    <span class="badge bg-soft-indigo text-indigo rounded-pill px-3 py-2 fw-700">
                        <i class="bi bi-cpu-fill me-1"></i> Servidor: <?= php_uname('n') ?>
                    </span>
                </div>

                <div class="row g-4">
                    <!-- CPU Usage -->
                    <div class="col-md-3">
                        <div class="glass-panel p-4 h-100 d-flex flex-column justify-content-between animate-up delay-1">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-700 text-muted text-xs text-uppercase">CPU Load</span>
                                <i class="bi bi-cpu text-indigo"></i>
                            </div>
                            <div class="d-flex align-items-end gap-2 mb-2">
                                <h2 class="mb-0 fw-800 text-slate"><?= $health['cpu'] ?>%</h2>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-gradient-indigo" role="progressbar" style="width: <?= $health['cpu'] ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- RAM Usage -->
                    <div class="col-md-3">
                        <div class="glass-panel p-4 h-100 d-flex flex-column justify-content-between animate-up delay-2">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-700 text-muted text-xs text-uppercase">Memoria RAM</span>
                                <i class="bi bi-memory text-rose"></i>
                            </div>
                            <div class="d-flex align-items-end gap-2 mb-2">
                                <h2 class="mb-0 fw-800 text-slate"><?= $health['ram']['percent'] ?>%</h2>
                                <span class="text-xs text-muted fw-600 mb-1"><?= $health['ram']['used_gb'] ?> GB / <?= $health['ram']['total_gb'] ?> GB</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-gradient-rose" role="progressbar" style="width: <?= $health['ram']['percent'] ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Disk Usage -->
                    <div class="col-md-3">
                        <div class="glass-panel p-4 h-100 d-flex flex-column justify-content-between animate-up delay-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-700 text-muted text-xs text-uppercase">Espacio Disco</span>
                                <i class="bi bi-hdd-fill text-amber"></i>
                            </div>
                            <div class="d-flex align-items-end gap-2 mb-2">
                                <h2 class="mb-0 fw-800 text-slate"><?= $health['disk']['percent'] ?>%</h2>
                                <span class="text-xs text-muted fw-600 mb-1"><?= $health['disk']['free_gb'] ?> GB Libres</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-gradient-amber" role="progressbar" style="width: <?= $health['disk']['percent'] ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Moodle Status -->
                    <div class="col-md-3">
                        <div class="glass-panel p-4 h-100 d-flex flex-column justify-content-between animate-up delay-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-700 text-muted text-xs text-uppercase">Moodle API</span>
                                <?php if ($health['moodle']['online']): ?>
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger"></i>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex flex-column">
                                <h4 class="mb-1 fw-800 <?= $health['moodle']['online'] ? 'text-success' : 'text-danger' ?>">
                                    <?= $health['moodle']['online'] ? 'Online' : 'Offline' ?>
                                </h4>
                                <span class="text-xs text-muted fw-600">
                                    Latency: <?= $health['moodle']['latency'] ?>ms
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-5">
                <!-- Herramientas de Mantenimiento -->
                <div class="col-xl-7">
                    <div class="d-flex align-items-center mb-4 animate-up delay-3">
                        <div class="icon-sq-sm bg-soft-rose rounded-3 me-3 text-rose">
                            <i class="bi bi-tools"></i>
                        </div>
                        <h4 class="fw-900 text-slate m-0">Mantenimiento Preventivo</h4>
                    </div>

                    <div class="glass-card-system p-4 animate-up delay-4 shadow-sm mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div>
                                <h5 class="fw-800 text-slate mb-1">Janitor (Limpieza del Sistema)</h5>
                                <p class="text-muted small mb-0">Elimina basura digital y optimiza el rendimiento general.</p>
                            </div>
                            <span class="badge bg-soft-rose text-rose rounded-pill px-3 py-2 fw-800 text-xs">SISTEMA</span>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <div class="p-3 bg-light rounded-4 border-dashed">
                                    <div class="text-xs fw-800 text-muted mb-1 text-uppercase">Caché del Sistema</div>
                                    <div class="fw-900 text-slate">64 MB Pendientes</div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 bg-light rounded-4 border-dashed">
                                    <div class="text-xs fw-800 text-muted mb-1 text-uppercase">Logs de Auditoría</div>
                                    <div class="fw-900 text-slate"><?= date('Y-m-d') ?> (Última)</div>
                                </div>
                            </div>
                        </div>

                        <button id="btn-janitor" class="btn btn-premium-primary w-100 py-3 rounded-4 shadow-lg">
                            <i class="bi bi-lightning-charge-fill me-2 fs-5"></i> Ejecutar Rutina de Limpieza Completa
                        </button>
                    </div>

                    <div class="glass-card-system p-4 animate-up delay-5 shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div>
                                <h5 class="fw-800 text-slate mb-1">Papelera de Reciclaje</h5>
                                <p class="text-muted small mb-0">Gestiona elementos eliminados para su recuperación o borrado final.</p>
                            </div>
                            <div class="icon-sq-sm bg-soft-indigo rounded-3 text-indigo">
                                <i class="bi bi-recycle"></i>
                            </div>
                        </div>

                        <a href="<?= BASE_URL ?>recycle-bin" class="btn btn-outline-indigo w-100 py-3 rounded-4 fw-800">
                            <i class="bi bi-trash3 me-2"></i> Abrir Papelera de Reciclaje
                        </a>
                    </div>
                </div>

                <!-- Info Técnica y Configuración -->
                <div class="col-xl-5">
                    <div class="d-flex align-items-center mb-4 animate-up delay-4">
                        <div class="icon-sq-sm bg-soft-indigo rounded-3 me-3 text-indigo">
                            <i class="bi bi-info-circle-fill"></i>
                        </div>
                        <h4 class="fw-900 text-slate m-0">Información del Entorno</h4>
                    </div>

                    <div class="glass-panel p-4 animate-up delay-5 mb-4">
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between align-items-center py-3 border-bottom">
                                <span class="fw-700 text-muted">PHP Version</span>
                                <span class="badge-soft-indigo px-3 py-1 rounded-pill fw-800"><?= phpversion() ?></span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-3 border-bottom">
                                <span class="fw-700 text-muted">Database Engine</span>
                                <span class="fw-800 text-slate">MySQL 8.0 / WampServer</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-3 border-bottom">
                                <span class="fw-700 text-muted">Sincronización Moodle</span>
                                <span class="text-success fw-800"><i class="bi bi-cloud-check-fill me-1"></i> Conectado</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-3">
                                <span class="fw-700 text-muted">Entorno de Ejecución</span>
                                <span class="badge-soft-warning px-3 py-1 rounded-pill fw-800">DEVELOPMENT</span>
                            </li>
                        </ul>
                    </div>

                    <div class="alert alert-soft-indigo border-0 rounded-4 p-4 animate-up delay-6">
                        <div class="d-flex gap-3">
                            <i class="bi bi-stars fs-4 text-indigo"></i>
                            <div>
                                <h6 class="fw-800 text-indigo mb-1">Próximamente: Panel de Logs</h6>
                                <p class="mb-0 text-muted small">Estamos trabajando en un visualizador de logs en tiempo real para facilitarte el debugging.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Migrations Section -->
            <div class="row g-5 mt-4">
                <div class="col-12">
                     <div class="d-flex align-items-center mb-4 animate-up delay-5">
                        <div class="icon-sq-sm bg-soft-amber rounded-3 me-3 text-amber">
                            <i class="bi bi-database-fill-gear"></i>
                        </div>
                        <h4 class="fw-900 text-slate m-0">Base de Datos y Migraciones</h4>
                    </div>

                    <div class="glass-card-system p-4 animate-up delay-6 shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div>
                                <h5 class="fw-800 text-slate mb-1">Estado de Migraciones</h5>
                                <p class="text-muted small mb-0">Gestiona las actualizaciones de esquema de base de datos.</p>
                            </div>
                            <?php 
                                $pendingCount = 0;
                                foreach($migrations as $m) if(!$m['executed']) $pendingCount++;
                            ?>
                            <?php if($pendingCount > 0): ?>
                                <span class="badge bg-danger text-white rounded-pill px-3 py-2 fw-800 animate-pulse">
                                    <?= $pendingCount ?> PENDIENTES
                                </span>
                            <?php else: ?>
                                <span class="badge bg-soft-success text-success rounded-pill px-3 py-2 fw-800">
                                    AL DÍA
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive rounded-4 border-dashed mb-4">
                            <table class="table table-hover align-middle mb-0 no-datatable">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 text-muted text-uppercase text-xs fw-800">Archivo de Migración</th>
                                        <th class="text-muted text-uppercase text-xs fw-800">Estado</th>
                                        <th class="text-muted text-uppercase text-xs fw-800">Fecha Ejecución</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($migrations as $mig): ?>
                                    <tr>
                                        <td class="ps-4 fw-700 text-slate font-monospace small">
                                            <?= $mig['migration'] ?>
                                        </td>
                                        <td>
                                            <?php if($mig['executed']): ?>
                                                <span class="badge-soft-success px-2 py-1 rounded fw-800 text-xs">EJECUTADO</span>
                                            <?php else: ?>
                                                <span class="badge-soft-warning px-2 py-1 rounded fw-800 text-xs">PENDIENTE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small fw-600">
                                            <?= $mig['executed_at'] ? date('d/m/Y H:i', strtotime($mig['executed_at'])) : '-' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($migrations)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted fst-italic">No hay migraciones registradas.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if($pendingCount > 0): ?>
                        <div class="alert alert-soft-warning border-0 rounded-4 p-3 mb-3 d-flex align-items-center gap-3">
                            <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
                            <div>
                                <h6 class="fw-800 text-warning mb-1">Actualización Requerida</h6>
                                <p class="mb-0 text-muted small">Hay cambios de estructura de base de datos pendientes. Ejecútelos para asegurar la estabilidad.</p>
                            </div>
                        </div>
                        <button id="btn-migrate" class="btn btn-warning w-100 py-3 rounded-4 shadow-lg fw-800 text-dark">
                            <i class="bi bi-play-circle-fill me-2 fs-5"></i> Ejecutar Migraciones Pendientes
                        </button>
                        <?php else: ?>
                             <button class="btn btn-light w-100 py-3 rounded-4 fw-800 text-muted" disabled>
                                <i class="bi bi-check-circle-fill me-2"></i> Base de Datos Actualizada
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- JS para Migraciones -->
            <!-- JS para Migraciones y Janitor -->
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const btnMigrate = document.getElementById('btn-migrate');
                    
                    // Función helper para obtener CSRF
                    const getCsrfToken = () => {
                        return document.querySelector('input[name="csrf_token"]')?.value || 
                               document.querySelector('meta[name="csrf-token"]')?.content || '';
                    };

                    if(btnMigrate) {
                        btnMigrate.addEventListener('click', async () => {
                            const result = await Swal.fire({
                                title: '¿Ejecutar Migraciones?',
                                text: "Se aplicarán cambios en la estructura de la base de datos. Asegúrate de tener un respaldo.",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#ffc107',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: 'Sí, ejecutar',
                                cancelButtonText: 'Cancelar',
                                customClass: {
                                    confirmButton: 'text-dark fw-bold'
                                }
                            });

                            if (!result.isConfirmed) return;
                            
                            btnMigrate.disabled = true;
                            const originalContent = btnMigrate.innerHTML;
                            btnMigrate.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Ejecutando...';
                            
                            try {
                                const fd = new FormData();
                                fd.append('csrf_token', getCsrfToken());

                                const response = await fetch('<?= BASE_URL ?>sistema/migrate', {
                                    method: 'POST',
                                    body: fd,
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': getCsrfToken()
                                    }
                                });
                                
                                const data = await response.json();
                                
                                if(data.status === 'success') {
                                    await Swal.fire(
                                        '¡Actualizado!',
                                        'La base de datos se ha actualizado correctamente.',
                                        'success'
                                    );
                                    location.reload();
                                } else {
                                    Swal.fire('Error', data.message || 'Error desconocido', 'error');
                                    btnMigrate.disabled = false;
                                    btnMigrate.innerHTML = '<i class="bi bi-play-circle-fill me-2 fs-5"></i> Reintentar';
                                }
                            } catch(e) {
                                console.error(e);
                                Swal.fire('Error', 'Fallo de conexión con el servidor', 'error');
                                btnMigrate.disabled = false;
                                btnMigrate.innerHTML = '<i class="bi bi-play-circle-fill me-2 fs-5"></i> Reintentar';
                            }
                        });
                    }

                    // Janitor Button (si existe)
                     const btnJanitor = document.getElementById('btn-janitor');
                     if(btnJanitor) {
                        btnJanitor.addEventListener('click', async () => {
                             const result = await Swal.fire({
                                title: '¿Limpiar Sistema?',
                                text: "Se eliminarán cachés y archivos temporales.",
                                icon: 'info',
                                showCancelButton: true,
                                confirmButtonText: 'Sí, limpiar',
                                confirmButtonColor: '#6366f1'
                            });

                            if (!result.isConfirmed) return;

                            btnJanitor.disabled = true;
                            btnJanitor.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Limpiando...';

                             try {
                                const fd = new FormData();
                                fd.append('csrf_token', getCsrfToken());

                                const response = await fetch('<?= BASE_URL ?>sistema/janitor/run', {
                                    method: 'POST', body: fd,
                                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrfToken() }
                                });
                                const data = await response.json();

                                if(data.status === 'success') {
                                    Swal.fire('Limpieza Completa', data.message, 'success');
                                } else {
                                    Swal.fire('Error', data.message, 'error');
                                }
                            } catch(e) {
                                Swal.fire('Error', 'No se pudo conectar.', 'error');
                            } finally {
                                btnJanitor.disabled = false;
                                btnJanitor.innerHTML = '<i class="bi bi-lightning-charge-fill me-2 fs-5"></i> Ejecutar Rutina de Limpieza Completa';
                            }
                        });
                     }
                });
            </script>

        </div>
        
    </div> <!-- Close .container-fluid ?? No, container-fluid fue cerrado arriba... --> 
    <!-- En realidad el Layouts/Footer.php cierra main-layout y content-wrapper, así que solo falta incluirlo -->
    <?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
