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

            <form action="<?= BASE_URL ?>investigacion/guardar" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate autocomplete="off">
                <?= \App\Helpers\CSRFHelper::csrfField(); ?>
                
                <div class="row">
                    <!-- Left Column: Main Info -->
                    <div class="col-lg-8 mb-4">
                        
                        <!-- Panel 1: Detalles de la Tesis -->
                        <div class="glass-panel p-4 mb-4 animate-up delay-1">
                            <div class="d-flex align-items-center mb-4 border-bottom pb-3 border-light">
                                <div class="icon-sq bg-soft-indigo me-3"><i class="bi bi-journal-bookmark-fill"></i></div>
                                <div>
                                    <h5 class="fw-bold m-0 text-slate">Detalles del Proyecto</h5>
                                    <p class="text-muted text-xs mb-0">Información básica de la tesis</p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="titulo" class="form-label fw-bold text-slate">Título de la Tesis</label>
                                <input type="text" name="titulo" id="titulo" class="form-control form-control-lg form-control-glass fs-5 fw-600" required placeholder="Ingrese el título..." minlength="5" style="min-height: 60px;">
                                <div class="invalid-feedback">El título es obligatorio.</div>
                            </div>

                            <div class="mb-4">
                                <label for="descripcion" class="form-label fw-bold text-slate">Resumen / Alcance</label>
                                <textarea name="descripcion" id="descripcion" class="form-control form-control-lg form-control-glass" rows="5" placeholder="Describa brevemente el objetivo del trabajo..."></textarea>
                            </div>
                        </div>

                        <!-- Panel 2: Documentación -->
                        <div class="glass-panel p-4 mb-4 animate-up delay-2">
                            <div class="d-flex align-items-center mb-4 border-bottom pb-3 border-light">
                                <div class="icon-sq bg-soft-success me-3 text-success" style="background: rgba(16, 185, 129, 0.1) !important;"><i class="bi bi-file-earmark-pdf-fill"></i></div>
                                <div>
                                    <h5 class="fw-bold m-0 text-slate">Documentación</h5>
                                    <p class="text-muted text-xs mb-0">Adjuntar archivos requeridos</p>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <label for="archivo" class="form-label fw-bold text-slate">Formulario Nº4 (Anteproyecto)</label>
                                <div class="p-3 bg-light rounded-3 border border-dashed text-center position-relative hover-shadow transition-all">
                                    <i class="bi bi-cloud-arrow-up fs-2 text-indigo mb-2 d-block"></i>
                                    <input type="file" name="archivo" id="archivo" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" accept=".pdf,.doc,.docx" onchange="document.getElementById('file-name-display').innerText = this.files[0] ? this.files[0].name : 'Seleccionar archivo'">
                                    <span class="fw-bold text-dark d-block" id="file-name-display">Haz clic para subir formulario</span>
                                    <span class="text-muted text-xs">PDF o Word. Máx 10MB.</span>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label for="archivo_tesis" class="form-label fw-bold text-slate">Archivo de Tesis (Documento Final)</label>
                                <div class="p-3 bg-light rounded-3 border border-dashed text-center position-relative hover-shadow transition-all">
                                    <i class="bi bi-file-earmark-text fs-2 text-primary mb-2 d-block"></i>
                                    <input type="file" name="archivo_tesis" id="archivo_tesis" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" accept=".pdf,.doc,.docx" onchange="document.getElementById('tesis-name-display').innerText = this.files[0] ? this.files[0].name : 'Seleccionar archivo'">
                                    <span class="fw-bold text-dark d-block" id="tesis-name-display">Haz clic para subir tesis</span>
                                    <span class="text-muted text-xs">PDF o Word. Máx 50MB.</span>
                                </div>
                            </div>
                        </div>

                </div>

                <!-- Right Column: Participants & Settings -->
                <div class="col-lg-4 mb-4">
                    
                    <!-- Panel 3: Equipo -->
                    <div class="glass-panel p-4 mb-4 animate-up delay-2">
                        <div class="d-flex align-items-center mb-4 border-bottom pb-3 border-light">
                            <div class="icon-sq bg-soft-primary me-3 text-primary" style="background: rgba(59, 130, 246, 0.1) !important;"><i class="bi bi-people-fill"></i></div>
                            <div>
                                <h5 class="fw-bold m-0 text-slate">Equipo de Trabajo</h5>
                                <p class="text-muted text-xs mb-0">Autores y Tutores</p>
                            </div>
                        </div>

                        <!-- Estudiantes -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-slate d-flex justify-content-between align-items-center">
                                <span>Estudiantes</span>
                                <span class="badge bg-soft-primary text-primary rounded-pill px-2">Tesistas</span>
                            </label>
                            <div id="estudiantes-container" class="d-flex flex-column gap-2 mb-2">
                                <!-- Dynamic Rows -->
                            </div>
                            <button type="button" class="btn btn-sm btn-light border-primary text-primary w-100 fw-bold border-dashed" id="btn-add-estudiante">
                                <i class="bi bi-plus-lg me-1"></i> Agregar Estudiante
                            </button>
                        </div>

                        <hr class="border-light my-4">

                        <!-- Docentes -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-slate d-flex justify-content-between align-items-center">
                                <span>Docentes</span>
                                <span class="badge bg-soft-indigo text-indigo rounded-pill px-2">Tutores</span>
                            </label>
                            <div id="docentes-container" class="d-flex flex-column gap-2 mb-2">
                                <!-- Dynamic Rows -->
                            </div>
                            <button type="button" class="btn btn-sm btn-light border-indigo text-indigo w-100 fw-bold border-dashed" id="btn-add-docente">
                                <i class="bi bi-plus-lg me-1"></i> Agregar Tutor
                            </button>
                        </div>
                    </div>

                    <!-- Panel 4: Estado y Acciones -->
                    <div class="glass-panel p-4 animate-up delay-3">
                         <div class="mb-4">
                            <label class="form-label fw-bold text-slate">Estado Inicial</label>
                            <div class="p-3 bg-soft-success rounded-3 text-success fw-bold d-flex align-items-center">
                                <i class="bi bi-check-circle-fill me-2"></i> Aprobada
                            </div>
                            <input type="hidden" name="estado" value="Aprobada">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-premium-primary btn-round shadow-lg py-3">
                                <i class="bi bi-save me-2"></i> Registrar Tesis
                            </button>
                            <a href="<?= BASE_URL ?>investigacion" class="btn btn-light btn-round py-2 text-muted border-0">
                                Cancelar
                            </a>
                        </div>
                    </div>

                </div> <!-- End Row -->
            </form>
        </div>
    </div>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>

<script>
    window.isEditMode = false;
    // Forzamos la raiz relativa si BASE_URL falla
    window.BASE_URL = <?= json_encode(BASE_URL) ?>; 
</script>

<!-- Custom Logic -->
<script src="<?= BASE_URL ?>js/FormTesis.js?v=<?= time() ?>"></script>
