<?php
// app/includes/navbar.php

// Asegúrate de que estas variables estén definidas antes de incluir este archivo
// En cada página, antes de require_once 'navbar.php', deberías tener:
// $nombre_usuario_sesion = $_SESSION['user_name'] ?? 'Usuario';
// $rol_usuario_sesion = $_SESSION['user_role'] ?? 'empleado';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">Flotilla Interna</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" aria-current="page" href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'solicitar_vehiculo.php') ? 'active' : ''; ?>" href="solicitar_vehiculo.php">Solicitar Vehículo</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'mis_solicitudes.php') ? 'active' : ''; ?>" href="mis_solicitudes.php">Mis Solicitudes</a>
                </li>
                <?php if (isset($rol_usuario_sesion) && ($rol_usuario_sesion === 'flotilla_manager' || $rol_usuario_sesion === 'admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'gestion_vehiculos.php') ? 'active' : ''; ?>" href="gestion_vehiculos.php">Gestión de Vehículos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'gestion_solicitudes.php') ? 'active' : ''; ?>" href="gestion_solicitudes.php">Gestión de Solicitudes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'gestion_mantenimientos.php') ? 'active' : ''; ?>" href="gestion_mantenimientos.php">Gestión de Mantenimientos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'reportes.php') ? 'active' : ''; ?>" href="reportes.php">Reportes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'gestion_documentos.php') ? 'active' : ''; ?>" href="gestion_documentos.php">Gestión de Documentos</a>
                    </li>
                <?php endif; ?>
                <?php if (isset($rol_usuario_sesion) && $rol_usuario_sesion === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'gestion_usuarios.php') ? 'active' : ''; ?>" href="gestion_usuarios.php">Gestión de Usuarios</a>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Hola, <?php echo htmlspecialchars($nombre_usuario_sesion ?? 'Usuario'); ?> (<?php echo htmlspecialchars($rol_usuario_sesion ?? 'empleado'); ?>)
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="#">Mi Perfil (próximamente)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>