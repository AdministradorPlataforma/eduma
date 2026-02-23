<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>

<!-- Sidebar Independiente -->
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<!-- Bloque Maestro -->
<div class="main-layout">
    
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <link rel="stylesheet" href="<?= BASE_URL ?>css/ListarTesis.css?v=<?= time() ?>">
        
        <div class="container-fluid py-4 flex-grow-1">

            <!-- Back Button -->
            <div class="mb-4">
                <a href="<?= BASE_URL ?>investigacion" class="btn btn-premium-secondary btn-round px-4">
                    <i class="bi bi-arrow-left me-2"></i> Volver al Listado
                </a>
            </div>

            <!-- Detail Glass Panel -->
            <div class="glass-panel p-5 animate-up delay-1">
                <div class="d-flex justify-content-between align-items-start mb-5 pb-4 border-bottom border-light">
                    <div>
                        <h6 class="text-uppercase text-muted fw-bold mb-2 ls-1">
                            Tesis #<?= str_pad((string)$tesis['id'], 3, '0', STR_PAD_LEFT) ?> 
                            <span class="mx-2 text-primary">|</span> 
                            <span class="text-primary font-monospace"><i class="bi bi-upc-scan me-1"></i><?= $tesis['codigo'] ?? 'S/C' ?></span>
                        </h6>
                        <h2 class="fw-800 m-0 text-slate mb-3"><?= htmlspecialchars($tesis['titulo']) ?></h2>
                        
                        <!-- Status Badge -->
                        <?php 
                        $cssClass = 'pendiente';
                        $icon = 'bi-circle';
                        switch($tesis['estado']) {
                            case 'Aprobada': $cssClass = 'aprobada'; $icon = 'bi-check-circle-fill'; break;
                            case 'Rechazada': $cssClass = 'rechazada'; $icon = 'bi-x-circle-fill'; break;
                            default: $cssClass = 'pendiente'; $icon = 'bi-hourglass-split'; break;
                        }
                        ?>
                        <span class="badge-status <?= $cssClass ?>">
                            <i class="bi <?= $icon ?>"></i> <?= $tesis['estado'] ?>
                        </span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>investigacion/editar/<?= $tesis['id'] ?>" class="btn btn-premium-secondary btn-round">
                            <i class="bi bi-pencil-square me-2"></i> Editar
                        </a>
                    </div>
                </div>

                <div class="row g-5">
                    <!-- Left Column: Details -->
                    <div class="col-lg-8">
                        
                        <div class="mb-5">
                            <h5 class="fw-700 text-slate mb-3"><i class="bi bi-file-text me-2 text-indigo"></i>Resumen / Descripción</h5>
                            <div class="p-4 bg-light rounded-4 border border-light text-secondary" style="line-height: 1.8;">
                                <?= nl2br(htmlspecialchars($tesis['descripcion'] ?? 'Sin descripción disponible.')) ?>
                            </div>
                        </div>

                        <div class="mb-5">
                            <h5 class="fw-700 text-slate mb-3"><i class="bi bi-paperclip me-2 text-indigo"></i>Documentación Adjunta</h5>
                            <?php if (!empty($tesis['archivo_path'])): ?>
                                <div class="d-flex align-items-center p-3 border rounded-3 bg-white shadow-sm" style="max-width: 400px;">
                                    <div class="fs-1 text-danger me-3"><i class="bi bi-file-earmark-pdf"></i></div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark">Formulario Nº4</div>
                                        <div class="text-muted small">Documento Base del Proyecto</div>
                                    </div>
                                    <a href="<?= BASE_URL . $tesis['archivo_path'] ?>" target="_blank" class="btn btn-sm btn-light border rounded-pill px-3">
                                        Descargar
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-muted fst-italic">No hay archivos adjuntos.</div>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Right Column: Meta Info -->
                    <div class="col-lg-4">
                        <div class="p-4 bg-soft-indigo rounded-4 mb-4">
                            <h6 class="text-uppercase fw-bold text-indigo mb-4 fs-xs ls-1">Equipo de Trabajo</h6>
                            
                            <!-- Estudiantes (Autores) -->
                            <div class="mb-4">
                                <div class="text-xs text-muted fw-bold text-uppercase mb-2">Estudiantes (Tesistas)</div>
                                <?php if (!empty($tesis['estudiantes'])): ?>
                                    <?php foreach ($tesis['estudiantes'] as $est): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="icon-sq-sm bg-white text-primary me-2 shadow-sm" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                <i class="bi bi-person"></i>
                                            </div>
                                            <div>
                                                <div class="fw-700 text-slate small">
                                                    <?= htmlspecialchars($est['nombre']) ?>
                                                </div>

                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted small fst-italic">No hay estudiantes registrados.</div>
                                <?php endif; ?>
                            </div>

                            <!-- Docentes (Asesores) -->
                            <div>
                                <div class="text-xs text-muted fw-bold text-uppercase mb-2">Docentes (Tutores)</div>
                                <?php if (!empty($tesis['docentes'])): ?>
                                    <?php foreach ($tesis['docentes'] as $tut): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="icon-sq-sm bg-white text-indigo me-2 shadow-sm" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                <i class="bi bi-person-badge"></i>
                                            </div>
                                            <div>
                                                <div class="fw-700 text-slate small">
                                                    <?= htmlspecialchars($tut['nombre']) ?>
                                                </div>

                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted small fst-italic">No hay docentes registrados.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-4 border border-light rounded-4">
                            <h6 class="text-uppercase fw-bold text-muted mb-3 fs-xs ls-1">Información de Registro</h6>
                            <ul class="list-unstyled mb-0 text-sm">
                                <li class="mb-2 d-flex justify-content-between">
                                    <span class="text-muted">ID Sistema:</span>
                                    <span class="fw-bold text-dark">#<?= $tesis['id'] ?></span>
                                </li>
                                <li class="mb-2 d-flex justify-content-between">
                                    <span class="text-muted">Fecha Creación:</span>
                                    <span class="fw-bold text-dark"><?= date('d/m/Y H:i', strtotime($tesis['created_at'])) ?></span>
                                </li>
                            </ul>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
