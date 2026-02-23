<?php 
$pageTitle = 'Tareas Programadas';
$extraCSS = 'Sistema'; // Reutilizar estilos si son genéricos
$extraJS = 'Sistema'; // Si necesitamos JS específico, podríamos crear Scheduler.js
include_once __DIR__ . '/../Layouts/Header.php'; 
?>

<link rel="stylesheet" href="<?= BASE_URL ?>css/Escritorio.css?v=<?= time() ?>">
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <div class="container-fluid py-5 px-lg-5">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-900 text-slate m-0">Tareas Programadas</h2>
                    <p class="text-muted small mb-0">Gestión y monitoreo de procesos automáticos del sistema.</p>
                </div>
                <div class="d-flex gap-2">
                     <a href="<?= BASE_URL ?>sistema" class="btn btn-light rounded-pill fw-700 px-4">
                        <i class="bi bi-arrow-left me-2"></i> Volver a Sistema
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-5">
                <!-- Total Executions -->
                <div class="col-md-3">
                    <div class="glass-panel p-4 h-100 d-flex flex-column justify-content-between animate-up delay-1">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-700 text-muted text-xs text-uppercase">Ejecuciones Totales</span>
                            <i class="bi bi-list-check text-indigo"></i>
                        </div>
                        <h2 class="mb-0 fw-800 text-slate"><?= $stats['total'] ?></h2>
                    </div>
                </div>

                <!-- Success -->
                 <div class="col-md-3">
                    <div class="glass-panel p-4 h-100 d-flex flex-column justify-content-between animate-up delay-2">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-700 text-muted text-xs text-uppercase">Exitosas</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </div>
                        <h2 class="mb-0 fw-800 text-success"><?= $stats['success'] ?></h2>
                    </div>
                </div>

                <!-- Failed -->
                 <div class="col-md-3">
                    <div class="glass-panel p-4 h-100 d-flex flex-column justify-content-between animate-up delay-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-700 text-muted text-xs text-uppercase">Fallidas</span>
                            <i class="bi bi-x-circle-fill text-danger"></i>
                        </div>
                        <h2 class="mb-0 fw-800 text-danger"><?= $stats['failed'] ?></h2>
                    </div>
                </div>

                <!-- Running -->
                 <div class="col-md-3">
                    <div class="glass-panel p-4 h-100 d-flex flex-column justify-content-between animate-up delay-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-700 text-muted text-xs text-uppercase">En Ejecución</span>
                            <i class="bi bi-play-circle-fill text-warning"></i>
                        </div>
                        <h2 class="mb-0 fw-800 text-warning"><?= $stats['running'] ?></h2>
                    </div>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="glass-card-system p-4 animate-up delay-5 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h5 class="fw-800 text-slate mb-1">Historial de Ejecución</h5>
                        <p class="text-muted small mb-0">Últimos 50 registros de actividad.</p>
                    </div>
                     <button class="btn btn-sm btn-outline-light text-muted" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refrescar
                    </button>
                </div>

                <div class="table-responsive rounded-4 border-dashed">
                    <table class="table table-hover align-middle mb-0" id="scheduler-table">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 text-muted text-uppercase text-xs fw-800">Tarea</th>
                                <th class="text-muted text-uppercase text-xs fw-800">Estado</th>
                                <th class="text-muted text-uppercase text-xs fw-800">Inicio</th>
                                <th class="text-muted text-uppercase text-xs fw-800">Fin</th>
                                <th class="text-muted text-uppercase text-xs fw-800">Duración</th>
                                <th class="text-muted text-uppercase text-xs fw-800">Salida</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                            <tr>
                                <td class="ps-4 fw-700 text-slate small">
                                    <?= htmlspecialchars($log['task_name']) ?>
                                </td>
                                <td>
                                    <?php if($log['status'] === 'success'): ?>
                                        <span class="badge-soft-success px-2 py-1 rounded fw-800 text-xs">EXITO</span>
                                    <?php elseif($log['status'] === 'failure'): ?>
                                        <span class="badge-soft-danger px-2 py-1 rounded fw-800 text-xs">FALLO</span>
                                    <?php else: ?>
                                        <span class="badge-soft-warning px-2 py-1 rounded fw-800 text-xs">CORRIENDO</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted text-xs font-monospace">
                                    <?= $log['started_at'] ?>
                                </td>
                                <td class="text-muted text-xs font-monospace">
                                    <?= $log['finished_at'] ?? '-' ?>
                                </td>
                                <td class="text-muted text-xs fw-700">
                                    <?= number_format($log['duration_ms'] / 1000, 2) ?>s
                                </td>
                                <td class="small text-muted">
                                    <?php if (!empty($log['output'])): ?>
                                        <button class="btn btn-xs btn-link text-decoration-none" 
                                                onclick="Swal.fire({title: 'Output', text: '<?= addslashes(htmlspecialchars($log['output'])) ?>', width: 600})">
                                            Ver Detalles
                                        </button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted fst-italic">No hay registros aún.</td>
                                </li>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    <?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
