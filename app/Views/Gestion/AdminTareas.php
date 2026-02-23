<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<!-- Bloque Maestro -->
<div class="main-layout">
    
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <!-- Librerías de UI -->
        <link rel="stylesheet" href="<?= BASE_URL ?>css/libraries/toastr.min.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/libraries/sweetalert2.min.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/AdminTareas.css?v=<?= time() ?>">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/Escritorio.css?v=<?= time() ?>">
        <div class="container-fluid py-4 px-4">

            <!-- Stats Row (Calculated from Data) -->
            <div class="row g-4 mb-5 animate-up delay-1">
                <div class="col-md-4">
                    <div class="glass-panel p-4 d-flex align-items-center justify-content-between stat-card">
                        <div>
                            <div class="label-tracking mb-1">ASIGNACIONES ACTIVAS</div>
                            <div class="h2 fw-800 m-0 text-slate-700"><?= $kpis['total_tareas'] ?? 0 ?></div>
                        </div>
                        <div class="icon-sq bg-soft-indigo text-indigo">
                            <i class="bi bi-list-task"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-panel p-4 d-flex align-items-center justify-content-between stat-card">
                        <div>
                            <div class="label-tracking mb-1">FACULTADES INVOLUCRADAS</div>
                            <div class="h2 fw-800 m-0 text-slate-700"><?= $kpis['facultades_unicas'] ?? 0 ?></div>
                        </div>
                        <div class="icon-sq bg-soft-rose text-rose">
                            <i class="bi bi-building"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-panel p-4 d-flex align-items-center justify-content-between stat-card">
                        <div>
                            <div class="label-tracking mb-1">PRÓXIMO CIERRE</div>
                            <div class="h2 fw-800 m-0 text-slate-700">
                                <?= ($kpis['proximo_cierre'] && $kpis['proximo_cierre'] != '-') ? "Día " . $kpis['proximo_cierre'] : '-' ?>
                            </div>
                        </div>
                        <div class="icon-sq bg-soft-mint text-success">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Formulario de Creación (Sin cambios) -->
                <div class="col-lg-4 animate-up delay-2">
                    <div class="glass-panel p-4 h-100 sticky-top-2">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="fw-800 m-0 text-slate-700">Nueva Asignación</h5>
                                <p class="small text-muted mb-0">Define una nueva responsabilidad</p>
                            </div>
                        </div>

                        <form action="<?= BASE_URL ?>gestion/guardar" method="POST">
                            <?= \App\Helpers\CSRFHelper::csrfField() ?>
                            
                            <div class="mb-3">
                                <label class="label-tracking mb-2 d-block">FACULTAD DESTINO</label>
                                <select class="form-select rounded-3 py-2 fw-600" name="facultad_id" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($facultades as $f): ?>
                                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="label-tracking mb-2 d-block">CARGO RESPONSABLE</label>
                                <input type="text" class="form-control rounded-3 py-2 fw-600" name="cargo_responsable" list="listacargos" placeholder="Ej: Decano" required>
                                <datalist id="listacargos">
                                    <?php foreach ($cargos as $c): ?>
                                        <option value="<?= htmlspecialchars($c) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="mb-3">
                                <label class="label-tracking mb-2 d-block">PRODUCTO / DOCUMENTO</label>
                                <textarea class="form-control rounded-3 py-2 fw-600" name="producto_documento" rows="2" required placeholder="Descripción del entregable..."></textarea>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="label-tracking mb-2 d-block">DÍA LÍMITE</label>
                                    <input type="number" class="form-control rounded-3 py-2 fw-600 text-center" name="dia_plazo" min="1" max="31" required placeholder="1-31">
                                </div>
                                <div class="col-6">
                                    <label class="label-tracking mb-2 d-block">FRECUENCIA</label>
                                    <select class="form-select rounded-3 py-2 fw-600" name="frecuencia">
                                        <option value="mensual">Mensual</option>
                                        <option value="semestral">Semestral</option>
                                        <option value="anual">Anual</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="label-tracking mb-2 d-block">DESTINATARIO (OPCIONAL)</label>
                                <input type="text" class="form-control rounded-3 py-2 fw-600" name="destino" placeholder="Ej: Vicerrectorado">
                            </div>

                            <button type="submit" class="btn btn-premium-primary btn-round w-100 py-3 shadow-soft">
                                <i class="bi bi-plus-lg me-2"></i> Crear Asignación
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-8 animate-up delay-3">
                    <div class="glass-panel p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="fw-800 m-0 text-slate-700">Matriz de Responsabilidades</h5>
                                <p class="small text-muted mb-0">Vista global de tareas asignadas</p>
                            </div>
                            <div class="fw-700 small text-indigo bg-soft-indigo px-3 py-1 rounded-pill">
                                Total: <?= $kpis['total_tareas'] ?? 0 ?>
                            </div>
                        </div>

                        <div id="dynamic-table-container" data-url="<?= BASE_URL ?>gestion/admin">
                            <?php include __DIR__ . '/Partials/TablaTareas.php'; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div> <!-- Close .container-fluid -->
        
        <script src="<?= BASE_URL ?>js/DynamicTable.js?v=<?= time() ?>"></script>

        
        <div id="server-data" 
             data-flash-success="<?= htmlspecialchars(\App\Helpers\FlashHelper::get('success') ?? '') ?>"
             data-flash-error="<?= htmlspecialchars(\App\Helpers\FlashHelper::get('error') ?? '') ?>">
        </div>

<script src="<?= BASE_URL ?>js/libraries/toastr.min.js"></script>
<script src="<?= BASE_URL ?>js/libraries/sweetalert2.all.min.js"></script>
<script src="<?= BASE_URL ?>js/AdminTareas.js?v=<?= time() ?>"></script>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
