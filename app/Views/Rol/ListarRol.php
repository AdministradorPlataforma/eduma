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
                        <h4 class="fw-800 m-0 text-slate">Roles del Sistema</h4>
                        <p class="text-muted small mb-0">Gestión de perfiles de acceso y permisos asociados.</p>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <a href="<?= BASE_URL ?>rol/crear" class="btn btn-premium-primary btn-round px-4 shadow-sm">
                            <i class="bi bi-plus-lg me-2"></i> Nuevo Rol
                        </a>
                        <div class="icon-sq bg-soft-indigo"><i class="bi bi-person-badge"></i></div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-premium w-100" id="tablaRoles">
                        <thead>
                            <tr>
                                <th class="w-10">ID</th>
                                <th class="w-30">Nombre del Rol</th>
                                <th>Descripción</th>
                                <th class="text-end w-15">Opciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $r): ?>
                            <tr>
                                <td>
                                    <span class="text-muted fw-700 small">#<?= str_pad((string)$r['id'], 3, '0', STR_PAD_LEFT) ?></span>
                                </td>
                                <td>
                                    <div class="user-info-group">
                                        <div class="avatar-soft-wrap">
                                            <div class="avatar-initials avatar-initials-role">
                                                <i class="bi bi-shield-lock-fill"></i>
                                            </div>
                                        </div>
                                        <span class="fw-800 text-slate"><?= $r['nombre'] ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-muted small line-clamp-2"><?= $r['descripcion'] ?></div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-action-group">
                                        <?php if ($r['id'] != 1): ?>
                                        <a href="<?= BASE_URL ?>rol/editar/<?= $r['id'] ?>" class="btn btn-action-round btn-edit-soft" title="Editar">
                                            <i class="bi bi-pencil-fill small"></i>
                                        </a>
                                        <button type="button" class="btn btn-action-round btn-delete-soft btn-delete-rol" data-id="<?= $r['id'] ?>" title="Eliminar">
                                            <i class="bi bi-trash-fill small"></i>
                                        </button>
                                        <?php else: ?>
                                            <span class="badge badge-rol admin">SYSTEM</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?= \App\Helpers\CSRFHelper::csrfField(); ?>
        </div>

<script src="<?= BASE_URL ?>js/libraries/sweetalert2.all.min.js"></script>
<script src="<?= BASE_URL ?>js/Rol.js?v=<?= time() ?>"></script>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
