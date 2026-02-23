<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/FormUsuario.css?v=<?= time() ?>">
        
        <div class="container-fluid py-4">

            <!-- Alertas Flash -->
            <div class="col-lg-8 mx-auto mb-4">
                <?= \App\Helpers\FlashHelper::alert('all'); ?>
            </div>

            <!-- Formulario Glass Panel -->
            <div class="glass-panel p-5 animate-up delay-1 col-lg-8 mx-auto mb-5">
                <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom border-light">
                    <div>
                        <h5 class="fw-800 m-0 text-slate">Registrar Nuevo Usuario</h5>
                        <p class="text-muted small mb-0">Complete la información para dar de alta un acceso.</p>
                    </div>
                    <div class="icon-sq bg-soft-indigo"><i class="bi bi-person-plus"></i></div>
                </div>

                <form action="<?= BASE_URL ?>usuario/crear" method="POST" autocomplete="off">
                    <?= \App\Helpers\CSRFHelper::csrfField(); ?>

                    <div class="row g-4 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label text-muted small fw-700">Nombre</label>
                            <input type="text" name="nombre" class="form-control form-control-lg form-control-glass" required placeholder="Ej: Juan">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label text-muted small fw-700">Apellido</label>
                            <input type="text" name="apellido" class="form-control form-control-lg form-control-glass" required placeholder="Ej: Pérez">
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label text-muted small fw-700">Nombre de Usuario</label>
                            <input type="text" name="username" class="form-control form-control-lg form-control-glass" required placeholder="Ej: jperez">
                            <div class="form-text small">Debe ser único en el sistema.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label text-muted small fw-700">Correo Electrónico</label>
                            <input type="email" name="email" class="form-control form-control-lg form-control-glass" required placeholder="nombre@correo.com">
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label text-muted small fw-700">Contraseña</label>
                            <input type="password" name="password" class="form-control form-control-lg form-control-glass" required placeholder="********">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label text-muted small fw-700">Confirmar Contraseña</label>
                            <input type="password" name="password_confirm" class="form-control form-control-lg form-control-glass" required placeholder="********">
                        </div>
                    </div>

                    <!-- Puesto / Rol -->
                    <div class="mb-5">
                        <label class="form-label text-muted small fw-700">Rol del Sistema</label>
                        <select name="rol_id" class="form-select form-select-lg form-control-glass">
                            <option value="">-- Sin Rol Asignado (Solo Flags) --</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?= $rol['id'] ?>"><?= $rol['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small mt-2">
                             El Rol define los permisos granulares. Las banderas abajo definen perfiles macro.
                        </div>
                    </div>

                    <!-- Roles y Banderas -->
                    <div class="mb-5">
                        <label class="form-label text-muted small fw-700 mb-3 d-block">Roles y Permisos Globales</label>
                        <div class="d-flex gap-4 flex-wrap">
                            <label class="custom-checkbox-card">
                                <input type="checkbox" name="es_admin" value="1">
                                <div class="card-content">
                                    <i class="bi bi-shield-lock-fill text-indigo mb-2"></i>
                                    <span class="fw-700">Administrador</span>
                                </div>
                            </label>

                            <label class="custom-checkbox-card">
                                <input type="checkbox" name="es_docente" value="1">
                                <div class="card-content">
                                    <i class="bi bi-person-video3 text-indigo mb-2"></i>
                                    <span class="fw-700">Docente</span>
                                </div>
                            </label>

                            <label class="custom-checkbox-card">
                                <input type="checkbox" name="es_estudiante" value="1">
                                <div class="card-content">
                                    <i class="bi bi-mortarboard-fill text-indigo mb-2"></i>
                                    <span class="fw-700">Estudiante</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-3 border-top pt-4 border-light">
                        <a href="<?= BASE_URL ?>usuario" class="btn btn-premium-secondary btn-round px-4">Cancelar</a>
                        <button type="submit" class="btn btn-premium-primary btn-round px-5 shadow-lg">Guardar Usuario</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

<script src="<?= BASE_URL ?>js/Usuario.js?v=<?= time() ?>"></script>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
