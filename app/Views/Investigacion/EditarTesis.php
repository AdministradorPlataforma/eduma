<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>

<!-- Sidebar Independiente -->
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<!-- Bloque Maestro -->
<div class="main-layout">
    
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/FormTesis.css?v=<?= time() ?>">
        
        <div class="container-fluid py-4">

            <!-- Alertas Flash -->
            <div class="col-12 mb-4">
                <?php if ($flash = \App\Helpers\FlashHelper::get('error')): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                        <div><?= $flash ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Formulario Glass Panel -->
            <div class="glass-panel p-5 animate-up delay-1 col-12 mb-5">
                <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom border-light">
                    <div>
                        <h5 class="fw-800 m-0 text-slate">Editar Tesis</h5>
                        <p class="text-muted small mb-0">Modifique la información de la tesis.</p>
                    </div>
                    <div class="icon-sq bg-soft-indigo"><i class="bi bi-pencil-square"></i></div>
                </div>

                <form action="<?= BASE_URL ?>investigacion/actualizar/<?= $tesis['id'] ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate autocomplete="off">
                    <?= \App\Helpers\CSRFHelper::csrfField(); ?>

                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <label for="titulo" class="form-label fw-bold text-slate">
                                <i class="bi bi-bookmark-fill me-2"></i>Título de la Tesis
                            </label>
                            <input type="text" name="titulo" id="titulo" class="form-control form-control-lg form-control-glass" required placeholder="Ingrese el título completo..." minlength="5" value="<?= htmlspecialchars($tesis['titulo']) ?>">
                            <div class="invalid-feedback">El título es obligatorio.</div>
                        </div>
                    </div>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold text-slate">
                                <i class="bi bi-upc-scan me-2"></i>Código (Autogenerado)
                            </label>
                            <input type="text" class="form-control form-control-lg bg-light" value="<?= htmlspecialchars($tesis['codigo'] ?? 'Pendiente') ?>" readonly disabled>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <!-- Estudiantes Section -->
                        <div class="col-12">
                            <label class="form-label fw-bold text-slate">
                                <i class="bi bi-mortarboard-fill me-2"></i>Estudiantes (Tesistas)
                            </label>
                            <div id="estudiantes-container" class="d-flex flex-column gap-2">
                                <!-- Rendered by JS -->
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-indigo mt-2" id="btn-add-estudiante">
                                <i class="bi bi-plus-circle me-1"></i> Agregar Estudiante
                            </button>
                        </div>

                        <!-- Docentes Section -->
                        <div class="col-12">
                            <label class="form-label fw-bold text-slate">
                                <i class="bi bi-person-workspace me-2"></i>Docentes (Tutores)
                            </label>
                            <div id="docentes-container" class="d-flex flex-column gap-2">
                                <!-- Rendered by JS -->
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-indigo mt-2" id="btn-add-docente">
                                <i class="bi bi-plus-circle me-1"></i> Agregar Docente
                            </button>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-12 col-md-6">
                            <label for="estado" class="form-label fw-bold text-slate">
                                <i class="bi bi-flag-fill me-2"></i>Estado Actual
                            </label>
                            <select name="estado" id="estado" class="form-select form-select-lg form-control-glass" required>
                                <option value="Pendiente" <?= $tesis['estado'] == 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                <option value="Aprobada" <?= $tesis['estado'] == 'Aprobada' ? 'selected' : '' ?>>Aprobada</option>
                                <option value="Rechazada" <?= $tesis['estado'] == 'Rechazada' ? 'selected' : '' ?>>Rechazada</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="descripcion" class="form-label fw-bold text-slate">
                            <i class="bi bi-text-paragraph me-2"></i>Resumen / Descripción
                        </label>
                        <textarea name="descripcion" id="descripcion" class="form-control form-control-lg form-control-glass" rows="4" placeholder="Breve descripción del alcance..."><?= htmlspecialchars($tesis['descripcion'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-5">
                        <label for="archivo" class="form-label fw-bold text-slate">
                            <i class="bi bi-file-earmark-arrow-up-fill me-2"></i>Actualizar Formulario Nº4 (Opcional)
                        </label>
                        <?php if (!empty($tesis['archivo_path'])): ?>
                            <div class="mb-2">
                                <a href="<?= BASE_URL . $tesis['archivo_path'] ?>" target="_blank" class="text-primary small fw-600"><i class="bi bi-file-earmark-check"></i> Documento Actual</a>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="archivo" id="archivo" class="form-control form-control-lg form-control-glass" accept=".pdf,.doc,.docx">
                        <div class="form-text small mt-2"><i class="bi bi-info-circle me-1"></i>Dejar vacío para mantener el actual. Formatos: PDF, Word. Máx 10MB.</div>
                    </div>

                    <div class="mb-5">
                        <label for="archivo_tesis" class="form-label fw-bold text-slate">
                            <i class="bi bi-file-earmark-text me-2"></i>Actualizar Archivo de Tesis (Documento Final)
                        </label>
                        <?php if (!empty($tesis['archivo_tesis_path'])): ?>
                            <div class="mb-2">
                                <a href="<?= BASE_URL . $tesis['archivo_tesis_path'] ?>" target="_blank" class="text-primary small fw-600"><i class="bi bi-file-earmark-check"></i> Tesis Actual</a>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="archivo_tesis" id="archivo_tesis" class="form-control form-control-lg form-control-glass" accept=".pdf,.doc,.docx">
                        <div class="form-text small mt-2"><i class="bi bi-info-circle me-1"></i>Dejar vacío para mantener el actual. Formatos: PDF, Word. Máx 50MB.</div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-3 border-top pt-4 border-light">
                        <a href="<?= BASE_URL ?>investigacion" class="btn btn-premium-secondary btn-round px-4">Cancelar</a>
                        <button type="submit" class="btn btn-premium-primary btn-round px-5 shadow-lg">Guardar Cambios</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

<link href="<?= BASE_URL ?>css/libraries/select2.min.css" rel="stylesheet" />

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>

<!-- Required for Select2 -->
<script src="<?= BASE_URL ?>js/libraries/select2.min.js"></script>

<script>
    window.isEditMode = true;
    window.BASE_URL = <?= json_encode(BASE_URL) ?>; 
    window.initialEstudiantes = <?= json_encode($tesis['estudiantes'] ?? []) ?>;
    window.initialDocentes = <?= json_encode($tesis['docentes'] ?? []) ?>;
</script>

<!-- Custom Logic (Common for Tesis Forms) -->
<script src="<?= BASE_URL ?>js/FormTesis.js?v=<?= time() ?>"></script>
