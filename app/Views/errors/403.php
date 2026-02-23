<?php
// Evitar inyección directa si no se define BASE_URL
if (!defined('BASE_URL')) {
    exit('No direct script access allowed');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Acceso Denegado</title>
    <!-- Bootstrap 5 CSS -->
    <link href="<?= BASE_URL ?>css/libraries/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            height: 100vh;
            overflow: hidden;
            background-color: #f1f5f9;
            background-image: 
                radial-gradient(at 0% 0%, rgba(245, 158, 11, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(220, 38, 38, 0.15) 0px, transparent 50%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .glass-container {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            border-radius: 32px;
            padding: 4rem 3rem;
            text-align: center;
            max-width: 600px;
            width: 90%;
            position: relative;
            overflow: hidden;
        }

        .error-code {
            font-size: 8rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            letter-spacing: -4px;
        }

        .error-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b; /* Slate 800 */
            margin-bottom: 1rem;
            letter-spacing: -0.5px;
        }

        .error-desc {
            color: #64748b; /* Slate 500 */
            font-size: 1.1rem;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .btn-home {
            background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            color: white;
            font-weight: 600;
            padding: 1rem 2.5rem;
            border-radius: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.4);
            display: inline-block;
        }

        .btn-home:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 35px -5px rgba(239, 68, 68, 0.5);
            color: white;
        }

        .blob {
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.6;
        }
        .blob-1 {
            top: -100px;
            left: -100px;
            background: #fcd34d;
        }
        .blob-2 {
            bottom: -100px;
            right: -100px;
            background: #fca5a5;
        }

    </style>
</head>
<body>

    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="glass-container">
        <div class="error-code">403</div>
        <h1 class="error-title">Acceso Denegado</h1>
        <p class="error-desc">
            No tienes los permisos suficientes para acceder a esta área protegida. Si crees que es un error, contacta al administrador.
        </p>
        <a href="<?= BASE_URL ?>escritorio" class="btn-home">
            Volver al Escritorio
        </a>
    </div>

</body>
</html>
