<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/ListarPremium.css?v=<?= time() ?>">

        <div class="container-fluid py-4 flex-grow-1">
            
            <?= \App\Helpers\FlashHelper::alert('all'); ?>

            <div class="glass-panel p-5 animate-up delay-1 h-100">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h4 class="fw-800 m-0 text-slate">Catálogo de Permisos</h4>
                        <p class="text-muted small mb-0">Definición de acciones atómicas del sistema (RBAC).</p>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <a href="<?= BASE_URL ?>permiso/crear" class="btn btn-premium-primary btn-round px-4 shadow-sm">
                            <i class="bi bi-plus-lg me-2"></i> Nuevo Permiso
                        </a>
                        <div class="icon-sq bg-soft-rose"><i class="bi bi-key-fill"></i></div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-premium w-100" id="tablaPermisos">
                        <thead>
                            <tr>
                                <th class="w-10">ID</th>
                                <th class="w-35">Identificador (Slug)</th>
                                <th>Descripción</th>
                                <th class="text-end w-15">Opciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permisos as $p): ?>
                            <tr>
                                <td><span class="text-muted fw-700 small">#<?= $p['id'] ?></span></td>
                                <td>
                                    <div class="user-info-group">
                                        <div class="avatar-soft-wrap">
                                            <div class="avatar-initials avatar-initials-rbac">
                                                <i class="bi bi-terminal-fill"></i>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="code-slug"><?= $p['slug'] ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-slate fw-600 small"><?= $p['descripcion'] ?></div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-action-group">
                                        <a href="<?= BASE_URL ?>permiso/editar/<?= $p['id'] ?>" class="btn btn-action-round btn-edit-soft" title="Editar">
                                            <i class="bi bi-pencil-fill small"></i>
                                        </a>
                                        <button type="button" class="btn btn-action-round btn-delete-soft btn-delete-permiso" data-id="<?= $p['id'] ?>" title="Eliminar">
                                            <i class="bi bi-trash-fill small"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-5 pt-4 border-top opacity-50">
                    <span class="small fw-700 text-muted">Total: <?= count($permisos) ?> permisos</span>
                </div>
            </div>
            <?= \App\Helpers\CSRFHelper::csrfField(); ?>
        </div>

<script src="<?= BASE_URL ?>js/libraries/sweetalert2.all.min.js"></script>
<script src="<?= BASE_URL ?>js/Permiso.js?v=<?= time() ?>"></script>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
