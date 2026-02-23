/**
 * AdminSesiones.js - Lógica del Monitor de Sesiones
 */
document.addEventListener('DOMContentLoaded', function () {
    console.log('Monitor de Sesiones Inicializado');
});

/**
 * Termina una sesión de usuario de forma remota
 * @param {string} sid ID de la sesión
 * @param {string} name Nombre del usuario
 */
async function terminateSession(sid, name) {
    const result = await Swal.fire({
        title: '¿Confirmar Expulsión?',
        text: `Se cerrará la conexión de ${name} y deberá volver a identificar su sesión.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, terminar sesión',
        cancelButtonText: 'Mantener conexión',
        background: '#ffffff',
        customClass: {
            title: 'fw-900',
            content: 'fw-500',
            popup: 'glass-panel border-0 shadow-lg'
        }
    });

    if (result.isConfirmed) {
        try {
            const formData = new FormData();
            formData.append('session_id', sid);

            // Obtener el token CSRF del meta tag (estándar EDUMA)
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const response = await fetch(window.BASE_URL + 'admin/sesiones/force-revoke', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    title: 'Usuario Expulsado',
                    text: 'La sesión ha sido eliminada del sistema.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    background: '#ffffff',
                    customClass: { popup: 'glass-panel border-0 shadow-lg' }
                });
                setTimeout(() => location.reload(), 2000);
            } else {
                Swal.fire('Error', data.message || 'No se pudo cerrar la sesión', 'error');
            }
        } catch (error) {
            console.error('Error expulsando sesión:', error);
            Swal.fire('Error', 'Error de red al intentar cerrar la sesión', 'error');
        }
    }
}
