<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <!-- Assets específicos de la vista -->
        <link rel="stylesheet" href="<?= BASE_URL ?>css/Perfil.css?v=<?= time() ?>">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/PerfilDecor.css?v=<?= time() ?>">

        <div class="container-fluid py-5">
            
            <!-- Perfil Header Premium -->
            <div class="profile-header-card mb-5 animate-up">
                <div class="profile-cover"></div>
                <div class="px-5 pb-4">
                    <div class="d-flex align-items-end profile-avatar-section">
                        <div class="avatar-container">
                            <div class="avatar-main">
                                <?php 
                                    $n = $user['nombre'] ?? 'U';
                                    $a = $user['apellido'] ?? '';
                                    echo strtoupper(substr($n, 0, 1) . ($a ? substr($a, 0, 1) : ''));
                                ?>
                            </div>
                            <button type="button" class="btn-avatar-edit">
                                <i class="bi bi-camera-fill"></i>
                            </button>
                        </div>
                        <div class="ms-4 mb-2 profile-meta">
                            <h3 class="fw-900 text-slate mb-1"><?= htmlspecialchars(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? '')) ?></h3>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge-soft badge-soft-indigo px-3">USUARIO REGISTRADO</span>
                                <span class="text-muted small fw-600"><i class="bi bi-shield-check me-1"></i> Perfil Verificado</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    
                    <!-- Alertas Flash -->
                    <?= \App\Helpers\FlashHelper::alert('all'); ?>

                    <form action="<?= BASE_URL ?>perfil/actualizar" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\CSRFHelper::getToken() ?>">

                        <!-- Grid de Información -->
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <!-- Sección Datos Personales -->
                                <div class="glass-panel p-4 animate-up delay-1">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5 class="fw-800 m-0 text-slate">Detalles Personales</h5>
                                        <div class="icon-sq bg-soft-indigo"><i class="bi bi-person-badge"></i></div>
                                    </div>

                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="form-label-premium">Nombre(s)</label>
                                            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($user['nombre'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label-premium">Apellido(s)</label>
                                            <input type="text" name="apellido" class="form-control" value="<?= htmlspecialchars($user['apellido'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label-premium">Correo Electrónico</label>
                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label-premium">Estado de la Cuenta</label>
                                            <div class="info-block-premium d-flex align-items-center gap-2 bg-light opacity-75">
                                                <div class="status-dot pulse-green"></div>
                                                <span class="fw-700">Activo (No editable)</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-5 d-flex justify-content-end">
                                        <button type="submit" class="btn btn-premium-primary btn-round px-5 py-3 shadow-lg">
                                            <i class="bi bi-check-circle me-2"></i> Guardar Cambios
                                        </button>
                                    </div>
                                </div>

                                <!-- Sección Seguridad -->
                                <div class="glass-panel p-4 mt-4 animate-up delay-2">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5 class="fw-800 m-0 text-slate">Seguridad de la Cuenta</h5>
                                        <div class="icon-sq bg-soft-rose"><i class="bi bi-shield-lock"></i></div>
                                    </div>
                                    
                                    <div class="alert alert-soft-warning border-0 rounded-4 d-flex align-items-center gap-3 mb-4">
                                        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                                        <div>
                                            <div class="fw-700">Autenticación de dos pasos</div>
                                            <div class="small opacity-75">Refuerce la seguridad de su cuenta activando el 2FA.</div>
                                        </div>
                                        <button type="button" class="btn btn-premium-warning btn-sm btn-round px-4">Activar</button>
                                    </div>

                                    <div class="list-group list-group-flush gap-4 mt-2">
                                        <div class="password-change-grid">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label class="form-label-premium">Contraseña Actual (Requerida para cambios)</label>
                                                    <div class="position-relative">
                                                        <input type="password" name="password_actual" class="form-control" placeholder="Contraseña Actual">
                                                        <i class="bi bi-shield-lock position-absolute top-50 end-0 translate-middle-y me-3 opacity-25"></i>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label-premium">Nueva Contraseña</label>
                                                    <input type="password" name="password_nueva" class="form-control" placeholder="Mín. 8 caracteres">
                                                    <div class="text-xs text-muted mt-2 ps-2">
                                                        <i class="bi bi-info-circle me-1"></i> Debe contener mayúsculas, minúsculas y números.
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label-premium">Confirmar Nueva Contraseña</label>
                                                    <input type="password" name="password_confirmar" class="form-control" placeholder="Repite la contraseña">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="list-group-item bg-transparent border-bottom-0 px-0 d-flex align-items-center justify-content-between pt-4 border-top">
                                            <div>
                                                <div class="fw-700 text-slate">Sesiones Activas</div>
                                                <span class="text-muted small">2 dispositivos conectados actualmente</span>
                                            </div>
                                            <a href="<?= BASE_URL ?>perfil/sesiones" class="btn btn-premium-secondary btn-sm btn-round px-4">Gestionar</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="col-lg-4">
                            <!-- Estadísticas Rápidas -->
                            <div class="glass-panel p-4 animate-up delay-3 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="fw-800 m-0 text-slate">Actividad</h5>
                                    <div class="icon-sq bg-soft-emerald"><i class="bi bi-graph-up-arrow"></i></div>
                                </div>
                                
                                <div class="stat-card-premium mb-3">
                                    <div class="stat-icon bg-indigo"><i class="bi bi-clock-history"></i></div>
                                    <div class="stat-content">
                                        <div class="stat-value">124 hrs</div>
                                        <div class="stat-label">Tiempo en plataforma</div>
                                    </div>
                                </div>

                                <div class="stat-card-premium mb-3">
                                    <div class="stat-icon bg-emerald"><i class="bi bi-journal-check"></i></div>
                                    <div class="stat-content">
                                        <div class="stat-value">12</div>
                                        <div class="stat-label">Cursos Completados</div>
                                    </div>
                                </div>

                                <div class="stat-card-premium mb-4">
                                    <div class="stat-icon bg-amber"><i class="bi bi-award"></i></div>
                                    <div class="stat-content">
                                        <div class="stat-value">5</div>
                                        <div class="stat-label">Insignias Obtenidas</div>
                                    </div>
                                </div>

                                <hr class="opacity-10 my-4">

                                <h6 class="fw-700 text-slate mb-3">Accesos Directos</h6>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-premium-secondary btn-round text-start w-100 px-4 py-3">
                                        <i class="bi bi-cloud-download me-2"></i> Mis Certificados
                                    </button>
                                    <button class="btn btn-premium-secondary btn-round text-start w-100 px-4 py-3">
                                        <i class="bi bi-gear me-2"></i> Ajustes de Notificaciones
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>


<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
