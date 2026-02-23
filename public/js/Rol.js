/**
 * Rol.js - Lógica dinámica para el Módulo de Roles
 * Manejo de checkboxes visuales y selección masiva
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Manejo visual de las tarjetas de permisos (checkbox cards)
    const cards = document.querySelectorAll('.permiso-card-item');

    cards.forEach(card => {
        const input = card.querySelector('input');
        const icon = card.querySelector('.permiso-check');

        if (!input || !icon) return;

        // Inicializar estado
        updateCardState(card, input, icon);

        // Escuchar cambios
        input.addEventListener('change', function () {
            updateCardState(card, input, icon);
        });
    });

    function updateCardState(card, input, icon) {
        if (input.checked) {
            card.classList.add('checked');
            icon.classList.remove('bi-circle');
            icon.classList.add('bi-check-circle-fill');
        } else {
            card.classList.remove('checked');
            icon.classList.remove('bi-check-circle-fill');
            icon.classList.add('bi-circle');
        }
    }

    // 2. Toggle All Global (Marcar/Desmarcar Todo)
    const btnToggleAll = document.getElementById('btnToggleAll');
    if (btnToggleAll) {
        btnToggleAll.addEventListener('click', function () {
            const allInputs = document.querySelectorAll('.permiso-checkbox');
            const allChecked = Array.from(allInputs).every(i => i.checked);

            allInputs.forEach(input => {
                input.checked = !allChecked;
                input.dispatchEvent(new Event('change'));
            });
        });
    }

    // 3. Toggle por Grupo (Botón "Inv" en cada categoría)
    document.querySelectorAll('.toggle-group-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const group = this.dataset.group;
            const inputs = document.querySelectorAll(`.permiso-checkbox[data-group="${group}"]`);

            inputs.forEach(input => {
                input.checked = !input.checked;
                input.dispatchEvent(new Event('change'));
            });
        });
    });

    // 4. Delegación de eventos para botones de eliminación
    document.querySelectorAll('.btn-delete-rol').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            confirmDelete(id);
        });
    });
});

/**
 * Confirmar eliminación de rol
 */
function confirmDelete(id) {
    if (typeof Swal === 'undefined') {
        console.error('SweetAlert2 no está cargado');
        return;
    }

    const baseUrl = window.BASE_URL || '/eduma2/';

    Swal.fire({
        title: '¿Eliminar Rol?',
        text: 'Los usuarios con este rol perderán sus permisos asociados.',
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
            form.action = `${baseUrl}rol/eliminar/${id}`;

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
