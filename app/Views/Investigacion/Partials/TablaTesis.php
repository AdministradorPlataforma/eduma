<table class="table table-premium w-100 no-datatable">
    <thead>
        <tr>
            <th class="ps-4">ID</th>
            <th>Tesis</th>
            <th>Estudiante</th>
            <th>Tutor Académico</th>
            <th class="text-center">Estado</th>
            <th>Fecha Reg.</th>
            <th class="pe-4 text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($tesis)): ?>
            <!-- Empty State -->
            <tr>
                <td colspan="7" class="text-center py-5">
                    <div class="py-5 d-flex flex-column align-items-center justify-content-center">
                        <div class="mb-4 bg-light rounded-circle p-4 d-inline-flex text-secondary opacity-50">
                            <i class="bi bi-folder-plus fs-1"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">No hay tesis registradas</h5>
                        <p class="text-muted mb-4 mw-sm text-center" style="max-width: 400px;">
                            Actualmente no existen tesis en el sistema.
                        </p>
                    </div>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($tesis as $t): ?>
                <tr>
                    <td class="ps-4 fw-bold text-secondary">#<?= str_pad((string)$t['id'], 3, '0', STR_PAD_LEFT) ?></td>
                    
                    <td>
                        <div class="d-flex align-items-center">
                            <div>
                                <span class="fw-bold text-dark d-block mb-1" title="<?= htmlspecialchars($t['titulo']) ?>"><?= htmlspecialchars($t['titulo']) ?></span>
                                <span class="text-xs text-muted">
                                    <i class="bi bi-upc-scan me-1"></i><?= $t['codigo'] ?? 'N/A' ?>
                                </span>
                                <?php if (!empty($t['archivo_path'])): ?>
                                    <div class="mt-1">
                                        <a href="<?= BASE_URL . $t['archivo_path'] ?>" target="_blank" class="doc-link">
                                            <i class="bi bi-paperclip"></i> Ver Documento
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small mt-1 fst-italic">Sin adjunto</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex flex-column">
                                <span class="fw-600 text-slate"><?= htmlspecialchars($t['estudiantes_nombres'] ?? 'No asignado') ?></span>
                            </div>
                        </div>
                    </td>

                    <td>
                        <span class="fw-600 text-slate"><?= htmlspecialchars($t['tutores_nombres'] ?? 'No asignado') ?></span>
                    </td>

                    <td class="text-center">
                        <?php 
                        // Status Logic for CSS
                        $cssClass = 'pendiente';
                        $icon = 'bi-circle';
                        switch($t['estado']) {
                            case 'Aprobada':
                                $cssClass = 'aprobada';
                                $icon = 'bi-check-circle-fill';
                                break;
                            case 'Rechazada':
                                $cssClass = 'rechazada';
                                $icon = 'bi-x-circle-fill';
                                break;
                            case 'Pendiente':
                                $cssClass = 'pendiente';
                                $icon = 'bi-hourglass-split';
                                break;
                        }
                        ?>
                        <span class="badge-status <?= $cssClass ?>">
                            <i class="bi <?= $icon ?>"></i> <?= $t['estado'] ?>
                        </span>
                    </td>

                    <td class="text-secondary small fw-500">
                        <?= date('d/m/Y', strtotime($t['created_at'])) ?>
                    </td>

                    <td class="pe-4 text-end">
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= BASE_URL ?>investigacion/ver/<?= $t['id'] ?>" class="btn btn-action-round text-primary bg-soft-primary" title="Ver Detalles">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                            <a href="<?= BASE_URL ?>investigacion/editar/<?= $t['id'] ?>" class="btn btn-action-round text-warning bg-soft-warning" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <a href="<?= BASE_URL ?>investigacion/generarActa/<?= $t['id'] ?>" target="_blank" class="btn btn-action-round text-danger bg-soft-danger" title="Acta PDF">
                                <i class="bi bi-file-earmark-pdf-fill"></i>
                            </a>
                            <form action="<?= BASE_URL ?>investigacion/eliminar/<?= $t['id'] ?>" method="POST" onsubmit="return confirm('¿Está seguro de eliminar esta tesis? Esta acción no se puede deshacer.');" class="d-inline">
                                <?= \App\Helpers\CSRFHelper::csrfField() ?>
                                <button type="submit" class="btn btn-action-round text-danger bg-soft-danger" title="Eliminar">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- Paginación AJAX Linkeable -->
<?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
    <div class="d-flex justify-content-center mt-4">
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-premium">
                <!-- Anterior -->
                <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                    <a class="page-link ajax-link" href="<?= BASE_URL ?>investigacion?page=<?= $pagination['prev_page'] ?>" data-page="<?= $pagination['prev_page'] ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                
                <!-- Números -->
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                    <li class="page-item <?= $pagination['current'] == $i ? 'active' : '' ?>">
                        <a class="page-link ajax-link" href="<?= BASE_URL ?>investigacion?page=<?= $i ?>" data-page="<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <!-- Siguiente -->
                <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                    <a class="page-link ajax-link" href="<?= BASE_URL ?>investigacion?page=<?= $pagination['next_page'] ?>" data-page="<?= $pagination['next_page'] ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
<?php endif; ?>
