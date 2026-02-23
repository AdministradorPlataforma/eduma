/**
 * layout.js
 * Scripts globales para el funcionamiento del layout principal (Sidebar, Navbar, etc.)
 */

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar BASE_URL desde el body si no existe globalmente
    if (typeof window.BASE_URL === 'undefined') {
        window.BASE_URL = document.body.getAttribute('data-base-url') || '/';
    }

    console.log('Layout JS cargado. BASE_URL:', window.BASE_URL);

    // 1. Sidebar Toggle (Móvil)
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
        });
    }

    // 2. Fix Manual para Submenús Colapsables (Bootstrap 5)
    // A veces los data-attributes fallan si hay conflictos o cargas asíncronas.
    const menuToggles = document.querySelectorAll('.sidebar [data-bs-toggle="collapse"]');

    menuToggles.forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            // Prevenir navegación por defecto del link '#'
            e.preventDefault();

            // Intentar usar la API de Bootstrap si está disponible
            if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                const targetId = this.getAttribute('data-bs-target');
                const targetEl = document.querySelector(targetId);

                if (targetEl) {
                    // Bootstrap 5.3 supports getOrCreateInstance
                    const collapse = bootstrap.Collapse.getOrCreateInstance(targetEl, {
                        toggle: false
                    });
                    collapse.toggle();
                }
            } else {
                console.error('Bootstrap 5 no está cargado o no se encuentra el objeto global "bootstrap".');
            }
        });
    });

    // 3. Search Shortcut (Cmd+K)
    const searchInput = document.querySelector('.search-input-premium');
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) searchInput.focus();
        }
    });

    if (searchInput) {
        searchInput.addEventListener('focus', () => {
            searchInput.closest('.search-container').classList.add('search-focused');
        });
        searchInput.addEventListener('blur', () => {
            searchInput.closest('.search-container').classList.remove('search-focused');
        });
    }
    // 4. Live Clock Widget
    function updateClock() {
        const timeEl = document.getElementById('liveClockTime');
        const dateEl = document.getElementById('liveClockDate');

        if (!timeEl || !dateEl) return;

        const now = new Date();
        const timeString = now.toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit'
        });
        const dateString = now.toLocaleDateString('es-ES', {
            weekday: 'short',
            day: '2-digit',
            month: 'short'
        }).replace('.', '').toUpperCase();

        timeEl.textContent = timeString;
        dateEl.textContent = dateString;
    }

    if (document.getElementById('liveClockTime')) {
        updateClock();
        setInterval(updateClock, 1000);
    }

    // 5. Manejo de Notificaciones
    const markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', markAllRead);
    }

    document.querySelectorAll('.mark-read-notif').forEach(notifLink => {
        notifLink.addEventListener('click', function (e) {
            const id = this.dataset.id;
            markRead(e, id);
        });
    });
});

/**
 * Marca una notificación como leída.
 * @param {Event} e - Evento de click.
 * @param {number} id - ID de la notificación.
 */
function markRead(e, id) {
    if (e) e.preventDefault();
    fetch(`${BASE_URL}notificaciones/leer/${id}`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(() => location.reload());
}

/**
 * Marca todas las notificaciones como leídas.
 * @param {Event} e - Evento de click.
 */
function markAllRead(e) {
    if (e) e.preventDefault();
    fetch(`${BASE_URL}notificaciones/leer-todas`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(() => location.reload());
}

