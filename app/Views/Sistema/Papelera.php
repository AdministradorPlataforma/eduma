<?php 
$pageTitle = 'Papelera de Reciclaje';
include_once __DIR__ . '/../Layouts/Header.php'; 
?>

<link rel="stylesheet" href="<?= BASE_URL ?>css/Escritorio.css?v=<?= time() ?>">

<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <div class="container-fluid py-5 px-lg-5">

            <div class="d-flex align-items-center justify-content-between mb-5 animate-up">
                <div>
                    <h2 class="fw-900 text-slate mb-1">Papelera de Reciclaje</h2>
                    <p class="text-muted mb-0">Restaura o elimina permanentemente registros eliminados lógicamente.</p>
                </div>
                <div class="icon-sq bg-soft-rose rounded-4 text-rose">
                    <i class="bi bi-trash3-fill fs-3"></i>
                </div>
            </div>

            <!-- Tesis Eliminadas -->
            <div class="card glass-card-system border-0 shadow-lg mb-5 animate-up delay-1">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="fw-800 text-slate d-flex align-items-center">
                        <i class="bi bi-journal-bookmark-fill me-2 text-indigo"></i> Tesis en Papelera
                        <span class="badge bg-soft-indigo text-indigo ms-2 rounded-pill"><?= count($tesis) ?></span>
                    </h5>
                </div>
                <div class="card-body px-4">
                    <div class="table-responsive">
                        <table class="table table-premium no-datatable align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Título</th>
                                    <th>Cód.</th>
                                    <th>Fecha Eliminación</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tesis)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted fst-italic">No hay tesis eliminadas.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tesis as $t): ?>
                                        <tr>
                                            <td class="ps-4 fw-600"><?= htmlspecialchars($t['titulo']) ?></td>
                                            <td class="text-secondary"><?= $t['codigo'] ?? 'N/A' ?></td>
                                            <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($t['deleted_at'])) ?></td>
                                            <td class="text-end pe-4">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <a href="<?= BASE_URL ?>recycle-bin/restore?type=tesis&id=<?= $t['id'] ?>" class="btn btn-action-round text-success bg-soft-success" title="Restaurar">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </a>
                                                    <button onclick="confirmPurge('tesis', <?= $t['id'] ?>)" class="btn btn-action-round text-danger bg-soft-danger" title="Eliminar Permanente">
                                                        <i class="bi bi-x-circle-fill"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tareas de Gestión Eliminadas -->
            <div class="card glass-card-system border-0 shadow-lg animate-up delay-2">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="fw-800 text-slate d-flex align-items-center">
                        <i class="bi bi-clipboard-check-fill me-2 text-rose"></i> Tareas de Gestión en Papelera
                        <span class="badge bg-soft-rose text-rose ms-2 rounded-pill"><?= count($gestion) ?></span>
                    </h5>
                </div>
                <div class="card-body px-4">
                    <div class="table-responsive">
                        <table class="table table-premium no-datatable align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Entregable</th>
                                    <th>Destino</th>
                                    <th>Fecha Eliminación</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($gestion)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted fst-italic">No hay tareas eliminadas.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($gestion as $g): ?>
                                        <tr>
                                            <td class="ps-4 fw-600"><?= htmlspecialchars($g['producto_documento']) ?></td>
                                            <td class="text-secondary"><?= htmlspecialchars($g['destino'] ?? 'Global') ?></td>
                                            <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($g['deleted_at'])) ?></td>
                                            <td class="text-end pe-4">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <a href="<?= BASE_URL ?>recycle-bin/restore?type=gestion&id=<?= $g['id'] ?>" class="btn btn-action-round text-success bg-soft-success" title="Restaurar">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </a>
                                                    <button onclick="confirmPurge('gestion', <?= $g['id'] ?>)" class="btn btn-action-round text-danger bg-soft-danger" title="Eliminar Permanente">
                                                        <i class="bi bi-x-circle-fill"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
        <?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
    </div>
</div>

<script>
function confirmPurge(type, id) {
    if (confirm('¿Está seguro de eliminar este registro permanentemente? Esta acción NO se puede deshacer.')) {
        window.location.href = `<?= BASE_URL ?>recycle-bin/purge?type=${type}&id=${id}`;
    }
}
</script>

<style>
.icon-sq {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 20px rgba(0,0,0,0.05);
}
.btn-action-round {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    transition: all 0.2s ease;
}
.btn-action-round:hover {
    transform: scale(1.1);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.bg-soft-success { background-color: rgba(25, 135, 84, 0.1) !important; color: #198754 !important; }
.bg-soft-danger { background-color: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; }
.bg-soft-indigo { background-color: rgba(102, 16, 242, 0.1) !important; color: #6610f2 !important; }
.bg-soft-rose { background-color: rgba(230, 0, 126, 0.1) !important; color: #e6007e !important; }
</style>
