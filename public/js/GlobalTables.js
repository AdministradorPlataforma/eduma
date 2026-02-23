/**
 * GlobalTables.js
 * Configuración global para DataTables en EDUMA
 */ // turbo
document.addEventListener('DOMContentLoaded', function () {
    // Detectar tablas con clase 'table' o 'datatable'
    // Excluir tablas que explícitamente no quieran ser dinámicas (clase 'no-datatable')
    const tables = document.querySelectorAll('table:not(.no-datatable)');

    tables.forEach(table => {
        // Validación extra: ignorar si tiene la clase no-datatable (redundancia por seguridad)
        if (table.classList.contains('no-datatable')) return;

        // Validación: ignorar si ya está inicializada
        if ($.fn.DataTable.isDataTable(table)) return;

        // Inicializar DataTable
        $(table).DataTable({
            language: {
                "sProcessing": "Procesando...",
                "sLengthMenu": "Mostrar _MENU_ registros",
                "sZeroRecords": "No se encontraron resultados",
                "sEmptyTable": "Ningún dato disponible en esta tabla",
                "sInfo": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                "sInfoEmpty": "Mostrando 0 a 0 de 0 registros",
                "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
                "sInfoPostFix": "",
                "sSearch": "Buscar:",
                "sUrl": "",
                "sInfoThousands": ",",
                "sLoadingRecords": "Cargando...",
                "oPaginate": {
                    "sFirst": "Primero",
                    "sLast": "Último",
                    "sNext": "Siguiente",
                    "sPrevious": "Anterior"
                },
                "oAria": {
                    "sSortAscending": ": Activar para ordenar la columna de manera ascendente",
                    "sSortDescending": ": Activar para ordenar la columna de manera descendente"
                }
            },
            responsive: true,
            autoWidth: false,
            // Styling Premium
            // L (Length) = Top Left
            // f (Filter) = Top Right
            // r (Processing)
            // t (Table)
            // i (Info) = Bottom Left
            // p (Pagination) = Bottom Right
            // Agregamos wrappers específicos para Flexbox
            dom: '<"d-flex justify-content-between align-items-center mb-4"<"d-flex align-items-center gap-2"l><"d-flex align-items-center gap-2"f>>rt<"d-flex justify-content-between align-items-center mt-4 pt-3 border-top border-light"<"text-muted small fw-600"i><p>>',
            initComplete: function () {
                const api = this.api();
                const wrapper = $(api.table().container());
                wrapper.addClass('animate-up');

                // Add icons/styling manually if needed
                const searchInput = wrapper.find('.dataTables_filter input');
                searchInput.attr('placeholder', 'Buscar registros...');
                searchInput.removeClass('form-control-sm'); // Remove default small size
            }
        });
    });
});
