/**
 * DynamicTable.js
 * Maneja la paginación y filtrado AJAX para tablas premium.
 */

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('dynamic-table-container');
    if (!container) return;

    const baseUrl = container.dataset.url;

    // Delegación de eventos para clicks en paginación
    container.addEventListener('click', (e) => {
        // Encontrar el link más cercano (por si clickea el icono)
        const link = e.target.closest('.ajax-link');

        if (link) {
            e.preventDefault();
            const page = link.dataset.page;
            if (!page) return;

            loadData(page);
        }
    });

    function loadData(page) {
        // Show loading state
        container.style.opacity = '0.5';

        // Construct URL
        const url = new URL(baseUrl); // baseUrl might need to be absolute or handle parameters
        // If baseUrl is relative or strict string, append params manually
        const fetchUrl = `${baseUrl}?page=${page}&ajax=1`;

        fetch(fetchUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Error loading data');
                return response.text(); // Expecting HTML fragment
            })
            .then(html => {
                container.innerHTML = html;
                container.style.opacity = '1';

                // Scroll to top of table gently
                const tableTop = container.offsetTop - 100;
                window.scrollTo({ top: tableTop, behavior: 'smooth' });
            })
            .catch(err => {
                console.error('DynamicTable Error:', err);
                container.style.opacity = '1';
                // Optional: Show toast error
            });
    }
});
