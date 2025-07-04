<?php
// public/dashboard.php - CÓDIGO COMPLETO Y CORREGIDO (Global Auth Redirect y Estatus de Usuario)
session_start(); // Siempre inicia la sesión al principio

// Incluye el archivo de conexión a la base de datos
require_once '../app/config/database.php';

// Establecer la conexión a la base de datos al inicio
$db = connectDB();

// Fetch current user's detailed status and amonestaciones for banner and logic
$current_user_estatus_usuario = $_SESSION['user_role'] ?? 'empleado'; // Default, will be overwritten
$current_user_amonestaciones_count = 0;
$current_user_recent_amonestaciones_text = ''; // Texto para el banner

if (isset($_SESSION['user_id']) && $db) {
    try {
        // Obtener el estatus_usuario del usuario logueado desde la DB (más fiable que la sesión sola)
        $stmt_user_full_status = $db->prepare("SELECT estatus_usuario FROM usuarios WHERE id = :user_id");
        $stmt_user_full_status->bindParam(':user_id', $_SESSION['user_id']);
        $stmt_user_full_status->execute();
        $user_full_status_result = $stmt_user_full_status->fetch(PDO::FETCH_ASSOC);
        if ($user_full_status_result) {
            $current_user_estatus_usuario = $user_full_status_result['estatus_usuario'];
            $_SESSION['user_estatus_usuario'] = $current_user_estatus_usuario; // Actualizar la sesión
        }

        // Si el usuario está 'amonestado', obtener los detalles de las amonestaciones para el banner
        if ($current_user_estatus_usuario === 'amonestado') {
            $stmt_amonestaciones = $db->prepare("
                SELECT COUNT(*) as total_count,
                       GROUP_CONCAT(CONCAT(DATE_FORMAT(fecha_amonestacion, '%d/%m'), ' (', tipo_amonestacion, ')') ORDER BY fecha_amonestacion DESC SEPARATOR '; ') AS recent_descriptions
                FROM amonestaciones
                WHERE usuario_id = :user_id
                LIMIT 3
            ");
            $stmt_amonestaciones->bindParam(':user_id', $_SESSION['user_id']);
            $stmt_amonestaciones->execute();
            $amonestacion_data = $stmt_amonestaciones->fetch(PDO::FETCH_ASSOC);

            if ($amonestacion_data) {
                $current_user_amonestaciones_count = $amonestacion_data['total_count'];
                $current_user_recent_amonestaciones_text = $amonestacion_data['recent_descriptions'] ?: 'Ninguna reciente.';
            }
        }
    } catch (PDOException $e) {
        error_log("Error al obtener estatus de usuario/amonestaciones para banner: " . $e->getMessage());
        $current_user_estatus_usuario = 'activo';
        $error_message = 'Error al cargar tu estatus o amonestaciones. Contacta al administrador.';
    }
}

// ¡CORRECCIÓN CRÍTICA! Incluir el redireccionador global para suspendidos AQUI.
require_once '../app/includes/global_auth_redirect.php';

// **VERIFICACIÓN DE SESIÓN:**
// Si el usuario NO está logueado, lo redirigimos de vuelta al login.
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Redirige al login si no hay sesión activa
    exit();
}

// Datos del usuario logueado (los obtuvimos del login y los guardamos en la sesión)
$nombre_usuario = $_SESSION['user_name'] ?? 'Usuario';
$rol_usuario = $_SESSION['user_role'] ?? 'empleado';

// Lógica para obtener datos del dashboard (contadores)
$vehiculos_disponibles_count = 0;
$mis_solicitudes_pendientes_count = 0;
$solicitudes_por_aprobar_count = 0;

if ($db) {
    try {
        $stmt_vehiculos = $db->prepare("
            SELECT COUNT(*) FROM vehiculos WHERE estatus = 'disponible'
        ");
        $stmt_vehiculos->execute();
        $vehiculos_disponibles_count = $stmt_vehiculos->fetchColumn();

        $stmt_mis_solicitudes = $db->prepare("SELECT COUNT(*) FROM solicitudes_vehiculos WHERE usuario_id = :user_id AND estatus_solicitud = 'pendiente'");
        $stmt_mis_solicitudes->bindParam(':user_id', $_SESSION['user_id']);
        $stmt_mis_solicitudes->execute();
        $mis_solicitudes_pendientes_count = $stmt_mis_solicitudes->fetchColumn();

        if ($rol_usuario === 'flotilla_manager' || $rol_usuario === 'admin') {
            $stmt_por_aprobar = $db->prepare("SELECT COUNT(*) FROM solicitudes_vehiculos WHERE estatus_solicitud = 'pendiente'");
            $stmt_por_aprobar->execute();
            $solicitudes_por_aprobar_count = $stmt_por_aprobar->fetchColumn();
        }
    } catch (PDOException $e) {
        error_log("Error al cargar datos del dashboard: " . $e->getMessage());
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
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>
    <?php
    $nombre_usuario_sesion = $_SESSION['user_name'] ?? 'Usuario';
    $rol_usuario_sesion = $_SESSION['user_role'] ?? 'empleado';
    require_once '../app/includes/navbar.php'; // Incluir la barra de navegación
    ?>
    <?php require_once '../app/includes/alert_banner.php'; // Incluir el banner de alertas 
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
    <!-- Scripts de FullCalendar -->
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
                        '\nEvento: ' + event.extendedProps.evento + // Usar 'evento'
                        '\nDescripción: ' + event.extendedProps.descripcion + // Usar 'descripcion'
                        '\nEstatus: ' + event.extendedProps.estatus +
                        '\nInicio: ' + event.start.toLocaleString('es-MX', {
                            dateStyle: 'medium',
                            timeStyle: 'short'
                        }) +
                        '\nFin: ' + event.end.toLocaleString('es-MX', {
                            dateStyle: 'medium',
                            timeStyle: 'short'
                        });
                    alert(msg);
                },
                // Opcional: Personalizar el texto para cuando no hay eventos
                noEventsContent: 'No hay vehículos reservados para estas fechas.',
            });

            calendar.render(); // Renderiza el calendario
        });
    </script>
    <script src="js/main.js"></script>
</body>

</html>