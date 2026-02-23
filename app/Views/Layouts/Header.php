<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - EDUMA' : 'EDUMA'; ?></title>
    <meta name="csrf-token" content="<?= \App\Helpers\CSRFHelper::getToken() ?>">
    
    <!-- Bootstrap 5 CSS -->
    <link href="<?= BASE_URL ?>css/libraries/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/libraries/bootstrap-icons.css">

    <!-- DataTables CSS + Plugins -->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/libraries/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/libraries/responsive.bootstrap5.min.css">


    <!-- Google Fonts: Inter (Mantener CDN por ahora o descargar si es crítico) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Estilos Globales -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/Main.css?v=<?= time() ?>">

    
    <!-- Estilos Específicos de la Vista -->
    <?php if (isset($extraCSS)): ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/<?php echo $extraCSS; ?>.css">
    <?php endif; ?>



    <!-- Global Config -->
    <script>
        window.BASE_URL = "<?= BASE_URL ?>";
    </script>
</head>
<body data-base-url="<?= BASE_URL ?>" class="<?= $bodyClass ?? '' ?>">
<div class="app-container">
