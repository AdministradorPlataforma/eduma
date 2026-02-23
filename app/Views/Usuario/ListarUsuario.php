<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>

<!-- Sidebar Independiente -->
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<!-- Bloque Maestro -->
<div class="main-layout">
    
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/ListarUsuario.css?v=<?= time() ?>">

        <div class="container-fluid py-4 flex-grow-1">

            <!-- Alertas Flash -->
            <?= \App\Helpers\FlashHelper::alert('success'); ?>

            <!-- Tabla de Usuarios Glass Panel -->
            <div class="glass-panel p-5 animate-up delay-1 h-100">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h4 class="fw-800 m-0 text-slate">Cuentas Registradas</h4>
                        <p class="text-muted small mb-0">
                            <span id="total-count"><?= number_format($totalUsuarios ?? 0) ?></span> usuarios en el sistema
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <a href="<?= BASE_URL ?>usuario/crear" class="btn btn-premium-primary btn-round px-4 shadow-sm">
                            <i class="bi bi-plus-lg me-2"></i> Nuevo Usuario
                        </a>
                        <div class="icon-sq bg-soft-indigo"><i class="bi bi-shield-lock"></i></div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="usuarios-table" class="table table-premium w-100 no-datatable"
                           data-ajax-url="<?= BASE_URL ?>usuario/datatable"
                           data-base-url="<?= BASE_URL ?>"
                           data-csrf-token="<?= \App\Helpers\CSRFHelper::generateToken() ?>">
                        <thead>
                            <tr>
                                <th>Usuario / Identidad</th>
                                <th>Correo Electrónico</th>
                                <th>Origen</th>
                                <th>Rol del Sistema</th>
                                <th>Estado</th>
                                <th class="text-end">Opciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- DataTables cargará los datos via AJAX -->
                        </tbody>
                    </table>
                </div>

            </div>
        </div>



<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
