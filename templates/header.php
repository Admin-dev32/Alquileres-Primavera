<?php require_once __DIR__ . '/../config/auth.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema interno — Alquileres Primavera</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/bootstrap.bundle.min.js" defer></script>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <?php
        $settingsData = [];
        $logoPath = '';
        $brandName = 'Alquileres Primavera';
        if (isset($pdo)) {
            try {
                $settingsQuery = $pdo->query('SELECT * FROM settings LIMIT 1');
                $settingsData = $settingsQuery->fetch();
                $logoPath = $settingsData['logo_path'] ?? '';
                if (empty($logoPath) && !empty($settingsData['business_logo'])) {
                    $logoPath = $settingsData['business_logo'];
                }
                $brandName = $settingsData['business_name'] ?? $brandName;
            } catch (PDOException $e) {
                error_log('Error cargando configuración en header: ' . $e->getMessage());
            }
        }
        $user = current_user();
        ?>
        <a class="navbar-brand fw-bold d-flex align-items-center" href="/index.php">
            <?php if (!empty($logoPath)): ?>
                <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" style="height:40px; width:auto; margin-right:8px;">
            <?php endif; ?>
            <span><?php echo htmlspecialchars($brandName); ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPrincipal" aria-controls="navPrincipal" aria-expanded="false" aria-label="Alternar navegación">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navPrincipal">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/index.php">Inicio</a></li>
                <li class="nav-item"><a class="nav-link" href="/documents/index.php">Documentos</a></li>
                <li class="nav-item"><a class="nav-link" href="/items/index.php">Artículos / Productos</a></li>
                <li class="nav-item"><a class="nav-link" href="/finance/index.php">Finanzas</a></li>
                <li class="nav-item"><a class="nav-link" href="/settings/index.php">Configuración</a></li>
                <?php if (function_exists('user_has_permission') && user_has_permission('manage_users')): ?>
                    <li class="nav-item"><a class="nav-link" href="/users/index.php">Usuarios</a></li>
                <?php endif; ?>
            </ul>
            <?php if ($user): ?>
                <ul class="navbar-nav ms-lg-3 mb-0">
                    <li class="nav-item d-flex align-items-center">
                        <span class="nav-link mb-0">Sesión: <?php echo htmlspecialchars($user['name'] ?? ($user['email'] ?? '')); ?></span>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="/auth/logout.php">Cerrar sesión</a></li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>
<div class="container my-4">
