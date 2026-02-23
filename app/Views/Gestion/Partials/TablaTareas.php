<?php
// TablaTareas.php (Partial)
?>
<div class="table-responsive">
    <table class="table table-premium align-middle mb-0 no-datatable">
        <thead class="bg-light">
            <tr>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-4">FACULTAD / CARGO</th>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ENTREGABLE</th>
                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">DÍA CORTE</th>
                <th class="text-secondary opacity-7"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tareas)): ?>
                <tr>
                    <td colspan="4" class="text-center py-4 text-muted">No hay tareas registradas.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tareas as $t): ?>
                <tr>
                    <td>
                        <div class="d-flex px-3 py-1">
                            <div class="d-flex flex-column justify-content-center">
                                <h6 class="mb-0 text-sm fw-700 text-slate-700"><?= htmlspecialchars($t['facultad']) ?></h6>
                                <p class="text-xs text-secondary mb-0 fw-600"><?= htmlspecialchars($t['responsable_cargo'] ?? '') ?></p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <p class="text-sm fw-600 mb-0 text-dark"><?= htmlspecialchars($t['producto_documento']) ?></p>
                        <?php if(!empty($t['destino'])): ?>
                            <p class="text-xs text-secondary mb-0">Para: <?= htmlspecialchars($t['destino']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="align-middle text-center">
                        <span class="badge badge-sm border border-secondary text-secondary bg-white rounded-pill px-3">
                            Día <?= $t['dia_plazo_mes'] ?>
                        </span>
                    </td>
                    <td class="align-middle text-end pe-3">
                        <button type="button" 
                                class="btn btn-premium-outline-danger btn-sm shadow-none btn-delete-task"
                                data-id="<?= $t['id'] ?>"
                                title="Eliminar Asignación">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Paginación AJAX -->
<?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
    <div class="d-flex justify-content-center mt-4">
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-premium">
                <!-- Anterior -->
                <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                    <a class="page-link ajax-link" href="<?= BASE_URL ?>gestion/admin?page=<?= $pagination['prev_page'] ?>" data-page="<?= $pagination['prev_page'] ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                
                <!-- Números -->
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                    <li class="page-item <?= $pagination['current'] == $i ? 'active' : '' ?>">
                        <a class="page-link ajax-link" href="<?= BASE_URL ?>gestion/admin?page=<?= $i ?>" data-page="<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <!-- Siguiente -->
                <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                    <a class="page-link ajax-link" href="<?= BASE_URL ?>gestion/admin?page=<?= $pagination['next_page'] ?>" data-page="<?= $pagination['next_page'] ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
<?php endif; ?>
