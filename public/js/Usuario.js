/**
 * Usuario.js
 * Logic for User Management Views
 * V2.0 - DataTables Server-Side Processing
 */

document.addEventListener('DOMContentLoaded', () => {
    // Password Confirmation Validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            const pass = form.querySelector('input[name="password"]');
            const passConfirm = form.querySelector('input[name="password_confirm"]');

            if (pass && passConfirm && pass.value !== passConfirm.value) {
                e.preventDefault();
                alert('Las contraseñas no coinciden. Por favor verifíquelas.');
                passConfirm.classList.add('is-invalid');
                passConfirm.focus();
            }
        });

        // Remove invalid class on input
        const inputs = form.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                input.classList.remove('is-invalid');
            });
        });
    }

    // Interactive Effects for Cards
    const cards = document.querySelectorAll('.custom-checkbox-card');
    cards.forEach(card => {
        card.addEventListener('mousedown', () => {
            card.querySelector('.card-content').style.transform = 'scale(0.95)';
        });
        card.addEventListener('mouseup', () => {
            setTimeout(() => {
                card.querySelector('.card-content').style.transform = '';
            }, 100);
        });
    });

    // 5. Delegación para eliminar usuario
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-delete-usuario');
        if (btn) {
            const id = btn.dataset.id;
            confirmDelete(id);
        }
    });

    // ===== DataTables Server-Side Initialization =====
    initUsuariosDataTable();
});

/**
 * Inicializa DataTables para la tabla de usuarios (Server-Side Processing)
 */
function initUsuariosDataTable() {
    const tableEl = document.getElementById('usuarios-table');
    if (!tableEl) return; // Solo ejecutar si existe la tabla

    // Obtener configuración desde data-* attributes
    const ajaxUrl = tableEl.dataset.ajaxUrl;
    const baseUrl = tableEl.dataset.baseUrl;
    const csrfToken = tableEl.dataset.csrfToken;

    // Inicializar DataTables con jQuery
    const table = $('#usuarios-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: ajaxUrl,
            type: 'GET',
            timeout: 30000,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            xhrFields: {
                withCredentials: true
            },
            error: function (xhr, error, thrown) {
                console.error('DataTables AJAX error:', error, thrown);

                // Si es un error de autenticación, recargar la página
                if (xhr.status === 401 || xhr.status === 403) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sesión expirada',
                        text: 'Tu sesión ha expirado. Por favor inicia sesión nuevamente.',
                        confirmButtonText: 'Ir al Login'
                    }).then(() => {
                        window.location.href = baseUrl + 'login';
                    });
                }
            }
        },

        columns: [
            {
                data: null,
                render: function (data, type, row) {
                    return `
                        <div class="user-info-group">
                            <div class="avatar-soft-wrap">
                                <div class="avatar-initials">${row.initials}</div>
                            </div>
                            <div>
                                <div class="fw-800 text-slate">${escapeHtml(row.nombre)} ${escapeHtml(row.apellido)}</div>
                                <div class="text-muted small text-xs-7">ID: #${String(row.id).padStart(5, '0')}</div>
                            </div>
                        </div>
                    `;
                }
            },
            {
                data: 'email',
                render: function (data) {
                    return `
                        <div class="d-flex align-items-center text-muted">
                            <i class="bi bi-envelope-at me-2 opacity-50"></i>
                            <span class="fw-500 small">${escapeHtml(data)}</span>
                        </div>
                    `;
                }
            },
            {
                data: 'origen',
                orderable: false,
                render: function (data, type, row) {
                    if (data === 'moodle') {
                        return `
                            <span class="badge badge-origen moodle" title="ID Moodle: ${row.id_moodle}">
                                <i class="bi bi-cloud-fill me-1"></i>Moodle
                            </span>
                        `;
                    } else {
                        return `
                            <span class="badge badge-origen local">
                                <i class="bi bi-hdd-fill me-1"></i>Local
                            </span>
                        `;
                    }
                }
            },
            {
                data: 'rol',
                render: function (data, type, row) {
                    const adminClass = row.isAdmin ? 'admin' : '';
                    return `<span class="badge rounded-pill badge-rol ${adminClass}">${escapeHtml(data).toUpperCase()}</span>`;
                }
            },
            {
                data: 'activo',
                render: function (data) {
                    const isActive = data == 1;
                    return `
                        <div class="status-indicator">
                            <div class="status-dot ${isActive ? '' : 'inactive'}"></div>
                            <span class="status-text">${isActive ? 'ACTIVO' : 'INACTIVO'}</span>
                        </div>
                    `;
                }
            },
            {
                data: 'id',
                orderable: false,
                render: function (data) {
                    return `
                        <div class="btn-action-group">
                            <a href="${baseUrl}usuario/editar/${data}" class="btn btn-action-round btn-edit-soft" title="Editar">
                                <i class="bi bi-pencil-fill small"></i>
                            </a>
                            <button type="button" class="btn btn-action-round btn-delete-soft btn-delete-usuario" data-id="${data}" title="Eliminar">
                                <i class="bi bi-trash-fill small"></i>
                            </button>
                        </div>
                    `;
                },
                className: 'text-end'
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Cargando...</span></div> Cargando...',
            lengthMenu: 'Mostrar _MENU_ registros',
            zeroRecords: 'No se encontraron usuarios',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ usuarios',
            infoEmpty: 'Sin registros disponibles',
            infoFiltered: '(filtrado de _MAX_ usuarios totales)',
            search: '<i class="bi bi-search"></i>',
            searchPlaceholder: 'Buscar usuario...',
            paginate: {
                first: '<i class="bi bi-chevron-double-left"></i>',
                last: '<i class="bi bi-chevron-double-right"></i>',
                next: '<i class="bi bi-chevron-right"></i>',
                previous: '<i class="bi bi-chevron-left"></i>'
            }
        },
        dom: '<"row align-items-center mb-3"<"col-md-6"l><"col-md-6"f>><"table-responsive"t><"row align-items-center mt-3"<"col-md-5"i><"col-md-7"p>>',
        searchDelay: 500, // Esperar 500ms después de dejar de escribir
        responsive: true,
        drawCallback: function (settings) {
            // Actualizar contador total
            const totalCount = document.getElementById('total-count');
            if (totalCount && settings.json) {
                totalCount.textContent = settings.json.recordsTotal?.toLocaleString() || '0';
            }
        }
    });

    // Estilos adicionales para el buscador
    $('.dataTables_filter input').addClass('form-control form-control-sm');
    $('.dataTables_length select').addClass('form-select form-select-sm');
}

/**
 * Escape HTML para prevenir XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Confirmar eliminación de usuario con SweetAlert2
 */
function confirmDelete(id) {
    // Obtener CSRF token desde la tabla o meta tag
    const tableEl = document.getElementById('usuarios-table');
    const csrfToken = tableEl?.dataset.csrfToken ||
        document.querySelector('meta[name="csrf-token"]')?.content || '';
    const baseUrl = tableEl?.dataset.baseUrl || window.BASE_URL || '';

    Swal.fire({
        title: '¿Eliminar Usuario?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Crear form y enviar
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `${baseUrl}usuario/eliminar/${id}`;

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            document.body.appendChild(form);
            form.submit();
        }
    });
}
