---
description: Creación de una nueva vista siguiendo el estándar EDUMA
---

Para crear una nueva vista en el proyecto, sigue estos pasos:

1. **Definir la Ruta**: Asegúrate de que la URL esté registrada en `routes/web.php`.
2. **Crear la Vista**: Crea el archivo en `app/views/{modulo}/{nombre}.php`.
   - Utiliza exclusivamente los `include_once` para los layouts.
   - Envuelve el contenido en `<main class="content-wrapper">`.
3. **Crear el Asset CSS**: Crea `public/css/{modulo}.css`.
   - No repitas estilos globales.
   - Enfócate en la personalización premium.
4. **Crear el Asset JS**: Crea `public/js/{modulo}.js` si la vista requiere interactividad.
5. **Verificar Assets**: Asegúrate de que el `Header.php` incluya dinámicamente los archivos CSS/JS correspondientes según la vista.
