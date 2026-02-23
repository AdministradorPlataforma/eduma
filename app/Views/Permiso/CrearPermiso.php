<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <div class="container-fluid py-4">

            <?= \App\Helpers\FlashHelper::alert('all'); ?>

            <div class="glass-panel p-5 animate-up delay-1 col-lg-6 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom border-light">
                    <div>
                        <h5 class="fw-800 m-0 text-slate">Registrar Permiso</h5>
                        <p class="text-muted small mb-0">Defina una nueva regla de acceso.</p>
                    </div>
                    <div class="icon-sq bg-soft-rose"><i class="bi bi-key"></i></div>
                </div>

                <form action="<?= BASE_URL ?>permiso/crear" method="POST" autocomplete="off">
                    <?= \App\Helpers\CSRFHelper::csrfField(); ?>

                    <div class="mb-4">
                        <label class="form-label text-muted small fw-700">Identificador (Slug)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 rounded-start-3 ps-3 text-muted border-light">
                                <i class="bi bi-terminal"></i>
                            </span>
                            <input type="text" name="slug" id="slugInput" class="form-control form-control-lg form-control-glass border-start-0 ps-2" required placeholder="entidad.accion" autocapitalize="none">
                        </div>
                        <div class="form-text small mt-2">
                             Debe seguir el formato <code class="text-rose">entidad.accion</code> (ej: <i>usuario.crear</i>). 
                             Solo minúsculas.
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="form-label text-muted small fw-700">Descripción Funcional</label>
                        <textarea name="descripcion" class="form-control form-control-glass" rows="3" required placeholder="Describe qué permite hacer este permiso..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-3 border-top pt-4 border-light">
                        <a href="<?= BASE_URL ?>permiso" class="btn btn-premium-secondary btn-round px-4">Cancelar</a>
                        <button type="submit" class="btn btn-premium-primary btn-round px-5 shadow-lg">Guardar Permiso</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
