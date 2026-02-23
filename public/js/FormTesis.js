document.addEventListener('DOMContentLoaded', function () {
    // 1. Bootstrap Validation Logic
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
    })();

    // 2. Dynamic Rows Logic
    const estudiantesContainer = document.getElementById('estudiantes-container');
    const docentesContainer = document.getElementById('docentes-container');
    const btnAddEstudiante = document.getElementById('btn-add-estudiante');
    const btnAddDocente = document.getElementById('btn-add-docente');

    // Index tracking
    let estudianteIndex = document.querySelectorAll('#estudiantes-container .input-group').length;
    let docenteIndex = document.querySelectorAll('#docentes-container .input-group').length;

    // Helper to init Select2 with AJAX
    function initSelect2Ajax(element, type, placeholder) {
        // Fallback for BASE_URL if not defined globaly
        const baseUrl = window.BASE_URL || '/';
        const ajaxUrl = `${baseUrl}investigacion/buscar-participantes`;

        console.log(`[FormTesis] Init Select2 for ${type}. URL: ${ajaxUrl}`);

        $(element).select2({
            width: '100%',
            // dropdownParent: $(element).closest('.glass-panel'), // Comentado para evitar problemas de overflow/z-index
            placeholder: placeholder,
            minimumInputLength: 2,
            language: {
                inputTooShort: function () { return "Ingrese al menos 2 caracteres..."; },
                searching: function () { return "Buscando en servidor..."; },
                noResults: function () { return "No se encontraron resultados"; },
                errorLoading: function () { return "Error al cargar resultados."; }
            },
            ajax: {
                url: ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term, // search term
                        type: type      // estudiante or docente
                    };
                },
                processResults: function (data) {
                    console.log(`[FormTesis] Resultados recibidos (${type}):`, data);
                    return {
                        results: data.results
                    };
                },
                cache: true,
                error: function (jqXHR, textStatus, errorThrown) {
                    if (textStatus === 'abort') {
                        return; // Ignorar cancelaciones de peticiones previas
                    }
                    console.error("[FormTesis] AJAX Error:", textStatus, errorThrown);
                    console.error("Response:", jqXHR.responseText);
                }
            }
        });
    }

    function createEstudianteRow(selectedValue = null, selectedName = null) {
        const div = document.createElement('div');
        div.className = 'input-group mb-2 animate-up';

        // For Edit mode: If we have a value, we must create an option so Select2 displays it correctly immediately
        const optionHtml = (selectedValue && selectedName)
            ? `<option value="${selectedValue}" selected>${selectedName}</option>`
            : '';

        div.innerHTML = `
            <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
            <div class="flex-grow-1">
                <select name="estudiantes[]" class="form-select select2-ajax-estudiante" required>
                    ${optionHtml}
                </select>
            </div>
            <button type="button" class="btn btn-outline-danger btn-remove"><i class="bi bi-trash"></i></button>
        `;

        // Handle Remove
        div.querySelector('.btn-remove').addEventListener('click', function () {
            div.remove();
        });

        estudiantesContainer.appendChild(div);

        // Init Select2
        const select = div.querySelector('select');
        initSelect2Ajax(select, 'estudiante', 'Buscar estudiante por nombre o legajo...');
    }

    function createDocenteRow(selectedValue = null, selectedName = null) {
        const div = document.createElement('div');
        div.className = 'input-group mb-2 animate-up';

        const optionHtml = (selectedValue && selectedName)
            ? `<option value="${selectedValue}" selected>${selectedName}</option>`
            : '';

        div.innerHTML = `
            <span class="input-group-text bg-white"><i class="bi bi-person-badge"></i></span>
            <div class="flex-grow-1">
                <select name="docentes[]" class="form-select select2-ajax-docente" required>
                     ${optionHtml}
                </select>
            </div>
            <button type="button" class="btn btn-outline-danger btn-remove"><i class="bi bi-trash"></i></button>
        `;

        // Handle Remove
        div.querySelector('.btn-remove').addEventListener('click', function () {
            div.remove();
        });

        docentesContainer.appendChild(div);

        // Init Select2
        const select = div.querySelector('select');
        initSelect2Ajax(select, 'docente', 'Buscar tutor por nombre o especialidad...');
    }

    if (btnAddEstudiante) {
        btnAddEstudiante.addEventListener('click', () => createEstudianteRow());
        // Init only if empty and we are in create mode (no server-side rendered rows)
        if (estudiantesContainer.children.length === 0 && !window.isEditMode) createEstudianteRow();
    }

    if (btnAddDocente) {
        btnAddDocente.addEventListener('click', () => createDocenteRow());
        if (docentesContainer.children.length === 0 && !window.isEditMode) createDocenteRow();
    }

    // Initialize existing rows (Edit Mode)
    if (window.initialEstudiantes && window.initialEstudiantes.length > 0) {
        window.initialEstudiantes.forEach(est => {
            const fullName = `${est.apellido}, ${est.nombre}`;
            createEstudianteRow(est.estudiante_id, fullName);
        });
    }

    if (window.initialDocentes && window.initialDocentes.length > 0) {
        window.initialDocentes.forEach(doc => {
            const fullName = `${doc.apellido}, ${doc.nombre}`;
            createDocenteRow(doc.docente_id, fullName);
        });
    }

    // Attach remove event to existing buttons (fallback)
    document.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', function () {
            this.closest('.input-group').remove();
        });
    });
});
