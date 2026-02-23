<aside class="sidebar premium-sidebar" id="sidebar">
    <!-- Brand Identity Area - Masterpiece -->
    <div class="brand-area d-flex align-items-center px-4 py-4 mb-4">
        <div class="brand-logo d-flex align-items-center justify-content-center">
            <i class="bi bi-mortarboard-fill text-white fs-4"></i>
        </div>
        <div class="brand-text ms-4 d-flex flex-column">
            <span class="fs-3 fw-800 tracking-tight text-tight brand-title">EDUMA</span>
            <span class="text-uppercase tracking-widest fw-800 opacity-30 mt-1 brand-subtitle">ECOSISTEMA v1.0</span>
        </div>
    </div>

    <!-- Navigation Area -->
    <div class="nav-wrapper flex-grow-1">
        <div class="px-4 mb-4 mt-2">
            <span class="text-uppercase fw-800 opacity-30 nav-category">Menú Principal</span>
        </div>
        
        <div class="nav-container px-3">
            <ul class="nav flex-column gap-2 mb-auto">
                <?php 
                $menuItems = \App\Helpers\MenuConfigHelper::getMenu(); 
                foreach ($menuItems as $index => $item): 
                    $hasSubmenu = !empty($item['submenu']);
                    $isActive = \App\Helpers\MenuConfigHelper::isActive($item['url']);
                    $isParentActive = false;
                    if ($hasSubmenu) {
                        foreach ($item['submenu'] as $sub) {
                            if (\App\Helpers\MenuConfigHelper::isActive($sub['url'])) {
                                $isParentActive = true;
                                break;
                            }
                        }
                    }
                    $activeClass = ($isActive || $isParentActive) ? 'active' : '';
                    $collapseId = 'submenu-' . $index; 
                ?>
                    <li class="nav-item">
                        <?php if ($hasSubmenu): ?>
                            <a href="#" 
                               class="nav-link-premium d-flex align-items-center gap-3 <?= $activeClass ?>" 
                               data-bs-toggle="collapse" 
                               data-bs-target="#<?= $collapseId ?>"
                               role="button" 
                               aria-expanded="<?= $isParentActive ? 'true' : 'false' ?>">
                                <div class="icon-box-sm rounded-4">
                                    <i class="<?= $item['icon'] ?> fs-6"></i>
                                </div>
                                <span class="nav-label fw-600"><?= htmlspecialchars($item['label']) ?></span>
                                <i class="bi bi-chevron-right ms-auto arrow-icon small opacity-30"></i>
                            </a>
                            <div class="collapse <?= $isParentActive ? 'show' : '' ?>" id="<?= $collapseId ?>">
                                <ul class="nav flex-column ms-5 mt-2 mb-3 border-start border-light border-2 submenu-inner">
                                    <?php foreach ($item['submenu'] as $subItem): 
                                         $isSubActive = \App\Helpers\MenuConfigHelper::isActive($subItem['url']);
                                         $subActiveClass = $isSubActive ? 'active' : '';
                                    ?>
                                        <li class="nav-item ms-3">
                                            <a href="<?= BASE_URL . $subItem['url'] ?>" class="nav-link-sub <?= $subActiveClass ?>">
                                                <?= htmlspecialchars($subItem['label']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a href="<?= BASE_URL . $item['url'] ?>" class="nav-link-premium d-flex align-items-center gap-3 <?= $activeClass ?>">
                                <div class="icon-box-sm rounded-4">
                                    <i class="<?= $item['icon'] ?> fs-6"></i>
                                </div>
                                <span class="nav-label fw-600"><?= htmlspecialchars($item['label']) ?></span>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Sidebar Footer - Elevated Experience -->
    <div class="sidebar-footer mt-auto p-4 mb-3 border-top border-white border-opacity-10">   
        <a href="<?= BASE_URL ?>logout" class="btn-logout-sidebar-premium w-100 d-flex align-items-center justify-content-center gap-3 py-2 fw-800 transition-all">
            <i class="bi bi-power"></i>
            <span>CERRAR SESIÓN</span>
        </a>
    </div>
</aside>

