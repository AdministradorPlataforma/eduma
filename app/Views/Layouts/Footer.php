<!-- Footer Premium Soft -->
<footer class="footer py-3 mt-auto footer-premium">
<div class="container-fluid d-flex justify-content-between align-items-center px-4">
<div class="d-flex align-items-center gap-3">
<span class="fw-700 tracking-wide text-slate footer-copy">©<?=date('Y')?> EDUMA</span>
<div class="vr opacity-25 footer-divider"></div>
<span class="fw-600 text-muted footer-system-text">SISTEMA DE GESTIÓN ACADÉMICA</span>
</div>
<div class="d-flex align-items-center gap-2">
<div class="footer-status-dot"></div>
<span class="text-xs fw-700 text-slate footer-version">V 1.0</span>
</div>
</div>
</footer>

</div> <!-- Close .content-wrapper -->
</div> <!-- Close .main-layout -->
</div> <!-- Close .app-container -->

<!-- jQuery (Required for DataTables) -->
<script src="<?= BASE_URL ?>js/libraries/jquery.min.js"></script>

<!-- DataTables JS + Plugins -->
<script src="<?= BASE_URL ?>js/libraries/jquery.dataTables.min.js"></script>
<script src="<?= BASE_URL ?>js/libraries/dataTables.bootstrap5.min.js"></script>
<script src="<?= BASE_URL ?>js/libraries/dataTables.responsive.min.js"></script>

<!-- Bootstrap 5 JS Bundle -->
<script src="<?= BASE_URL ?>js/libraries/bootstrap.bundle.min.js?v=1.0"></script>

<!-- Scripts Globales de Layout (Sidebar, etc.) -->
<script src="<?= BASE_URL ?>js/Layout.js?v=<?= time() ?>"></script>
<script src="<?= BASE_URL ?>js/GlobalTables.js?v=<?= time() ?>"></script>

<!-- SweetAlert2 -->
<script src="<?= BASE_URL ?>js/libraries/sweetalert2.all.min.js"></script>

<!-- Lógica Específica de la Vista -->
<?php if (isset($extraJS) && !empty($extraJS)): ?>
<script src="<?= BASE_URL ?>js/<?= $extraJS ?>.js?v=<?= time() ?>"></script>
<?php endif; ?>
    <!-- Global Search -->
    <script src="<?= BASE_URL ?>js/GlobalSearch.js?v=<?= time() ?>"></script>
</body>
</html>
