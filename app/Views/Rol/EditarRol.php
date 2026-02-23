<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <!-- Reutilizamos el estilo Premium de Formularios -->
        <link rel="stylesheet" href="<?= BASE_URL ?>css/FormUsuario.css?v=<?= time() ?>">
        
        <!-- Estilos específicos para el Grid de Permisos -->
        <div class="container-fluid py-4 flex-grow-1">

            <?= \App\Helpers\FlashHelper::alert('all'); ?>

            <div class="row">
                <div class="col-12 animate-up delay-1">
                    
                    <div class="premium-form-card p-5">
                        
                        <form action="<?= BASE_URL ?>rol/editar/<?= $rol['id'] ?>" method="POST" autocomplete="off">
                            <?= \App\Helpers\CSRFHelper::csrfField(); ?>

                            <!-- Header -->
                            <div class="form-header-simple">
                                <div>
                                    <h5 class="fw-800 m-0 text-slate fs-4">Editar Rol</h5>
                                    <p class="text-muted small mb-0 mt-1">Identificador: <span class="fw-700 text-indigo">#<?= str_pad((string)$rol['id'], 3, '0', STR_PAD_LEFT) ?></span></p>
                                </div>
                                <div class="d-flex gap-3 align-items-center">
                                    <a href="<?= BASE_URL ?>rol" class="btn btn-premium-secondary btn-round px-4">
                                        Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-premium-primary btn-round px-5 py-2 d-flex align-items-center gap-2">
                                        <i class="bi bi-check-lg"></i> Guardar Cambios
                                    </button>
                                </div>
                            </div>

                            <div class="row g-5">
                                <!-- Columna Izquierda: Datos Básicos -->
                                <div class="col-lg-4">
                                    <div class="pe-lg-4 border-end-lg border-light h-100">
                                        <div class="form-section-title"><i class="bi bi-info-circle me-2 text-indigo"></i>Datos del Rol</div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Nombre del Rol</label>
                                            <input type="text" name="nombre" class="form-control form-control-lg form-control-glass fw-bold text-slate" 
                                                   required value="<?= $rol['nombre'] ?>" placeholder="Ej: Gestor Académico">
                                            <div class="form-text small">Debe ser único y descriptivo.</div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label">Descripción</label>
                                            <textarea name="descripcion" class="form-control form-control-glass" rows="6" 
                                                      placeholder="Describa las responsabilidades de este rol..."><?= $rol['descripcion'] ?></textarea>
                                        </div>

                                        <div class="alert alert-soft-info d-flex align-items-start gap-3 p-3 rounded-4 text-sm mt-5">
                                            <i class="bi bi-shield-exclamation mt-1 fs-5"></i>
                                            <div>
                                                <strong>Importante:</strong><br>
                                                Los cambios en los permisos afectarán inmediatamente a todos los usuarios que tengan este rol asignado.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Columna Derecha: Selector de Permisos -->
                                <div class="col-lg-8">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="form-section-title m-0"><i class="bi bi-shield-lock me-2 text-indigo"></i>Permisos del Sistema</div>
                                        <button type="button" class="btn btn-premium-secondary btn-sm btn-round px-3 fw-700" id="btnToggleAll">
                                            <i class="bi bi-check-all me-1"></i> Marcar/Desmarcar Todo
                                        </button>
                                    </div>

                                    <div class="permisos-container mt-4">
                                        <?php 
                                        $permisosAgrupados = [];
                                        foreach ($permisos as $p) {
                                            $parts = explode('.', $p['slug']);
                                            $entidad = ucfirst($parts[0] ?? 'General');
                                            $permisosAgrupados[$entidad][] = $p;
                                        }
                                        ksort($permisosAgrupados); // Ordenar categorías alfabéticamente
                                        
                                        foreach ($permisosAgrupados as $entidad => $perms):
                                            // Verificar si todos están marcados en este grupo para el botón de grupo (opcional, JS puede manejarlo)
                                        ?>
                                        <div class="permiso-group-wrapper">
                                            <div class="permiso-group-title">
                                                <span><?= $entidad ?></span>
                                                <button type="button" class="toggle-group-btn" data-group="<?= $entidad ?>">Inv</button>
                                            </div>
                                            
                                            <div class="permiso-grid">
                                                <?php foreach ($perms as $p): 
                                                    $isChecked = in_array($p['id'], $permisosAsignados);
                                                ?>
                                                <label class="permiso-card-item <?= $isChecked ? 'checked' : '' ?>">
                                                    <input type="checkbox" name="permisos[]" value="<?= $p['id'] ?>" 
                                                           class="form-check-input mt-1 d-none permiso-checkbox"
                                                           <?= $isChecked ? 'checked' : '' ?>
                                                           data-group="<?= $entidad ?>">
                                                    
                                                    <div class="d-flex flex-column flex-grow-1">
                                                        <span class="fw-700 text-dark small mb-1"><?= $p['slug'] ?></span>
                                                        <span class="text-muted small lh-sm text-sm-8"><?= $p['descripcion'] ?></span>
                                                    </div>

                                                    <i class="bi <?= $isChecked ? 'bi-check-circle-fill' : 'bi-circle' ?> permiso-check"></i>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                </div>
                            </div>

                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

<script src="<?= BASE_URL ?>js/Rol.js?v=<?= time() ?>"></script>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
