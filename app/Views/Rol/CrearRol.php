<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <div class="container-fluid py-4">

            <?= \App\Helpers\FlashHelper::alert('all'); ?>

            <form action="<?= BASE_URL ?>rol/crear" method="POST" autocomplete="off">
                <?= \App\Helpers\CSRFHelper::csrfField(); ?>

                <div class="row">
                    <!-- Columna Izquierda: Datos del Rol -->
                    <div class="col-lg-4 mb-4">
                         <div class="glass-panel p-4 animate-up delay-1 h-100">
                            <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom border-light">
                                <div class="icon-sq bg-soft-cyan"><i class="bi bi-person-badge"></i></div>
                                <div>
                                    <h5 class="fw-800 m-0 text-slate">Datos del Rol</h5>
                                    <p class="text-muted small mb-0">Información básica</p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-muted small fw-700">Nombre del Rol</label>
                                <input type="text" name="nombre" class="form-control form-control-lg form-control-glass" required placeholder="Ej: Secretaria Académica">
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-muted small fw-700">Descripción</label>
                                <textarea name="descripcion" class="form-control form-control-glass" rows="4" placeholder="Breve descripcíón de las responsabilidades..."></textarea>
                            </div>

                            <div class="mt-auto pt-4">
                                <button type="submit" class="btn btn-premium-primary btn-round w-100 py-3 shadow-lg">Guardar Rol</button>
                                <a href="<?= BASE_URL ?>rol" class="btn btn-premium-secondary btn-round w-100 py-2 mt-3">Cancelar</a>
                            </div>
                         </div>
                    </div>

                    <!-- Columna Derecha: Selector de Permisos -->
                     <div class="col-lg-8 mb-4">
                        <div class="glass-panel p-4 animate-up delay-2 h-100">
                             <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-light">
                                <div>
                                    <h5 class="fw-800 m-0 text-slate">Permisos Asignados</h5>
                                    <p class="text-muted small mb-0">Seleccione las acciones permitidas para este rol.</p>
                                </div>
                                <button type="button" class="btn btn-premium-secondary btn-sm btn-round px-3" id="btnToggleAll">Marcar Todos</button>
                            </div>

                            <div class="row g-3 max-h-600">
                                <?php 
                                // Agrupar permisos por Entidad (prefijo del slug) para mejor UX
                                $permisosAgrupados = [];
                                foreach ($permisos as $p) {
                                    $parts = explode('.', $p['slug']);
                                    $entidad = ucfirst($parts[0]);
                                    $permisosAgrupados[$entidad][] = $p;
                                }
                                
                                foreach ($permisosAgrupados as $entidad => $perms):
                                ?>
                                <div class="col-12">
                                    <h6 class="fw-800 text-cyan mb-3 mt-2"><i class="bi bi-layers-half me-2"></i><?= $entidad ?></h6>
                                    <div class="row g-3">
                                        <?php foreach ($perms as $p): ?>
                                        <div class="col-md-6 col-xl-4">
                                            <label class="permiso-card-item">
                                                <input type="checkbox" name="permisos[]" value="<?= $p['id'] ?>" class="permiso-checkbox">
                                                <div class="permiso-content">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <span class="badge bg-soft-cyan text-cyan mb-2"><?= $p['slug'] ?></span>
                                                        <i class="bi bi-circle permiso-check"></i>
                                                    </div>
                                                    <p class="small text-muted mb-0 lh-sm"><?= $p['descripcion'] ?></p>
                                                </div>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <hr class="border-light opacity-50 my-4">
                                </div>
                                <?php endforeach; ?>
                            </div>

                        </div>
                     </div>
                </div>
            </form>

        </div>
    </div>

<script src="<?= BASE_URL ?>js/Rol.js?v=<?= time() ?>"></script>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
