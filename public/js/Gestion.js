/**
 * Gestion.js
 * Lógica para la vista de Seguimiento Académico
 */
document.addEventListener('DOMContentLoaded', function () {
    // 1. Inicializar Variables Globales
    const pageData = document.getElementById('server-data');
    const csrfToken = pageData ? pageData.dataset.csrf : '';
    const alertas = pageData && pageData.dataset.alerts ? JSON.parse(pageData.dataset.alerts) : [];

    // 2. Configuración Toastr
    if (typeof toastr !== 'undefined') {
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "6000",
            "extendedTimeOut": "2000"
        };

        // Mostrar alertas de vencimiento
        alertas.forEach(aviso => {
            toastr.warning(aviso, 'Recordatorio de Plazo');
        });
    }

    // 3. Animaciones de Entrada (Manejadas por CSS)
    console.log('Gestion JS inicializado');

    // 4. Lógica para Subir Evidencia
    const btnsSubir = document.querySelectorAll('.btn-upload-file');

    btnsSubir.forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const titulo = this.dataset.titulo;

            if (typeof Swal === 'undefined') return;

            Swal.fire({
                title: 'Subir Evidencia Digital',
                html: `
                    <p class="text-muted small mb-4">Completa la tarea <strong>${titulo}</strong> subiendo el documento soporte.</p>
                    <div class="file-upload-wrapper p-4 border border-dashed rounded-3 bg-light">
                        <i class="bi bi-cloud-arrow-up display-4 text-indigo opacity-50 mb-2"></i>
                        <p class="small text-muted mb-0">Formatos: PDF, JPG, PNG (Max 5MB)</p>
                    </div>
                `,
                input: 'file',
                inputAttributes: {
                    'accept': '.pdf, .jpg, .jpeg, .png',
                    'class': 'form-control mt-3'
                },
                showCancelButton: true,
                confirmButtonText: 'Guardar Evidencia',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3b82f6', // Blue 500
                cancelButtonColor: '#94a3b8', // Slate 400
                showLoaderOnConfirm: true,
                backdrop: `rgba(15, 23, 42, 0.4)`,
                customClass: {
                    popup: 'rounded-4 px-4 py-4',
                    confirmButton: 'rounded-pill px-4',
                    cancelButton: 'rounded-pill px-4'
                },
                preConfirm: (file) => {
                    if (!file) {
                        Swal.showValidationMessage('Debes seleccionar un archivo válido');
                        return false;
                    }

                    let formData = new FormData();
                    formData.append('documento', file);
                    formData.append('tarea_id', id);
                    formData.append('csrf_token', csrfToken);

                    return fetch(window.BASE_URL + 'gestion/subir-evidencia', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => {
                            if (!response.ok) throw new Error(response.statusText);
                            return response.json();
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Error en la subida: ${error}`);
                        });
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    if (result.value.status === 'success') {
                        Swal.fire({
                            title: '¡Evidencia Registrada!',
                            text: 'La tarea ha sido marcada como cumplida exitosamente.',
                            icon: 'success',
                            confirmButtonColor: '#10b981',
                            confirmButtonText: 'Entendido',
                            customClass: { popup: 'rounded-4' }
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: result.value.message || 'No se pudo guardar la evidencia.',
                            icon: 'error',
                            customClass: { popup: 'rounded-4' }
                        });
                    }
                }
            });
        });
    });
});
