<?php include_once __DIR__ . '/../Layouts/Header.php'; ?>

<!-- Sidebar Independiente -->
<?php include_once __DIR__ . '/../Layouts/Sidebar.php'; ?>

<!-- Bloque Maestro -->
<div class="main-layout">
    
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <div class="container-fluid py-4 flex-grow-1">

            <!-- Encabezado -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-800 m-0 text-slate">Análisis de Riesgo Académico</h4>
                    <p class="text-muted small mb-0">Identificación temprana de estudiantes con dificultades</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-white shadow-sm btn-sm fw-600"><i class="bi bi-download me-2"></i>Exportar Reporte</button>
                    <button class="btn btn-premium-primary btn-sm fw-600"><i class="bi bi-arrow-repeat me-2"></i>Actualizar Análisis</button>
                </div>
            </div>

            <!-- Resumen KPIs -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 p-4 position-relative overflow-hidden">
                        <div class="position-absolute top-0 end-0 p-3 opacity-10">
                            <i class="bi bi-exclamation-triangle-fill fs-1 text-danger"></i>
                        </div>
                        <h6 class="text-uppercase text-muted fw-700 text-xs tracking-wide">Riesgo Alto</h6>
                        <h2 class="fw-900 text-danger mb-1"><?= count(array_filter($students, fn($s) => $s['promedio'] < 60)) ?></h2>
                        <span class="badge bg-danger-soft text-danger fw-700 text-xs">Atención Inmediata</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 p-4 position-relative overflow-hidden">
                         <div class="position-absolute top-0 end-0 p-3 opacity-10">
                            <i class="bi bi-exclamation-circle-fill fs-1 text-warning"></i>
                        </div>
                        <h6 class="text-uppercase text-muted fw-700 text-xs tracking-wide">Riesgo Medio</h6>
                        <h2 class="fw-900 text-warning mb-1"><?= count(array_filter($students, fn($s) => $s['promedio'] >= 60 && $s['promedio'] < 75)) ?></h2>
                        <span class="badge bg-warning-soft text-warning fw-700 text-xs">Seguimiento</span>
                    </div>
                </div>
                <div class="col-md-4">
                     <div class="card border-0 shadow-sm h-100 p-4 position-relative overflow-hidden bg-primary text-white">
                        <div class="position-absolute top-0 end-0 p-3 opacity-25">
                            <i class="bi bi-people-fill fs-1 text-white"></i>
                        </div>
                        <h6 class="text-uppercase text-white-50 fw-700 text-xs tracking-wide">Total Analizados</h6>
                        <h2 class="fw-900 text-white mb-1"><?= count($students) ?></h2>
                        <span class="badge bg-white text-primary fw-700 text-xs">Estudiantes en cursos a cargo</span>
                    </div>
                </div>
            </div>

            <!-- Tabla de Riesgo -->
            <div class="glass-panel p-4 animate-up delay-1">
                <h5 class="fw-800 text-slate mb-4">Estudiantes Identificados</h5>
                
                <?php if (empty($students)): ?>
                    <div class="text-center py-5">
                        <div class="icon-circle bg-light mb-3 mx-auto">
                            <i class="bi bi-check-lg fs-2 text-success"></i>
                        </div>
                        <h6 class="fw-700 text-slate">Todo se ve excelente</h6>
                        <p class="text-muted small">No se detectaron estudiantes con promedio crítico en tus cursos asignados.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-3 border-0 rounded-start">Estudiante</th>
                                    <th class="border-0">Curso</th>
                                    <th class="border-0 text-center">Promedio Actual</th>
                                    <th class="border-0 text-center">Nivel Riesgo</th>
                                    <th class="pe-3 border-0 rounded-end text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $s): 
                                    $promedio = (float)$s['promedio'];
                                    $riskClass = $promedio < 60 ? 'bg-danger text-white' : 'bg-warning text-dark';
                                    $riskLabel = $promedio < 60 ? 'Crítico' : 'Moderado';
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-soft-wrap bg-light rounded-circle fw-bold text-primary d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                                <?= strtoupper(substr($s['nombre'], 0, 1) . substr($s['apellido'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-700 text-dark small"><?= htmlspecialchars($s['nombre'] . ' ' . $s['apellido']) ?></div>
                                                <div class="text-xs text-muted">ID: <?= $s['id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border fw-600"><?= htmlspecialchars($s['curso_nombre']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-800 fs-6"><?= number_format($promedio, 1) ?>%</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill <?= $riskClass ?> fw-600 px-3"><?= $riskLabel ?></span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-outline-primary btn-sm btn-round fw-600" onclick="alert('Detalle no implementado')">
                                            Ver Detalle
                                        </button>
                                        <button class="btn btn-outline-success btn-sm btn-round ms-1" title="Enviar mensaje">
                                            <i class="bi bi-envelope"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>

<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
