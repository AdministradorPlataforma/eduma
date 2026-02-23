<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <!-- Reutilizamos estilos Premium -->
        <link rel="stylesheet" href="<?= BASE_URL ?>css/FormUsuario.css?v=<?= time() ?>">
        
        <div class="container-fluid py-4 flex-grow-1">

            <?= \App\Helpers\FlashHelper::alert('all'); ?>

            <div class="row">
                <div class="col-12 animate-up delay-1">
                    
                    <div class="premium-form-card p-5">
                        
                        <form action="<?= BASE_URL ?>permiso/editar/<?= $permiso['id'] ?>" method="POST" autocomplete="off">
                            <?= \App\Helpers\CSRFHelper::csrfField(); ?>

                            <!-- Header -->
                            <div class="form-header-simple">
                                <div>
                                    <h5 class="fw-800 m-0 text-slate fs-4">Editar Permiso</h5>
                                    <p class="text-muted small mb-0 mt-1">
                                        Definición de Acceso: <span class="fw-700 text-indigo"><?= $permiso['slug'] ?></span>
                                    </p>
                                </div>
                                <div class="d-flex gap-3 align-items-center">
                                    <a href="<?= BASE_URL ?>permiso" class="btn btn-premium-secondary btn-round px-4">
                                        Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-premium-primary btn-round px-5 py-2 d-flex align-items-center gap-2">
                                        <i class="bi bi-check-lg"></i> Guardar Cambios
                                    </button>
                                </div>
                            </div>

                            <div class="row g-5 justify-content-center">
                                
                                <div class="col-lg-8">
                                    
                                    <div class="alert alert-soft-info d-flex align-items-start gap-3 p-3 rounded-4 text-sm mb-5">
                                        <div class="icon-sq bg-white text-indigo rounded-circle shadow-sm flex-shrink-0 avatar-sq-sm">
                                            <i class="bi bi-shield-lock-fill"></i>
                                        </div>
                                        <div>
                                            <strong class="text-indigo">Zona Técnica</strong><br>
                                            <span class="text-muted">Los permisos definen las capacidades atómicas del sistema. Modificar el "Slug" puede romper funcionalidades si el código fuente lo referencia directamente.</span>
                                        </div>
                                    </div>

                                    <div class="row g-4">
                                        <div class="col-12">
                                            <label class="form-label">Identificador Único (Slug)</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-soft-indigo border-0 text-indigo fw-bold ps-3 pe-3 input-group-text-premium">
                                                    <i class="bi bi-key-fill"></i>
                                                </span>
                                                <input type="text" name="slug" class="form-control form-control-lg form-control-glass ps-3" 
                                                       required value="<?= $permiso['slug'] ?>" placeholder="ej: usuario.crear">
                                            </div>
                                            <div class="form-text small mt-2 ms-2">Formato recomendado: <code>entidad.accion</code> (todo minúsculas).</div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Descripción Funcional</label>
                                            <textarea name="descripcion" class="form-control form-control-lg form-control-glass" rows="4" 
                                                      placeholder="Explique qué permite hacer este permiso..."><?= $permiso['descripcion'] ?></textarea>
                                        </div>
                                    </div>

                                </div>
                            </div>

                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
