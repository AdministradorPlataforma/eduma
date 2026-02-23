/**
 * Escritorio.js
 * Lógica específica para el Dashboard principal.
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Gráficos
    initCumplimientoChart();

    // 2. Manejo de Acciones Rápidas (Redirección vía Data Attributes)
    document.querySelectorAll('.action-card-premium').forEach(card => {
        card.addEventListener('click', function () {
            const url = this.dataset.url;
            if (url) window.location.href = url;
        });
    });

    // 3. Filtros de Actividad
    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.addEventListener('click', function () {
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            this.classList.add('active');

            const filter = this.dataset.filter;
            console.log('Filtering activity by:', filter);
            // Aquí se podría implementar filtrado real ocultando elementos del DOM
        });
    });

    console.log('Escritorio JS inicializado V2.5');
});

/**
 * Inicializa el gráfico de dona de cumplimiento académico.
 */
function initCumplimientoChart() {
    const ctx = document.getElementById('cumplimientoChart');
    if (!ctx) return;

    const percent = parseFloat(ctx.dataset.percent) || 0;
    const pending = parseFloat(ctx.dataset.pending) || 0;

    if (typeof Chart === 'undefined') {
        console.error('Chart.js no está cargado.');
        return;
    }

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completado', 'Pendiente'],
            datasets: [{
                data: [percent, pending],
                backgroundColor: ['#6366f1', '#f43f5e'],
                hoverBackgroundColor: ['#4f46e5', '#e11d48'],
                borderWidth: 0,
                borderRadius: 20,
                cutout: '84%'
            }]
        },
        options: {
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    padding: 15,
                    backgroundColor: 'rgba(255,255,255,0.9)',
                    titleColor: '#1e293b',
                    bodyColor: '#64748b',
                    borderColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 1,
                    usePointStyle: true
                }
            },
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 2000,
                easing: 'easeOutQuart'
            }
        },
        plugins: [{
            id: 'centerText',
            afterDraw: (chart) => {
                const { ctx, chartArea: { top, bottom, left, right, width, height } } = chart;
                ctx.save();
                const fontSize = (height / 4).toFixed(2);
                ctx.font = `bold ${fontSize}px Inter`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#1e293b';
                ctx.fillText(percent.toFixed(1) + '%', width / 2, height / 2 + top);
                ctx.restore();
            }
        }]
    });
}

/**
 * Maneja las acciones rápidas del escritorio.
 * @param {string} action - El identificador de la acción.
 */
function handleQuickAction(action) {
    console.log('Action triggered:', action);
    // Aquí se puede implementar redirección o lógica AJAX
}
