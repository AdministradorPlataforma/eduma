/**
 * GlobalSearch.js
 * Maneja la búsqueda universal (Cmd+K)
 */

document.addEventListener('DOMContentLoaded', () => {
    const searchModal = new bootstrap.Modal(document.getElementById('globalSearchModal'));
    const searchInput = document.getElementById('globalSearchInput');
    const resultsContainer = document.getElementById('globalSearchResults');
    let debounceTimer;

    // 1. Atajos de Teclado
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            searchModal.show();
        }
    });

    // 2. Focus al abrir
    document.getElementById('globalSearchModal').addEventListener('shown.bs.modal', () => {
        searchInput.focus();
    });

    // 3. Trigger desde Navbar (si existe)
    const navSearchTrigger = document.querySelector('.search-input-premium');
    if (navSearchTrigger) {
        navSearchTrigger.addEventListener('click', (e) => {
            e.preventDefault();
            searchModal.show();
        });
        // Si el user empieza a tipear en el input del navbar, transferirlo al modal
        navSearchTrigger.addEventListener('input', (e) => {
            searchModal.show();
            searchInput.value = e.target.value;
            searchInput.dispatchEvent(new Event('input'));
        });
    }

    // 4. Lógica de Búsqueda
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();

        clearTimeout(debounceTimer);
        resultsContainer.innerHTML = ''; // Limpiar previo

        if (query.length < 2) {
            resultsContainer.innerHTML = '<div class="text-center p-4 text-muted small">Escriba al menos 2 caracteres...</div>';
            return;
        }

        resultsContainer.innerHTML = '<div class="text-center p-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Buscando...</div>';

        debounceTimer = setTimeout(() => {
            fetch(`${BASE_URL}search/query?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    renderResults(data.results);
                })
                .catch(err => {
                    console.error('Error en búsqueda:', err);
                    resultsContainer.innerHTML = '<div class="text-center p-3 text-danger small">Error al buscar.</div>';
                });
        }, 300);
    });

    function renderResults(results) {
        if (!results || results.length === 0) {
            resultsContainer.innerHTML = `
                <div class="text-center p-5">
                    <i class="bi bi-search opacity-25 display-4 d-block mb-3"></i>
                    <p class="text-muted small m-0">No se encontraron resultados para "${searchInput.value}"</p>
                </div>
            `;
            return;
        }

        let html = '<div class="list-group list-group-flush">';
        results.forEach(item => {
            // Icono según tipo
            let badgeClass = 'bg-light text-dark';
            if (item.type === 'tesis') badgeClass = 'bg-soft-indigo text-indigo';
            if (item.type === 'usuario') badgeClass = 'bg-soft-success text-success';

            html += `
                <a href="${item.url || '#'}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 border-0 border-bottom-dashed">
                    <div class="avatar-soft-wrap ms-1 ${badgeClass} rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi ${item.icon || 'bi-circle'} fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">${item.title}</h6>
                        <small class="text-muted d-block">${item.subtitle || ''}</small>
                    </div>
                    <i class="bi bi-chevron-right ms-auto text-muted opacity-50 small"></i>
                </a>
            `;
        });
        html += '</div>';
        resultsContainer.innerHTML = html;
    }
});
