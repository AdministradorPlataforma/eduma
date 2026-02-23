<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <div class="container-fluid py-5 px-lg-5">
            
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <a href="<?= BASE_URL ?>perfil" class="text-decoration-none text-muted small fw-700 mb-2 d-inline-block">
                        <i class="bi bi-arrow-left me-1"></i> VOLVER AL PERFIL
                    </a>
                    <h2 class="fw-800 text-slate mb-1">Sesiones Activas</h2>
                    <p class="text-muted small mb-0">Gestiona los dispositivos donde tienes tu sesión iniciada.</p>
                </div>
                <div class="icon-sq bg-soft-indigo mb-2">
                    <i class="bi bi-shield-lock text-indigo"></i>
                </div>
            </div>

            <?php if (empty($sessions)): ?>
                <div class="glass-panel p-5 text-center">
                    <div class="icon-sq-lg bg-light text-muted mx-auto mb-4">
                        <i class="bi bi-shield-slash"></i>
                    </div>
                    <h4 class="fw-800 text-slate mb-2">Sin registros activos</h4>
                    <p class="text-muted small max-w-sm mx-auto">No se han encontrado registros de sesiones activas en la base de datos.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($sessions as $session): ?>
                        <div class="col-12 col-xl-6">
                            <div class="glass-panel p-4 animate-up">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-sq bg-soft-indigo rounded-3 icon-sq-54">
                                            <?php 
                                                $ua = strtolower($session['user_agent']);
                                                if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
                                                    echo '<i class="bi bi-phone"></i>';
                                                } else {
                                                    echo '<i class="bi bi-laptop"></i>';
                                                }
                                            ?>
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <h5 class="fw-800 text-slate m-0"><?= $session['ip_address'] ?></h5>
                                                <?php if ($session['id'] === $current_sid): ?>
                                                    <span class="badge-soft badge-soft-success">Esta sesión</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-muted text-xs mb-0 text-truncate w-max-250" title="<?= htmlspecialchars($session['user_agent']) ?>">
                                                <?= htmlspecialchars($session['user_agent']) ?>
                                            </p>
                                            <div class="text-xs text-indigo fw-700 mt-2">
                                                <i class="bi bi-clock-history me-1"></i> Actividad: <?= date('d/m/Y H:i', (int)$session['last_activity']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($session['id'] !== $current_sid): ?>
                                        <button class="btn btn-soft-danger btn-sm px-3 rounded-pill fw-700 btn-revoke-session" data-sid="<?= $session['id'] ?>">
                                            Finalizar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?= \App\Helpers\CSRFHelper::csrfField(); ?>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>js/libraries/sweetalert2.all.min.js"></script>
<script src="<?= BASE_URL ?>js/Perfil.js?v=<?= time() ?>"></script>


<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
