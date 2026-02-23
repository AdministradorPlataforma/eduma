<?php 
$bodyClass = 'auth-page';
include_once __DIR__ . '/../Layouts/Header.php'; 
?>
<link rel="stylesheet" href="<?= BASE_URL ?>css/AuthDecor.css">

<main class="auth-container container-fluid p-0 overflow-hidden">
    <div class="row g-0 vh-100">
        
        <!-- Centered Section -->
        <div class="col-lg-12 auth-form-section animate-fade-in d-flex align-items-center justify-content-center position-relative overflow-hidden">
            <!-- Background Decorations: Premium Mesh & Blobs -->
            <div class="decor-mesh"></div>
            <div class="decor-blob decor-blob-1"></div>
            <div class="decor-blob decor-blob-2"></div>
            <div class="decor-blob decor-blob-3"></div>

            <div class="w-90 position-relative max-w-420 z-5 auth-card animate-soft-reveal">
                
                <div class="brand-wrapper mb-3 text-center">
                    <img src="<?= BASE_URL ?>images/uma-completo.png?v=<?= time() ?>" alt="UMA Logo" class="brand-logo img-fluid mb-2 mx-auto brand-logo-login">
                </div>

                <!-- Flash Messages -->
                <?= \App\Helpers\FlashHelper::alert('error'); ?>
                <?= \App\Helpers\FlashHelper::alert('success'); ?>

                <form action="<?= BASE_URL ?>login" method="POST" class="mt-2">
                    <?= \App\Helpers\CSRFHelper::csrfField(); ?>

                    <div class="mb-2">
                        <label class="modern-label">Tu Usuario</label>
                        <input type="text" name="username" class="joy-input" placeholder="Nº de C.I." required autofocus>
                    </div>

                    <div class="mb-2">
                        <label class="modern-label">Contraseña</label>
                        <input type="password" name="password" class="joy-input" placeholder="Contraseña" required>
                    </div>

                    <div class="mb-2">
                        <label class="modern-label">Código de Seguridad</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="text" name="captcha" class="joy-input captcha-input captcha-field-compact" placeholder="Código" required autocomplete="off">
                            <img src="<?= BASE_URL ?>captcha/image" alt="Captcha" id="captchaImg" class="captcha-img rounded shadow-sm captcha-img-styled" title="Click para recargar">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <label class="d-flex align-items-center text-muted small user-select-none cursor-pointer">
                            <input type="checkbox" class="form-check-input me-2" name="remember">
                            <span>Recordarme</span>
                        </label>
                        <a href="<?= BASE_URL ?>password/forgot" class="link-joy small">¿Olvidaste tu clave?</a>
                    </div>

                    <button type="submit" class="btn btn-premium-primary btn-round w-100 py-3">
                        Ingresar ahora
                    </button>
                </form>

                <div class="mt-4 pt-3 text-center border-top">
                    <p class="small text-muted mb-1">¿Aún no tienes cuenta?</p>
                    <a href="#" class="link-joy fw-bold small">Contactar Admisiones</a>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- Footer is usually hidden in this design, but kept for scripts -->
<script src="<?= BASE_URL ?>js/Auth.js?v=<?= time() ?>"></script>
<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
