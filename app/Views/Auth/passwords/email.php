<?php 
$bodyClass = 'auth-page';
include_once __DIR__ . '/../../Layouts/Header.php'; 
?>
<link rel="stylesheet" href="<?= BASE_URL ?>css/AuthDecor.css">

<main class="auth-container container-fluid p-0 overflow-hidden">
    <div class="row g-0 vh-100">
        
        <div class="col-lg-12 auth-form-section animate-fade-in d-flex align-items-center justify-content-center position-relative overflow-hidden">
            <div class="decor-mesh"></div>
            <div class="decor-blob decor-blob-1"></div>
            
            <div class="w-90 position-relative max-w-420 z-5 auth-card animate-soft-reveal">
                
                <div class="brand-wrapper mb-4 text-center">
                    <img src="<?= BASE_URL ?>images/uma-completo.png?v=<?= time() ?>" alt="UMA Logo" class="brand-logo img-fluid mb-2 mx-auto brand-logo-login" style="max-height: 80px;">
                    <h5 class="fw-bold text-slate mt-2">Recuperar Acceso</h5>
                    <p class="text-muted small">Ingresa tu correo para recibir un enlace de restablecimiento.</p>
                </div>

                <?= \App\Helpers\FlashHelper::alert('error'); ?>
                <?= \App\Helpers\FlashHelper::alert('success'); ?>

                <form action="<?= BASE_URL ?>password/email" method="POST" class="mt-4">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                    <div class="mb-4">
                        <label class="modern-label">Correo Electrónico</label>
                        <input type="email" name="email" class="joy-input" placeholder="ejemplo@uma.edu.pe" required autofocus>
                    </div>

                    <button type="submit" class="btn btn-premium-primary btn-round w-100 py-3 mb-3">
                        <i class="bi bi-envelope-paper-fill me-2"></i> Enviar Enlace
                    </button>
                    
                    <div class="text-center">
                        <a href="<?= BASE_URL ?>login" class="link-joy small fw-bold">
                            <i class="bi bi-arrow-left me-1"></i> Volver al Login
                        </a>
                    </div>
                </form>

            </div>
        </div>

    </div>
</main>

<?php include_once __DIR__ . '/../../Layouts/Footer.php'; ?>
