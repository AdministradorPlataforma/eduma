<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<!-- Bloque Maestro -->
<div class="main-layout">
    
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <!-- Librerías de UI -->
        <link rel="stylesheet" href="<?= BASE_URL ?>css/libraries/toastr.min.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/libraries/sweetalert2.min.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/Gestion.css?v=<?= time() ?>">

        <div class="container-fluid py-4 px-4">


            <!-- Token CSRF para JS -->
            <input type="hidden" id="csrf_token_global" value="<?= \App\Helpers\CSRFHelper::getToken() ?>">

            <div class="row g-4">
                <!-- Columna Izquierda: Mis Tareas (Prioridad) -->
                <div class="col-lg-7 animate-up delay-1">
                    <div class="glass-panel p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="fw-800 m-0 text-slate">Tareas Pendientes</h4>
                                <p class="small text-muted mb-0">Acciones requeridas para este periodo</p>
                            </div>
                            <div class="icon-sq-md bg-soft-indigo mb-2">
                                <i class="bi bi-list-task text-indigo"></i>
                            </div>
                        </div>

                        <?php if (empty($tareas)): ?>
                            <div class="empty-state-card mt-3">
                                <div class="icon-sq-lg bg-soft-mint mx-auto mb-4 animate-bounce-soft">
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                </div>
                                <h4 class="fw-800 text-slate mb-2">¡Todo al día!</h4>
                                <p class="text-muted fw-500 small max-w-sm mx-auto">
                                    No tienes tareas pendientes asignadas a tu cargo en este momento.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-premium align-middle">
                                    <thead>
                                        <tr>
                                            <th>Actividad / Documento</th>
                                            <th class="text-center">Vencimiento</th>
                                            <th class="text-center">Estado</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tareas as $tarea): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="icon-sq-sm bg-light me-3 text-muted">
                                                            <i class="bi bi-file-earmark-text"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-700 text-slate text-sm"><?= htmlspecialchars($tarea['producto_documento']) ?></div>
                                                            <div class="text-xs text-muted">Destino: <?= htmlspecialchars($tarea['destino']) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <span class="text-xs fw-800 text-slate">Día <?= $tarea['dia_plazo_mes'] ?></span>
                                                        <span class="text-xs text-muted">del mes</span>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($tarea['evidencia_id']): ?>
                                                        <span class="badge-status success rounded-pill">Completo</span>
                                                    <?php else: ?>
                                                        <?php 
                                                            $statusClass = $tarea['semaforo'] == 'danger' ? 'danger' : ($tarea['semaforo'] == 'warning' ? 'warning' : 'success');
                                                            $statusText = $tarea['semaforo'] == 'danger' ? 'Vencida' : ($tarea['semaforo'] == 'warning' ? 'Por Vencer' : 'A Tiempo');
                                                        ?>
                                                        <span class="badge-status <?= $statusClass ?> rounded-pill">
                                                            <span class="semaphore-dot <?= $statusClass ?>"></span><?= $statusText ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if (!$tarea['evidencia_id']): ?>
                                                        <button class="btn btn-premium-primary btn-sm btn-round py-2 px-3 shadow-sm btn-upload-file" 
                                                                data-id="<?= $tarea['id'] ?>" 
                                                                data-titulo="<?= htmlspecialchars($tarea['producto_documento']) ?>">
                                                            <i class="bi bi-cloud-arrow-up me-2"></i> Subir
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-completed py-2 px-3 w-100">
                                                            <i class="bi bi-check-lg me-1"></i> Enviado
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Columna Derecha: Planilla Global (Informativa) -->
                <div class="col-lg-5 animate-up delay-2">
                    <div class="glass-panel p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="fw-800 m-0 text-slate">Planilla Institucional</h4>
                                <p class="small text-muted mb-0">Estado global de facultades</p>
                            </div>
                            <div class="icon-sq-md bg-soft-rose mb-2">
                                <i class="bi bi-grid text-rose"></i>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-borderless align-middle no-datatable">
                                <tbody class="fs-7">
                                    <?php foreach ($planilla as $row): ?>
                                        <tr class="border-bottom border-light">
                                            <td class="py-3 ps-2">
                                                <div class="fw-700 text-slate mb-1"><?= htmlspecialchars($row['facultad']) ?></div>
                                                <div class="text-xs text-muted"><?= htmlspecialchars($row['producto_documento']) ?></div>
                                            </td>
                                            <td class="text-end pe-2">
                                                <?php if ($row['fecha_subida']): ?>
                                                    <span class="badge bg-soft-mint text-success rounded-pill px-2 py-1">
                                                        <i class="bi bi-check-circle-fill me-1"></i> Cumplido
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted rounded-pill px-2 py-1">
                                                        <i class="bi bi-hourglass me-1"></i> Pendiente
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenedor de Datos para JS Externo -->
        <div id="server-data" 
             data-csrf="<?= \App\Helpers\CSRFHelper::getToken() ?>" 
             data-alerts='<?= json_encode($alertas ?? []) ?>'>
        </div>

<script src="<?= BASE_URL ?>js/libraries/toastr.min.js"></script>
<script src="<?= BASE_URL ?>js/Gestion.js?v=<?= time() ?>"></script>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
