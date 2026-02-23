/**
 * Permiso.js - Lógica para el Módulo de Permisos
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Delegación de eventos para botones de eliminación
    document.querySelectorAll('.btn-delete-permiso').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            confirmDelete(id);
        });
    });
});

/**
 * Confirmar eliminación de permiso
 */
function confirmDelete(id) {
    if (typeof Swal === 'undefined') {
        console.error('SweetAlert2 no está cargado');
        return;
    }

    const baseUrl = window.BASE_URL || '/eduma2/';

    Swal.fire({
        title: '¿Eliminar Permiso?',
        text: 'Esta acción despojará de este acceso a todos los roles que lo posean.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `${baseUrl}permiso/eliminar/${id}`;

            // CSRF Token
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value
                || document.querySelector('meta[name="csrf-token"]')?.content;

            if (csrfToken) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
            }

            document.body.appendChild(form);
            form.submit();
        }
    });
}
