/**
 * AdminTareas.js
 * Lógica para la vista de Administración de Tareas Académicas
 */
document.addEventListener('DOMContentLoaded', function () {
    // 1. Manejo de Mensajes Flash (Toastr)
    const pageData = document.getElementById('server-data');
    if (pageData) {
        const flashSuccess = pageData.dataset.flashSuccess;
        const flashError = pageData.dataset.flashError;

        if (typeof toastr !== 'undefined') {
            toastr.options = {
                "closeButton": true,
                "progressBar": true,
                "positionClass": "toast-top-right",
                "timeOut": "5000"
            };

            if (flashSuccess) {
                toastr.success(flashSuccess, "Éxito");
            }
            if (flashError) {
                toastr.error(flashError, "Atención");
            }
        }
    }

    // 2. Animaciones de entrada (Manejadas por CSS)
    console.log('AdminTareas JS cargado');
});

// Event Delegation para Botón Eliminar
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-delete-task');
    if (btn) {
        e.preventDefault();
        const id = btn.dataset.id;
        confirmDeleteTarea(id);
    }
});

function confirmDeleteTarea(id) {
    // Usar SweetAlert2 si está disponible
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción eliminará la tarea asignada permanentemente.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f43f5e', // Rose 500
            cancelButtonColor: '#94a3b8', // Slate 400
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            background: '#fff',
            borderRadius: '1rem',
            backdrop: `
                rgba(15, 23, 42, 0.4)
                left top
                no-repeat
            `
        }).then((result) => {
            if (result.isConfirmed) {
                submitDeleteForm(id);
            }
        });
    } else {
        // Fallback nativo
        if (confirm('¿Seguro que deseas eliminar esta asignación?')) {
            submitDeleteForm(id);
        }
    }
}

function submitDeleteForm(id) {
    let form = document.createElement('form');
    form.method = 'POST';
    form.action = window.BASE_URL + 'gestion/eliminar/' + id;

    // CSRF Token
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
        document.querySelector('input[name="csrf_token"]')?.value;

    let csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = csrfToken;

    form.appendChild(csrfInput);
    document.body.appendChild(form);
    form.submit();
}
