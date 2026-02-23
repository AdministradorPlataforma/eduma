<nav class="navbar navbar-expand-lg border-0 px-3 py-0">
    <div class="container-fluid glass-navbar mt-3 rounded-4 shadow-none border-0 d-flex align-items-center justify-content-between gap-3">
        
        <!-- Bloque Izquierdo: Live Clock & Info (Innovación) -->
        <div class="nav-left-section d-none d-xl-flex">
            <div class="live-clock-widget" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Sistema en Tiempo Real">
                <div class="clock-icon-box">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="clock-content">
                    <span id="liveClockTime" class="clock-time">--:--</span>
                    <span id="liveClockDate" class="clock-date">Sincronizando...</span>
                </div>
            </div>
        </div>

        <!-- Bloque Central: Búsqueda Inteligente -->
        <div class="collapse navbar-collapse d-none d-lg-flex justify-content-center flex-grow-1" id="navbarContent">
            <div class="position-relative search-soft-wrap search-container w-100 search-soft-wrap-max">
                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-4 fs-5 text-primary opacity-75 search-icon-main"></i>
                <input class="form-control fw-600 search-input-premium" type="search" placeholder="Buscar expedientes, alumnos o departamentos...">
                <!-- Shortcut Hint -->
                <div class="search-shortcut d-none d-md-block">
                    <span class="opacity-75">CMD</span> K
                </div>
            </div>
        </div>

        <!-- Bloque Derecho: Acciones de Usuario -->
        <div class="d-flex align-items-center gap-3">

            <!-- Notifications (Dynamic) -->
            <div class="dropdown position-relative p-1">
                <a class="nav-link p-2 rounded-circle hover-soft-bg transition-all dropdown-toggle no-caret" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones">
                    <i class="bi bi-bell-fill fs-5 notif-bell text-primary opacity-80"></i>
                    <?php if ($globalUnreadCount > 0): ?>
                        <span class="position-absolute translate-middle badge rounded-pill bg-danger notif-badge">
                            <?= $globalUnreadCount > 9 ? '9+' : $globalUnreadCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 animate-soft-reveal notif-dropdown">
                    <li class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                        <span class="fw-bold small text-muted">Notificaciones</span>
                        <a href="#" id="markAllReadBtn" class="small text-decoration-none text-primary">Marcar todo</a>
                    </li>
                    
                    <?php if (empty($globalUnreadNotifs)): ?>
                        <li class="p-4 text-center text-muted small">
                            <i class="bi bi-bell-slash opacity-50 d-block fs-3 mb-2"></i>
                            Sin novedades
                        </li>
                    <?php else: ?>
                        <?php foreach($globalUnreadNotifs as $notif): ?>
                            <li>
                                <a href="#" class="dropdown-item py-3 border-bottom border-light mark-read-notif" data-id="<?= $notif['id'] ?>">
                                    <div class="d-flex align-items-start gap-2">
                                        <div class="flex-shrink-0">
                                            <?php 
                                            $icon = 'bi-info-circle text-info';
                                            if ($notif['type'] == 'success') $icon = 'bi-check-circle text-success';
                                            if ($notif['type'] == 'warning') $icon = 'bi-exclamation-triangle text-warning';
                                            if ($notif['type'] == 'danger') $icon = 'bi-x-circle text-danger';
                                            ?>
                                            <i class="bi <?= $icon ?>"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 small fw-bold text-dark"><?= htmlspecialchars($notif['title']) ?></h6>
                                            <p class="mb-1 small text-muted text-wrap notif-msg"><?= htmlspecialchars($notif['message']) ?></p>
                                            <small class="text-xs text-muted opacity-75"><?= date('d/m H:i', strtotime($notif['created_at'])) ?></small>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            


            <div class="vr opacity-5 mx-1 vertical-divider"></div>

            <!-- Profile Dropdown -->
            <div class="dropdown">
                <a class="d-flex align-items-center gap-3 text-decoration-none dropdown-toggle no-caret" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="d-none d-md-block text-end me-1">
                        <div class="fw-800 fs-7 text-dark mb-0 lh-1"><?= $_SESSION['user_data']['nombre'] ?? 'Usuario' ?></div>
                        <small class="text-xs text-muted opacity-75">En línea</small>
                    </div>
                    <div class="avatar-nav-wrap">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['user_data']['nombre'] ?? 'User') ?>&background=0B2FE6&color=fff&bold=true" class="rounded-circle" width="44" height="44">
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-premium animate-soft-reveal" aria-labelledby="userDropdown">
                    <li class="px-2 pb-4 mb-3 border-bottom border-light">
                        <div class="d-flex align-items-center gap-4">
                            <div class="avatar-soft-wrap p-1 bg-light rounded-circle shadow-none">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['user_data']['nombre'] ?? 'User') ?>&background=0B2FE6&color=fff&bold=true" class="rounded-circle" width="52" height="52">
                            </div>
                            <div class="overflow-hidden">
                                <div class="fw-800 fs-6 text-truncate user-name-nav"><?= $_SESSION['user_data']['nombre'] ?? 'Usuario' ?></div>
                                <div class="badge-soft-indigo d-inline-block mt-1 user-role-badge"><?= $_SESSION['user_data']['rol'] ?? 'Gestor Académico' ?></div>
                            </div>
                        </div>
                    </li>
                    <li><a class="dropdown-item dropdown-item-premium" href="<?= BASE_URL ?>perfil"><i class="bi bi-person-circle me-3"></i> Perfil Personal</a></li>
                    <li class="mt-3"><a class="dropdown-item dropdown-item-premium text-danger" href="<?= BASE_URL ?>logout"><i class="bi bi-power me-3"></i> Cerrar Sesión</a></li>
                </ul>
            </div>

        </div>
    </div>
</nav>

<!-- Modal Búsqueda Universal -->
<div class="modal fade" id="globalSearchModal" tabindex="-1" aria-labelledby="globalSearchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content glass-panel border-0 shadow-lg">
      <div class="modal-header border-0 pb-0">
        <div class="input-group">
            <span class="input-group-text bg-transparent border-0 ps-3">
                <i class="bi bi-search text-primary fs-4"></i>
            </span>
            <input type="text" class="form-control border-0 fs-5 py-3 shadow-none bg-transparent" 
                   id="globalSearchInput" 
                   placeholder="Buscar tesis, estudiantes, docentes... (ESC para cerrar)" 
                   autocomplete="off">
        </div>
        <button type="button" class="btn-close d-none" data-bs-dismiss="modal" aria-label="Close"></button> <!-- Hidden close btn -->
      </div>
      <div class="modal-body p-0" style="min-height: 100px; max-height: 60vh; overflow-y: auto;">
          <div id="globalSearchResults" class="p-3">
              <div class="text-center text-muted p-4">
                  <small>Escriba para buscar o presione ESC para salir.</small>
                  <div class="mt-2 text-xs opacity-50">
                      <code>CMD + K</code> abre este buscador en cualquier momento.
                  </div>
              </div>
          </div>
      </div>
      <div class="modal-footer border-0 pt-0 justify-content-between px-4 pb-3">
          <small class="text-muted text-xs">Resultados limitados a 10 por categoría.</small>
          <div class="d-flex align-items-center gap-2 text-xs text-muted">
              <span><i class="bi bi-arrow-return-left"></i> Seleccionar</span>
              <span><i class="bi bi-arrow-down"></i> Navegar</span>
          </div>
      </div>
    </div>
  </div>
</div>
