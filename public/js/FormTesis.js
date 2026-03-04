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

    function createEstudianteRow(value = '') {
        const div = document.createElement('div');
        div.className = 'input-group mb-2 animate-up';

        div.innerHTML = `
            <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
            <input type="text" name="estudiantes[]" class="form-control" placeholder="Nombre completo del estudiante" value="${value}" required>
            <button type="button" class="btn btn-outline-danger btn-remove"><i class="bi bi-trash"></i></button>
        `;

        // Handle Remove
        div.querySelector('.btn-remove').addEventListener('click', function () {
            div.remove();
        });

        estudiantesContainer.appendChild(div);
    }

    function createDocenteRow(value = '') {
        const div = document.createElement('div');
        div.className = 'input-group mb-2 animate-up';

        div.innerHTML = `
            <span class="input-group-text bg-white"><i class="bi bi-person-badge"></i></span>
            <input type="text" name="docentes[]" class="form-control" placeholder="Nombre completo del tutor/asesor" value="${value}" required>
            <button type="button" class="btn btn-outline-danger btn-remove"><i class="bi bi-trash"></i></button>
        `;

        // Handle Remove
        div.querySelector('.btn-remove').addEventListener('click', function () {
            div.remove();
        });

        docentesContainer.appendChild(div);
    }

    if (btnAddEstudiante) {
        btnAddEstudiante.addEventListener('click', () => createEstudianteRow());
        // Init only if empty and we are in create mode
        if (estudiantesContainer.children.length === 0 && !window.isEditMode) createEstudianteRow();
    }

    if (btnAddDocente) {
        btnAddDocente.addEventListener('click', () => createDocenteRow());
        if (docentesContainer.children.length === 0 && !window.isEditMode) createDocenteRow();
    }

    // Initialize existing rows (Edit Mode)
    if (window.initialEstudiantes && window.initialEstudiantes.length > 0) {
        window.initialEstudiantes.forEach(est => {
            const name = est.display_name || (est.apellido ? `${est.apellido}, ${est.nombre}` : '');
            createEstudianteRow(name);
        });
    }

    if (window.initialDocentes && window.initialDocentes.length > 0) {
        window.initialDocentes.forEach(doc => {
            const name = doc.display_name || (doc.apellido ? `${doc.apellido}, ${doc.nombre}` : '');
            createDocenteRow(name);
        });
    }

    // Attach remove event to existing buttons (fallback)
    document.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', function () {
            this.closest('.input-group').remove();
        });
    });
});
