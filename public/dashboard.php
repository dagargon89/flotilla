<?php
// public/dashboard.php - CÓDIGO COMPLETO Y ACTUALIZADO (Conteo Simple de Vehículos Disponibles)
session_start(); // Siempre inicia la sesión al principio

// Incluye el archivo de conexión a la base de datos
require_once '../app/config/database.php';

// **VERIFICACIÓN DE SESIÓN:**
// Si el usuario NO está logueado, lo redirigimos de vuelta al login.
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Redirige al login si no hay sesión activa
    exit();
}

// Datos del usuario logueado (los obtuvimos del login y los guardamos en la sesión)
$nombre_usuario = $_SESSION['user_name'] ?? 'Usuario';
$rol_usuario = $_SESSION['user_role'] ?? 'empleado'; // Por si acaso no se definió el rol

// Lógica para conectar a la BD y obtener datos del dashboard
$db = connectDB();
$vehiculos_disponibles_count = 0;
$mis_solicitudes_pendientes_count = 0;
$solicitudes_por_aprobar_count = 0;

if ($db) {
    try {
        // LÓGICA SIMPLIFICADA PARA EL CONTADOR DE VEHÍCULOS DISPONIBLES EN EL DASHBOARD:
        // Solo cuenta los vehículos cuyo estatus en la tabla 'vehiculos' es directamente 'disponible'.
        // No considera si tienen solicitudes aprobadas para el momento actual.
        // Se actualizará cuando el estatus del vehículo cambie al marcar salida/regreso.
        $stmt_vehiculos = $db->prepare("
            SELECT COUNT(*) FROM vehiculos WHERE estatus = 'disponible'
        ");
        $stmt_vehiculos->execute();
        $vehiculos_disponibles_count = $stmt_vehiculos->fetchColumn();

        // Contar MIS solicitudes pendientes (para el usuario logueado)
        $stmt_mis_solicitudes = $db->prepare("SELECT COUNT(*) FROM solicitudes_vehiculos WHERE usuario_id = :user_id AND estatus_solicitud = 'pendiente'");
        $stmt_mis_solicitudes->bindParam(':user_id', $_SESSION['user_id']);
        $stmt_mis_solicitudes->execute();
        $mis_solicitudes_pendientes_count = $stmt_mis_solicitudes->fetchColumn();

        // Contar solicitudes pendientes para aprobar (solo si es flotilla_manager o admin)
        if ($rol_usuario === 'flotilla_manager' || $rol_usuario === 'admin') {
            $stmt_por_aprobar = $db->prepare("SELECT COUNT(*) FROM solicitudes_vehiculos WHERE estatus_solicitud = 'pendiente'");
            $stmt_por_aprobar->execute();
            $solicitudes_por_aprobar_count = $stmt_por_aprobar->fetchColumn();
        }

    } catch (PDOException $e) {
        error_log("Error al cargar datos del dashboard: " . $e->getMessage());
        // No mostramos el error al usuario, solo en logs
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Flotilla Interna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css' rel='stylesheet' />
</head>
<body>
    <?php
    // Asegúrate de definir estas variables ANTES de incluir la navbar
    $nombre_usuario_sesion = $_SESSION['user_name'] ?? 'Usuario';
    $rol_usuario_sesion = $_SESSION['user_role'] ?? 'empleado';
    require_once '../app/includes/navbar.php'; // Incluir la barra de navegación
    ?>

    <div class="container mt-4">
        <h1 class="mb-4">Bienvenido al Dashboard, <?php echo htmlspecialchars($nombre_usuario); ?></h1>

        <div class="row">
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Vehículos Disponibles</h5>
                        <p class="card-text fs-1 fw-bold"><?php echo $vehiculos_disponibles_count; ?></p>
                        <a href="solicitar_vehiculo.php" class="btn btn-primary">Solicitar Uno</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Mis Solicitudes Pendientes</h5>
                        <p class="card-text fs-1 fw-bold"><?php echo $mis_solicitudes_pendientes_count; ?></p>
                        <a href="mis_solicitudes.php" class="btn btn-info text-white">Ver Mis Solicitudes</a>
                    </div>
                </div>
            </div>
            <?php if ($rol_usuario === 'flotilla_manager' || $rol_usuario === 'admin'): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card text-center shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Solicitudes por Aprobar</h5>
                            <p class="card-text fs-1 fw-bold"><?php echo $solicitudes_por_aprobar_count; ?></p>
                            <a href="gestion_solicitudes.php" class="btn btn-success">Gestionar Solicitudes</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card mt-4 p-3 shadow-sm">
            <h3 class="mb-3">Calendario de Disponibilidad de Vehículos</h3>
            <div id='calendar' style="height: 500px;"></div>
            <p class="mt-2 text-muted">Aquí podrás ver qué vehículos están disponibles y cuándo.</p>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/es.global.min.js'></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                // Opciones generales del calendario
                initialView: 'dayGridMonth', // Vista inicial por mes
                locale: 'es', // Idioma en español
                headerToolbar: { // Botones en la cabecera
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' // Diferentes vistas
                },
                editable: false, // No permitimos arrastrar o redimensionar eventos desde aquí
                selectable: false, // No permitimos seleccionar rangos de fechas

                // Fuente de eventos: ¡Aquí apuntamos a nuestro script PHP!
                events: 'api/get_calendar_events.php', // Ruta relativa a public/

                // Opcional: Cuando se hace clic en un evento (reserva)
                eventClick: function(info) {
                    var event = info.event;
                    var msg = 'Vehículo: ' + event.extendedProps.vehiculo +
                              '\nSolicitante: ' + event.extendedProps.solicitante +
                              '\nPropósito: ' + event.extendedProps.proposito +
                              '\nEstatus: ' + event.extendedProps.estatus +
                              '\nInicio: ' + event.start.toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' }) +
                              '\nFin: ' + event.end.toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
                    alert(msg);
                    // Aquí podrías abrir un modal de Bootstrap con más detalles en lugar de alert
                },
                // Opcional: Personalizar el texto para cuando no hay eventos
                noEventsContent: 'No hay vehículos reservados para estas fechas.',
            });

            calendar.render(); // Renderiza el calendario
        });
    </script>
    <script src="js/main.js"></script> </body>
</html>