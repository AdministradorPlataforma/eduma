<?php
/**
 * Monitor de Sesiones - Vista Administrativa
 * Estructura 100% Sincronizada con el Estándar EDUMA V2
 */
include_once __DIR__ . '/../Layouts/Header.php';
include_once __DIR__ . '/../Layouts/Sidebar.php';
?>

<div class="main-layout">
    <?php include_once __DIR__ . '/../Layouts/Navbar.php'; ?>

    <div class="content-wrapper">
        <div class="container-fluid py-5 px-lg-5 flex-grow-1">

            <!-- Dashboard de Indicadores (KPIs) -->
            <section class="main-stat-grid mb-5">
                <div class="kpi-modern-v2 indigo animate-up delay-1">
                    <div class="kpi-content">
                        <span class="kpi-label">Conexiones</span>
                        <h2 class="kpi-value text-slate"><?= count($sessions) ?></h2>
                    </div>
                    <div class="kpi-icon-box">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>

                <div class="kpi-modern-v2 green animate-up delay-2">
                    <div class="kpi-content">
                        <span class="kpi-label">Seguridad</span>
                        <h2 class="kpi-value text-slate">Máxima</h2>
                    </div>
                    <div class="kpi-icon-box">
                        <i class="bi bi-shield-fill-check"></i>
                    </div>
                </div>

                <div class="kpi-modern-v2 amber animate-up delay-3">
                    <div class="kpi-content">
                        <span class="kpi-label">Ecosistema</span>
                        <h2 class="kpi-value text-slate">Activo</h2>
                    </div>
                    <div class="kpi-icon-box">
                        <i class="bi bi-hdd-network-fill"></i>
                    </div>
                </div>
            </section>

            <!-- Panel de Datos Masterpiece -->
            <div class="glass-panel p-5 animate-up delay-3 border-0 shadow-sm mb-5">
                <div class="d-flex align-items-center gap-3 mb-5">
                    <div class="icon-sq bg-soft-indigo sm"><i class="bi bi-broadcast"></i></div>
                    <h4 class="fw-900 m-0 text-slate uppercase tracking-tighter">Terminales Conectadas</h4>
                </div>

                <div class="table-responsive">
                    <table class="table table-premium w-100" id="sessionsTable">
                        <thead>
                            <tr>
                                <th>Identidad</th>
                                <th>Dirección IP</th>
                                <th>Dispositivo</th>
                                <th>Navegador</th>
                                <th>Actividad</th>
                                <th class="text-end">Gestión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): 
                                $isCurrent = ($session['id'] === $current_sid);
                                $lastSeen = \App\Helpers\TimeHelper::timeAgo($session['last_activity']);
                                
                                // User Agent Parsing
                                $ua = $session['user_agent'];
                                $browser = 'Navegador'; $browserIcon = 'bi-globe';
                                if (strpos($ua, 'Chrome') !== false) { $browser = 'Chrome'; $browserIcon = 'bi-google'; }
                                elseif (strpos($ua, 'Firefox') !== false) { $browser = 'Firefox'; $browserIcon = 'bi-browser-firefox'; }
                                elseif (strpos($ua, 'Safari') !== false) { $browser = 'Safari'; $browserIcon = 'bi-browser-safari'; }
                                elseif (strpos($ua, 'Edg') !== false) { $browser = 'Edge'; $browserIcon = 'bi-browser-edge'; }

                                $os = 'S.O.'; $osIcon = 'bi-laptop';
                                if (strpos($ua, 'Windows') !== false) { $os = 'Windows'; $osIcon = 'bi-windows'; }
                                elseif (strpos($ua, 'Android') !== false) { $os = 'Android'; $osIcon = 'bi-phone'; }
                                elseif (strpos($ua, 'iPhone') !== false) { $os = 'iPhone'; $osIcon = 'bi-phone-vibrate'; }
                                elseif (strpos($ua, 'Macintosh') !== false) { $os = 'Mac OS'; $osIcon = 'bi-apple'; }
                            ?>
                                <tr class="<?= $isCurrent ? 'bg-soft-indigo-light' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-premium">
                                                <?= strtoupper(mb_substr($session['nombre'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-800 text-slate small"><?= htmlspecialchars(($session['nombre'] ?? '') . ' ' . ($session['apellido'] ?? '')) ?></div>
                                                <div class="text-muted text-xs">@<?= htmlspecialchars($session['username'] ?? 'anon') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <code class="fw-800 text-indigo extra-small"><?= $session['ip_address'] ?></code>
                                    </td>
                                    <td>
                                        <div class="ua-chip">
                                            <i class="bi <?= $osIcon ?>"></i>
                                            <?= $os ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi <?= $browserIcon ?> text-slate opacity-50"></i>
                                            <span class="text-muted fw-800 text-xs"><?= $browser ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isCurrent): ?>
                                            <span class="badge badge-soft-success fw-900 px-3 py-1 rounded-pill text-xs">
                                                <span class="status-online"></span>ONLINE
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted fw-800 text-xs"><?= $lastSeen ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if (!$isCurrent): ?>
                                            <button class="btn btn-soft-danger btn-round btn-xs px-3 fw-800" 
                                                    onclick="terminateSession('<?= $session['id'] ?>', '<?= htmlspecialchars($session['nombre'] ?? 'Usuario') ?>')">
                                                <i class="bi bi-power me-1"></i> EXPULSAR
                                            </button>
                                        <?php else: ?>
                                            <div class="text-muted text-xs fw-900 opacity-25 italic me-3">SESIÓN ACTUAL</div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
<?php include_once __DIR__ . '/../Layouts/Footer.php'; ?>
