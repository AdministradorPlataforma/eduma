<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/FormUsuario.css?v=<?= time() ?>">

        <div class="container-fluid py-4 flex-grow-1">

            <!-- Alertas Flash -->
            <?= \App\Helpers\FlashHelper::alert('all'); ?>

            <div class="row w-100 m-0">
                <div class="col-12 p-0">
                    
                    <!-- Formulario Premium Card -->
                    <div class="premium-form-card p-5 animate-up delay-1">
                        
                        <!-- Simple Header -->
                        <div class="form-header-simple">
                            <div>
                                <h5 class="fw-800 m-0 text-slate fs-4">Editar Perfil</h5>
                                <p class="text-muted small mb-0 mt-1">Usuario: <span class="fw-700 text-indigo"><?= $usuario['username'] ?></span></p>
                            </div>
                            <div class="header-icon-simple">
                                <i class="bi bi-pencil-square"></i>
                            </div>
                        </div>

                        <!-- Form Body -->
                        <div class="mt-4">

                        <form action="<?= BASE_URL ?>usuario/editar/<?= $usuario['id'] ?>" method="POST" autocomplete="off">
                            <?= \App\Helpers\CSRFHelper::csrfField(); ?>

                            <div class="form-section-title">Información Personal</div>
                            
                            <div class="row g-4 mb-5">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" name="nombre" class="form-control form-control-lg form-control-glass" required value="<?= $usuario['nombre'] ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Apellido</label>
                                    <input type="text" name="apellido" class="form-control form-control-lg form-control-glass" required value="<?= $usuario['apellido'] ?>">
                                </div>
                            </div>

                            <div class="form-section-title">Credenciales de Acceso</div>

                            <div class="row g-4 mb-2">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Nombre de Usuario</label>
                                    <input type="text" name="username" class="form-control form-control-lg form-control-glass" required value="<?= $usuario['username'] ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Correo Electrónico</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-0 ps-0 text-muted"><i class="bi bi-envelope-at"></i></span>
                                        <input type="email" name="email" class="form-control form-control-lg form-control-glass ps-2" required value="<?= $usuario['email'] ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-soft-info d-flex align-items-start gap-3 p-3 mb-4 rounded-4 text-sm">
                                <i class="bi bi-shield-lock-fill mt-1 fs-5"></i>
                                <div>
                                    <strong>Seguridad:</strong><br>
                                    Deje los campos de contraseña en blanco si no desea realizar cambios.
                                </div>
                            </div>

                            <div class="row g-4 mb-5">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Nueva Contraseña</label>
                                    <input type="password" name="password" class="form-control form-control-lg form-control-glass" placeholder="••••••••">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Confirmar Contraseña</label>
                                    <input type="password" name="password_confirm" class="form-control form-control-lg form-control-glass" placeholder="••••••••">
                                </div>
                            </div>

                            <div class="form-section-title">Roles y Permisos</div>

                            <!-- Puesto / Rol -->
                             <div class="mb-4">
                                <label class="form-label">Rol del Sistema (Jerarquía)</label>
                                <select name="rol_id" class="form-select form-select-lg form-control-glass">
                                    <option value="">-- Sin Rol Asignado --</option>
                                    <?php foreach ($roles as $rol): 
                                        $selected = ($currentRolId == $rol['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $rol['id'] ?>" <?= $selected ?>><?= $rol['nombre'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Roles y Banderas -->
                            <div class="mb-5">
                                <label class="form-label d-block mb-3">Accesos Especiales</label>
                                <div class="d-flex gap-4 flex-wrap">
                                    <label class="custom-checkbox-card">
                                        <input type="checkbox" name="es_admin" value="1" <?= $usuario['es_admin'] ? 'checked' : '' ?>>
                                        <div class="card-content">
                                            <i class="bi bi-shield-check"></i>
                                            <span class="fw-700 small">Admin</span>
                                        </div>
                                    </label>

                                    <label class="custom-checkbox-card">
                                        <input type="checkbox" name="es_docente" value="1" <?= $usuario['es_docente'] ? 'checked' : '' ?>>
                                        <div class="card-content">
                                            <i class="bi bi-person-video3"></i>
                                            <span class="fw-700 small">Docente</span>
                                        </div>
                                    </label>

                                    <label class="custom-checkbox-card">
                                        <input type="checkbox" name="es_estudiante" value="1" <?= $usuario['es_estudiante'] ? 'checked' : '' ?>>
                                        <div class="card-content">
                                            <i class="bi bi-mortarboard"></i>
                                            <span class="fw-700 small">Estudiante</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                             <!-- Estado -->
                             <div class="mb-5 pt-4 border-top border-light">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <label class="fw-700 text-slate mb-1 d-block">Estado de la Cuenta</label>
                                        <p class="text-muted small mb-0">Controla el acceso al sistema para este usuario</p>
                                    </div>
                                    <div class="form-check form-switch form-switch-lg">
                                        <input class="form-check-input" type="checkbox" id="activoSwitch" name="activo" value="1" <?= ($usuario['activo'] ?? 1) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center pt-3">
                                <a href="<?= BASE_URL ?>usuario" class="btn btn-premium-secondary btn-round px-4 text-decoration-none">
                                    <i class="bi bi-arrow-left me-2"></i> Volver
                                </a>
                                <button type="submit" class="btn btn-premium-primary btn-round px-5 py-3 d-flex align-items-center gap-2">
                                    <i class="bi bi-check-lg"></i> Guardar Cambios
                                </button>
                            </div>

                        </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="<?= BASE_URL ?>js/Usuario.js?v=<?= time() ?>"></script>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
