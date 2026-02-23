<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<div class="main-layout">
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <div class="container-fluid py-4 flex-grow-1">

            <!-- Stats Row (Floating Top) -->
            <?php $stats = $stats ?? ['total_today' => 0, 'unique_users' => 0, 'security_events' => 0]; ?>
            <div class="row g-4 mb-4">
                <div class="col-xl-4 col-md-6 animate-up delay-1">
                    <div class="kpi-modern indigo">
                        <div class="icon-box"><i class="bi bi-activity"></i></div>
                        <div class="text-muted small fw-800 text-uppercase mb-2">Eventos Hoy</div>
                        <div class="d-flex align-items-end gap-2">
                            <h2 class="fw-800 m-0"><?= $stats['total_today'] ?></h2>
                            <span class="badge-soft badge-soft-indigo mb-1">Registrados</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 animate-up delay-2">
                    <div class="kpi-modern purple">
                        <div class="icon-box"><i class="bi bi-people-fill"></i></div>
                        <div class="text-muted small fw-800 text-uppercase mb-2">Usuarios Activos</div>
                        <div class="d-flex align-items-end gap-2">
                            <h2 class="fw-800 m-0"><?= $stats['unique_users'] ?></h2>
                            <span class="text-muted small mb-1">Sesiones</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-12 animate-up delay-3">
                    <div class="kpi-modern red">
                        <div class="icon-box"><i class="bi bi-shield-lock-fill"></i></div>
                        <div class="text-muted small fw-800 text-uppercase mb-2">Eventos Críticos</div>
                        <div class="d-flex align-items-end gap-2">
                            <h2 class="fw-800 m-0"><?= $stats['security_events'] ?></h2>
                            <span class="text-danger small mb-1 fw-bold">Seguridad</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Audit Card Container -->
            <div class="glass-panel p-4 p-lg-5 animate-up delay-1">
                
                <!-- Card Header -->
                <div class="d-flex justify-content-between align-items-center mb-5 pb-4 border-bottom border-light">
                    <div>
                        <h4 class="fw-800 m-0 text-slate">Registros de Auditoría</h4>
                        <p class="text-muted small mb-0">Trazabilidad completa y seguridad del núcleo institucional.</p>
                    </div>
                    <button id="btn-refresh-audit" class="btn btn-premium-secondary btn-round btn-sm shadow-sm px-4">
                        <i class="bi bi-arrow-clockwise me-2"></i> Refrescar
                    </button>
                </div>
                
                <!-- Filters Section (Inside Card) -->
                <div class="mb-5 pb-4 border-bottom border-light">
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-filter-right text-indigo fs-5"></i>
                        <h6 class="fw-800 text-slate m-0" >Búsqueda Avanzada</h6>
                    </div>
                    
                    <form method="GET" action="<?= BASE_URL ?>audit" class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label-premium">Fecha Inicio</label>
                            <input type="date" name="start_date" class="form-control" value="<?= $filters['start_date'] ?? '' ?>">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label-premium">Fecha Fin</label>
                            <input type="date" name="end_date" class="form-control" value="<?= $filters['end_date'] ?? '' ?>">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label-premium">Responsable</label>
                            <select name="user_id" class="form-select">
                                <option value="">Todos los usuarios</option>
                                <?php if (!empty($users)): ?>
                                    <?php foreach($users as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= (isset($filters['user_id']) && $filters['user_id'] == $u['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['username']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label-premium">Acción / Evento</label>
                            <input type="text" name="action" class="form-control" placeholder="Ej: LOGIN, CREATE" value="<?= $filters['action'] ?? '' ?>">
                        </div>
                        <div class="col-12 text-end mt-4">
                            <a href="<?= BASE_URL ?>audit" class="btn btn-premium-outline-danger btn-round btn-sm px-4 me-2">Limpiar</a>
                            <button type="submit" class="btn btn-premium-primary btn-round btn-sm px-4 shadow-sm">Filtrar Resultados</button>
                        </div>
                    </form>
                </div>

                <!-- Table Content -->
                <div class="table-responsive">
                    <table class="table table-premium align-middle mb-0" id="auditTable">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Recurso</th>
                                <th>Origen IP</th>
                                <th class="text-end">Detalles</th>
                            </tr>
                        </thead>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-700 text-slate small"><?= date('d M, Y', strtotime($log['created_at'])) ?></span>
                                                <span class="text-muted text-xs"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($log['username']): ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="avatar-sq bg-soft-indigo text-indigo fw-800 rounded-2 avatar-sq-sm">
                                                        <?= strtoupper(substr($log['username'], 0, 2)) ?>
                                                    </div>
                                                    <span class="fw-700 text-slate small"><?= htmlspecialchars($log['username']) ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge-soft badge-soft-indigo">SISTEMA</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $badgeClass = 'badge-soft-indigo';
                                            if (strpos($log['action'], 'CREATE') !== false) $badgeClass = 'badge-soft-success';
                                            if (strpos($log['action'], 'UPDATE') !== false) $badgeClass = 'badge-soft-warning';
                                            if (strpos($log['action'], 'DELETE') !== false) $badgeClass = 'badge-soft-critical';
                                            if (strpos($log['action'], 'SECURITY') !== false) $badgeClass = 'badge-soft-critical';
                                            ?>
                                            <span class="badge-soft <?= $badgeClass ?> fw-800 badge-text-xs"><?= htmlspecialchars($log['action']) ?></span>
                                        </td>
                                        <td>
                                            <span class="text-slate fw-600 small"><?= htmlspecialchars($log['resource'] ?? '-') ?></span>
                                        </td>
                                        <td>
                                            <code class="text-muted small"><?= htmlspecialchars($log['ip_address']) ?></code>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($log['details']): ?>
                                                <button class="btn btn-log-detail btn-sm rounded-pill fw-800 fs-8 px-3" type="button" 
                                                        data-log='<?= htmlspecialchars(json_encode([
                                                            'id' => $log['id'],
                                                            'details' => json_decode($log['details'], true)
                                                        ])) ?>'>
                                                    LOG <i class="bi bi-eye ms-1"></i>
                                                </button>
                                            <?php else: ?>
                                                <small class="text-muted opacity-30">N/A</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Modal de Detalles (Fuera de la tabla) -->
                <div class="modal fade" id="logDetailsModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title fw-800 text-slate">Detalle del Evento</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="bg-gray-900 rounded-3 p-3 overflow-auto log-details-container">
                                    <pre id="logDetailsContent" class="text-success small m-0 font-monospace"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                </div>
            </div>
        </div>
<script src="<?= BASE_URL ?>js/AuditExplorer.js?v=<?= time() ?>"></script>
<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
