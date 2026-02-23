<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>

<!-- Sidebar Independiente -->
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<!-- Bloque Maestro -->
<div class="main-layout">
    
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/ListarTesis.css?v=<?= time() ?>">
        
        <div class="container-fluid py-4 flex-grow-1">

            <!-- Alertas Flash -->
            <?php if ($flash = \App\Helpers\FlashHelper::get('success')): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center">
                    <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                    <div><?= $flash ?></div>
                </div>
            <?php endif; ?>

            <!-- Tabla de Tesis Glass Panel -->
            <div class="glass-panel p-5 animate-up delay-1 h-100">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h4 class="fw-800 m-0 text-slate">Tesis</h4>
                        <p class="text-muted small mb-0">
                            <span><?= !empty($tesis) ? count($tesis) : 0 ?></span> tesis registradas
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <a href="<?= BASE_URL ?>investigacion/exportar" class="btn btn-premium-secondary btn-round shadow-sm px-4">
                            <i class="bi bi-file-earmark-excel me-2"></i> Exportar Excel
                        </a>
                        <a href="<?= BASE_URL ?>investigacion/registrar" class="btn btn-premium-primary btn-round px-4 shadow-sm" style="background: linear-gradient(135deg, var(--accent-indigo) 0%, var(--accent-violet) 100%); color: white; border: none; padding: 0.6rem 1.4rem; border-radius: 50px;">
                            <i class="bi bi-plus-lg me-2"></i> Nueva Tesis
                        </a>
                        <div class="icon-sq bg-soft-indigo"><i class="bi bi-mortarboard"></i></div>
                    </div>
                </div>

                <div class="table-responsive" id="dynamic-table-container" data-url="<?= BASE_URL ?>investigacion">
                    <?php include __DIR__ . '/Partials/TablaTesis.php'; ?>
                </div>

            </div>
            <!-- Pagination is now part of the partial -->
        </div>

<!-- Dynamic Table Script -->
<script src="<?= BASE_URL ?>js/DynamicTable.js?v=<?= time() ?>"></script>


        </div>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
