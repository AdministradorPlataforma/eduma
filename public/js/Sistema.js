/**
 * Sistema.js - Lógica para el panel de mantenimiento y herramientas globales
 */
document.addEventListener('DOMContentLoaded', () => {
    const janitorBtn = document.getElementById('btn-janitor');
    if (janitorBtn) {
        janitorBtn.addEventListener('click', async () => {
            const { isConfirmed } = await Swal.fire({
                title: '¿Ejecutar Mantenimiento Proactivo?',
                text: "Se purgarán cachés, logs antiguos y se optimizarán las tablas de la base de datos.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0B2FE6',
                confirmButtonText: 'Sí, optimizar ahora',
                cancelButtonText: 'En otro momento',
                customClass: {
                    popup: 'rounded-5',
                    confirmButton: 'px-4 py-2 rounded-3',
                    cancelButton: 'px-4 py-2 rounded-3'
                }
            });

            if (isConfirmed) {
                try {
                    Swal.fire({
                        title: 'Optimizando Sistema',
                        html: 'Espere un momento mientras el Janitor limpia la casa...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const formData = new FormData();
                    formData.append('csrf_token', csrfToken);

                    // Usamos window.BASE_URL definido en el Header global
                    const response = await fetch(window.BASE_URL + 'sistema/janitor/run', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.status === 'success') {
                        let details = '';
                        for (const [key, value] of Object.entries(data.data.details)) {
                            details += `<tr><td class="text-capitalize fw-600 text-muted">${key.replace(/_/g, ' ')}</td><td class="text-end fw-800 text-indigo">${value}</td></tr>`;
                        }

                        Swal.fire({
                            title: '¡Operación Exitosa!',
                            html: `
                                <div class="mt-3">
                                    <table class="table table-sm table-borderless">
                                        <tbody>${details}</tbody>
                                    </table>
                                    <div class="alert alert-soft-success small mb-0 mt-3 border-0">El sistema ahora está funcionando al 100% de su capacidad nominal.</div>
                                </div>
                            `,
                            icon: 'success',
                            customClass: {
                                popup: 'rounded-5'
                            }
                        });
                    } else {
                        Swal.fire('Error', data.message || 'La limpieza falló inesperadamente', 'error');
                    }
                } catch (e) {
                    console.error(e);
                    Swal.fire('Error de Conexión', 'No se pudo establecer comunicación con el servidor central.', 'error');
                }
            }
        });
    }
});
