document.addEventListener('DOMContentLoaded', () => {
    // 1. Delegación para finalizar sesión
    document.querySelectorAll('.btn-revoke-session').forEach(btn => {
        btn.addEventListener('click', function () {
            const sid = this.dataset.sid;
            revokeSession(sid);
        });
    });
});

/**
 * Revoca una sesión activa por su ID
 */
function revokeSession(sid) {
    if (typeof Swal === 'undefined') {
        console.error('SweetAlert2 no está cargado');
        return;
    }

    Swal.fire({
        title: '¿Cerrar esta sesión?',
        text: "El dispositivo tendrá que volver a iniciar sesión.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, finalizar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-danger rounded-pill px-4',
            cancelButton: 'btn btn-light rounded-pill px-4'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('session_id', sid);

            // Intentar obtener CSRF si existe
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
            if (csrfToken) formData.append('csrf_token', csrfToken);

            fetch(window.BASE_URL + 'perfil/sesiones/revoke', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        location.reload();
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'No se pudo cerrar la sesión',
                            icon: 'error',
                            customClass: { popup: 'rounded-4' }
                        });
                    }
                })
                .catch(err => {
                    console.error('Error al revocar sesión:', err);
                    Swal.fire('Error', 'Hubo un problema de conexión', 'error');
                });
        }
    });
}
