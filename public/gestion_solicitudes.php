<?php
// public/gestion_solicitudes.php - CÓDIGO COMPLETO Y CORREGIDO (Error Undefined $db)
session_start();
require_once '../app/config/database.php';

// ¡CORRECCIÓN CRÍTICA! Establecer la conexión a la base de datos aquí, al inicio.
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


// **VERIFICACIÓN DE ROL:**
// Este archivo es para 'flotilla_manager' y 'admin'.
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'flotilla_manager' && $_SESSION['user_role'] !== 'admin')) {
    header('Location: dashboard.php'); // Redirige al dashboard si no tiene permisos
    exit();
}

$nombre_usuario_sesion = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$rol_usuario_sesion = $_SESSION['user_role'];

$success_message = '';
$error_message = $error_message ?? ''; // Mantener el error si ya viene del bloque de amonestaciones

$solicitudes = []; // Para guardar las solicitudes que se mostrarán
$vehiculos_disponibles_para_seleccion = []; // Para la lista desplegable en el modal

// --- Lógica para procesar las acciones de aprobar/rechazar/asignar ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $solicitud_id = $_POST['solicitud_id'] ?? null;
    $action = $_POST['action'] ?? '';
    $observaciones = trim($_POST['observaciones_aprobacion'] ?? '');
    $vehiculo_asignado_id = filter_var($_POST['vehiculo_asignado_id'] ?? null, FILTER_VALIDATE_INT);

    if ($solicitud_id && ($action === 'aprobar' || $action === 'rechazar')) {
        try {
            $db->beginTransaction();

            $new_status = ($action === 'aprobar') ? 'aprobada' : 'rechazada';
            $vehiculo_to_update = null;

            // Obtener datos actuales de la solicitud y del usuario solicitante
            $stmt_get_solicitud_info = $db->prepare("
                SELECT s.vehiculo_id, s.fecha_salida_solicitada, s.fecha_regreso_solicitada,
                       u.nombre AS solicitante_nombre, u.correo_electronico AS solicitante_correo,
                       s.evento, s.descripcion, s.destino
                FROM solicitudes_vehiculos s
                JOIN usuarios u ON s.usuario_id = u.id
                WHERE s.id = :solicitud_id FOR UPDATE");
            $stmt_get_solicitud_info->bindParam(':solicitud_id', $solicitud_id);
            $stmt_get_solicitud_info->execute();
            $current_solicitud_info = $stmt_get_solicitud_info->fetch(PDO::FETCH_ASSOC);

            if (!$current_solicitud_info) {
                throw new Exception("Solicitud no encontrada para procesar.");
            }

            if ($action === 'aprobar') {
                if (!$vehiculo_asignado_id) {
                    throw new Exception("Debes seleccionar un vehículo para aprobar la solicitud.");
                }

                // Verificar disponibilidad del vehículo para las fechas solicitadas
                $stmt_overlap = $db->prepare("
                    SELECT COUNT(*) FROM solicitudes_vehiculos
                    WHERE vehiculo_id = :vehiculo_id
                    AND estatus_solicitud IN ('aprobada', 'en_curso')
                    AND (
                        (fecha_salida_solicitada < :fecha_regreso AND fecha_regreso_solicitada > :fecha_salida)
                    )
                    AND id != :solicitud_id_exclude
                ");
                $stmt_overlap->bindParam(':vehiculo_id', $vehiculo_asignado_id);
                $stmt_overlap->bindParam(':fecha_salida', $current_solicitud_info['fecha_salida_solicitada']);
                $stmt_overlap->bindParam(':fecha_regreso', $current_solicitud_info['fecha_regreso_solicitada']);
                $stmt_overlap->bindParam(':solicitud_id_exclude', $solicitud_id);
                $stmt_overlap->execute();

                if ($stmt_overlap->fetchColumn() > 0) {
                    throw new Exception("El vehículo seleccionado no está disponible en las fechas solicitadas. Por favor, elige otro.");
                }

                $vehiculo_to_update = $vehiculo_asignado_id;
            } else {
                $vehiculo_to_update = null;
            }

            // Actualizar el estado de la solicitud y asignar vehículo
            $stmt_update_sol = $db->prepare("UPDATE solicitudes_vehiculos SET estatus_solicitud = :new_status, fecha_aprobacion = NOW(), aprobado_por = :aprobado_por, observaciones_aprobacion = :observaciones, vehiculo_id = :vehiculo_id WHERE id = :solicitud_id");
            $stmt_update_sol->bindParam(':new_status', $new_status);
            $stmt_update_sol->bindParam(':aprobado_por', $user_id);
            $stmt_update_sol->bindParam(':observaciones', $observaciones);
            $stmt_update_sol->bindParam(':vehiculo_id', $vehiculo_to_update);
            $stmt_update_sol->bindParam(':solicitud_id', $solicitud_id);
            $stmt_update_sol->execute();

            if ($stmt_update_sol->rowCount() > 0) {
                $success_message = 'Solicitud ' . ($action === 'aprobar' ? 'aprobada' : 'rechazada') . ' con éxito.';

                $db->commit(); // Confirmar los cambios en BD antes de enviar email

                // --- ENVÍO DE CORREO ELECTRÓNICO (PENDIENTE DE CONFIGURAR) ---
                // $solicitante_email = $current_solicitud_info['solicitante_correo'];
                // $solicitante_nombre = $current_solicitud_info['solicitante_nombre'];
                // $subject = "Actualización de tu solicitud de vehículo: #" . $solicitud_id;

                // $email_body_html = "
                //     <html>
                //     <head>
                //         <title>Actualización de Solicitud de Vehículo</title>
                //         <style>/* ... */</style>
                //     </head>
                //     <body>/* ... */</body>
                //     </html>";
                // $email_body_text = "Estimado/a " . $solicitante_nombre . ",\n\n...";

                // if (function_exists('sendEmail')) { // Asegurarse que la función sendEmail exista
                //     sendEmail($solicitante_email, $solicitante_nombre, $subject, $email_body_html, $email_body_text);
                // } else {
                //     error_log("ADVERTENCIA: La función sendEmail no está disponible. No se envió el correo.");
                // }
                // --- FIN ENVÍO DE CORREO ELECTRÓNICO ---

            } else {
                $error_message = 'La solicitud no pudo ser actualizada. Asegúrate de que no esté ya procesada o de que el ID sea correcto.';
                $db->rollBack();
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al gestionar solicitud y enviar correo: " . $e->getMessage());
            $error_message = 'Ocurrió un error al procesar la solicitud: ' . $e->getMessage();
        }
    }
}

// --- Obtener todas las solicitudes para mostrar en la tabla ---
if ($db) {
    try {
        $stmt = $db->query("
            SELECT
                s.id AS solicitud_id,
                u.nombre AS usuario_nombre,
                s.fecha_salida_solicitada,
                s.fecha_regreso_solicitada,
                s.evento,
                s.descripcion,
                s.destino,
                s.estatus_solicitud,
                s.observaciones_aprobacion,
                v.marca,
                v.modelo,
                v.placas,
                v.id AS vehiculo_actual_id
            FROM solicitudes_vehiculos s
            JOIN usuarios u ON s.usuario_id = u.id
            LEFT JOIN vehiculos v ON s.vehiculo_id = v.id
            ORDER BY s.fecha_creacion DESC
        ");
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Obtener vehículos disponibles para el dropdown en el modal ---
        $stmt_vehiculos_disp = $db->query("SELECT id, marca, modelo, placas FROM vehiculos WHERE estatus IN ('disponible', 'en_mantenimiento') ORDER BY marca, modelo");
        $vehiculos_disponibles_para_seleccion = $stmt_vehiculos_disp->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al cargar datos para gestión de solicitudes: " . $e->getMessage());
        $error_message = 'No se pudieron cargar las solicitudes o vehículos.';
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Solicitudes - Flotilla Interna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>
    <?php
    $nombre_usuario_sesion = $_SESSION['user_name'] ?? 'Usuario';
    $rol_usuario_sesion = $_SESSION['user_role'] ?? 'empleado';
    require_once '../app/includes/navbar.php';
    ?>
    <?php require_once '../app/includes/alert_banner.php'; // Incluir el banner de alertas 
    ?>

    <div class="container mt-4">
        <h1 class="mb-4">Gestión de Solicitudes de Vehículos</h1>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($solicitudes)): ?>
            <div class="alert alert-info" role="alert">
                No hay solicitudes de vehículos para mostrar.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID Sol.</th>
                            <th>Solicitante</th>
                            <th>Salida Deseada</th>
                            <th>Regreso Deseado</th>
                            <th>Evento</th>
                            <th>Descripción</th>
                            <th>Destino</th>
                            <th>Vehículo Asignado</th>
                            <th>Estatus</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $solicitud): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($solicitud['solicitud_id']); ?></td>
                                <td><?php echo htmlspecialchars($solicitud['usuario_nombre']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_salida_solicitada'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_regreso_solicitada'])); ?></td>
                                <td><?php echo htmlspecialchars($solicitud['evento']); ?></td>
                                <td><?php echo htmlspecialchars($solicitud['descripcion']); ?></td>
                                <td><?php echo htmlspecialchars($solicitud['destino']); ?></td>
                                <td>
                                    <?php if ($solicitud['marca']): ?>
                                        <?php echo htmlspecialchars($solicitud['marca'] . ' ' . $solicitud['modelo'] . ' (' . $solicitud['placas'] . ')'); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    switch ($solicitud['estatus_solicitud']) {
                                        case 'pendiente':
                                            $status_class = 'badge bg-warning text-dark';
                                            break;
                                        case 'aprobada':
                                            $status_class = 'badge bg-success';
                                            break;
                                        case 'rechazada':
                                            $status_class = 'badge bg-danger';
                                            break;
                                        case 'en_curso':
                                            $status_class = 'badge bg-primary';
                                            break;
                                        case 'completada':
                                            $status_class = 'badge bg-secondary';
                                            break;
                                        case 'cancelada':
                                            $status_class = 'badge bg-info';
                                            break;
                                    }
                                    ?>
                                    <span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($solicitud['estatus_solicitud'])); ?></span>
                                </td>
                                <td>
                                    <?php if ($solicitud['estatus_solicitud'] === 'pendiente'): ?>
                                        <div class="table-actions-cell">
                                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveRejectModal"
                                                data-solicitud-id="<?php echo $solicitud['solicitud_id']; ?>" data-action="aprobar"
                                                data-usuario="<?php echo htmlspecialchars($solicitud['usuario_nombre']); ?>"
                                                data-salida="<?php echo htmlspecialchars($solicitud['fecha_salida_solicitada']); ?>"
                                                data-regreso="<?php echo htmlspecialchars($solicitud['fecha_regreso_solicitada']); ?>"
                                                data-observaciones-aprobacion="<?php echo htmlspecialchars($solicitud['observaciones_aprobacion']); ?>"
                                                data-vehiculo-actual-id="<?php echo htmlspecialchars($solicitud['vehiculo_actual_id']); ?>"
                                                data-vehiculo-info-display="<?php echo htmlspecialchars($solicitud['marca'] ? $solicitud['marca'] . ' ' . $solicitud['modelo'] . ' (' . $solicitud['placas'] . ')' : 'Sin asignar'); ?>">
                                                <i class="bi bi-check-circle"></i> Aprobar
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#approveRejectModal"
                                                data-solicitud-id="<?php echo $solicitud['solicitud_id']; ?>" data-action="rechazar"
                                                data-usuario="<?php echo htmlspecialchars($solicitud['usuario_nombre']); ?>"
                                                data-observaciones-aprobacion="<?php echo htmlspecialchars($solicitud['observaciones_aprobacion']); ?>">
                                                <i class="bi bi-x-circle"></i> Rechazar
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#viewDetailsModal"
                                            data-solicitud-id="<?php echo $solicitud['solicitud_id']; ?>"
                                            data-usuario="<?php echo htmlspecialchars($solicitud['usuario_nombre']); ?>"
                                            data-salida="<?php echo htmlspecialchars($solicitud['fecha_salida_solicitada']); ?>"
                                            data-regreso="<?php echo htmlspecialchars($solicitud['fecha_regreso_solicitada']); ?>"
                                            data-evento="<?php echo htmlspecialchars($solicitud['evento']); ?>"
                                            data-descripcion="<?php echo htmlspecialchars($solicitud['descripcion']); ?>"
                                            data-destino="<?php echo htmlspecialchars($solicitud['destino']); ?>"
                                            data-vehiculo="<?php echo htmlspecialchars($solicitud['marca'] ? $solicitud['marca'] . ' ' . $solicitud['modelo'] . ' (' . $solicitud['placas'] . ')' : 'Sin asignar'); ?>"
                                            data-estatus="<?php echo htmlspecialchars(ucfirst($solicitud['estatus_solicitud'])); ?>"
                                            data-observaciones-aprobacion="<?php echo htmlspecialchars($solicitud['observaciones_aprobacion']); ?>">
                                            <i class="bi bi-eye"></i> Ver Detalles
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="modal fade" id="approveRejectModal" tabindex="-1" aria-labelledby="approveRejectModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="approveRejectModalLabel">Gestionar Solicitud</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="gestion_solicitudes.php" method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="solicitud_id" id="modalSolicitudId">
                            <input type="hidden" name="action" id="modalAction">
                            <input type="hidden" name="vehiculo_info_display_modal" id="vehiculoInfoDisplayModal">
                            <p>Estás a punto de <strong id="modalActionText"></strong> la solicitud de <strong id="modalUserName"></strong>.</p>
                            <div id="vehicleAssignmentSection">
                                <div class="mb-3">
                                    <label for="vehiculo_asignado_id" class="form-label">Asignar Vehículo Disponible</label>
                                    <select class="form-select" id="vehiculo_asignado_id" name="vehiculo_asignado_id">
                                        <option value="">Selecciona un vehículo (Obligatorio para Aprobar)</option>
                                        <?php foreach ($vehiculos_disponibles_para_seleccion as $vehiculo_opcion): ?>
                                            <option value="<?php echo htmlspecialchars($vehiculo_opcion['id']); ?>">
                                                <?php echo htmlspecialchars($vehiculo_opcion['marca'] . ' ' . $vehiculo_opcion['modelo'] . ' (' . $vehiculo_opcion['placas'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <p class="text-info small">Solo se muestran vehículos que no están asignados actualmente a otras solicitudes *aprobadas* en las fechas solicitadas.</p>
                            </div>
                            <div class="mb-3">
                                <label for="observaciones_aprobacion" class="form-label">Observaciones (Opcional)</label>
                                <textarea class="form-control" id="observaciones_aprobacion_modal" name="observaciones_aprobacion" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn" id="modalSubmitBtn"></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewDetailsModalLabel">Detalles de Solicitud</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Solicitante:</strong> <span id="detailUserName"></span></p>
                        <p><strong>Salida Deseada:</strong> <span id="detailFechaSalida"></span></p>
                        <p><strong>Regreso Deseado:</strong> <span id="detailFechaRegreso"></span></p>
                        <p><strong>Evento:</strong> <span id="detailEvento"></span></p>
                        <p><strong>Descripción:</strong> <span id="detailDescripcion"></span></p>
                        <p><strong>Destino:</strong> <span id="detailDestino"></span></p>
                        <p><strong>Vehículo Asignado:</strong> <span id="detailVehiculoAsignado"></span></p>
                        <p><strong>Estatus:</strong> <span id="detailEstatus" class="badge"></span></p>
                        <p><strong>Observaciones del Gestor:</strong> <span id="detailObservacionesAprobacion"></span></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // JavaScript para manejar el modal de Aprobar/Rechazar Solicitud
        document.addEventListener('DOMContentLoaded', function() {
            var approveRejectModal = document.getElementById('approveRejectModal');
            approveRejectModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var solicitudId = button.getAttribute('data-solicitud-id');
                var action = button.getAttribute('data-action');
                var userName = button.getAttribute('data-usuario');
                var salida = button.getAttribute('data-salida');
                var regreso = button.getAttribute('data-regreso');
                var observacionesAprobacion = button.getAttribute('data-observaciones-aprobacion');
                var vehiculoActualId = button.getAttribute('data-vehiculo-actual-id');
                var vehiculoInfoDisplay = button.getAttribute('data-vehiculo-info-display');

                var modalSolicitudId = approveRejectModal.querySelector('#modalSolicitudId');
                var modalAction = approveRejectModal.querySelector('#modalAction');
                var vehiculoInfoDisplayModal = approveRejectModal.querySelector('#vehiculoInfoDisplayModal');
                var modalActionText = approveRejectModal.querySelector('#modalActionText');
                var modalUserName = approveRejectModal.querySelector('#modalUserName');
                var modalSubmitBtn = approveRejectModal.querySelector('#modalSubmitBtn');
                var vehiculoAssignmentSection = approveRejectModal.querySelector('#vehicleAssignmentSection');
                var vehiculoAsignadoSelect = approveRejectModal.querySelector('#vehiculo_asignado_id');
                var observacionesModal = approveRejectModal.querySelector('#observaciones_aprobacion_modal');

                modalSolicitudId.value = solicitudId;
                modalAction.value = action;
                modalUserName.textContent = userName;
                observacionesModal.value = observacionesAprobacion;
                vehiculoInfoDisplayModal.value = vehiculoInfoDisplay;

                vehiculoAsignadoSelect.value = vehiculoActualId || '';

                if (action === 'aprobar') {
                    modalActionText.textContent = 'APROBAR';
                    modalSubmitBtn.textContent = 'Aprobar Solicitud';
                    modalSubmitBtn.className = 'btn btn-success';
                    vehiculoAssignmentSection.style.display = 'block';
                    vehiculoAsignadoSelect.setAttribute('required', 'required');
                } else if (action === 'rechazar') {
                    modalActionText.textContent = 'RECHAZAR';
                    modalSubmitBtn.textContent = 'Rechazar Solicitud';
                    modalSubmitBtn.className = 'btn btn-danger';
                    vehiculoAssignmentSection.style.display = 'none';
                    vehiculoAsignadoSelect.removeAttribute('required');
                    vehiculoAsignadoSelect.value = '';
                }
            });

            // JavaScript para manejar el modal de Ver Detalles
            var viewDetailsModal = document.getElementById('viewDetailsModal');
            viewDetailsModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;

                document.getElementById('detailUserName').textContent = button.getAttribute('data-usuario');
                document.getElementById('detailFechaSalida').textContent = new Date(button.getAttribute('data-salida')).toLocaleString('es-MX', {
                    dateStyle: 'medium',
                    timeStyle: 'short'
                });
                document.getElementById('detailFechaRegreso').textContent = new Date(button.getAttribute('data-regreso')).toLocaleString('es-MX', {
                    dateStyle: 'medium',
                    timeStyle: 'short'
                });
                document.getElementById('detailEvento').textContent = button.getAttribute('data-evento');
                document.getElementById('detailDescripcion').textContent = button.getAttribute('data-descripcion');
                document.getElementById('detailDestino').textContent = button.getAttribute('data-destino');
                document.getElementById('detailVehiculoAsignado').textContent = button.getAttribute('data-vehiculo');

                var statusBadge = document.getElementById('detailEstatus');
                statusBadge.textContent = button.getAttribute('data-estatus');
                statusBadge.className = 'badge';
                switch (button.getAttribute('data-estatus').toLowerCase()) {
                    case 'pendiente':
                        statusBadge.classList.add('bg-warning', 'text-dark');
                        break;
                    case 'aprobada':
                        statusBadge.classList.add('bg-success');
                        break;
                    case 'rechazada':
                        statusBadge.classList.add('bg-danger');
                        break;
                    case 'en_curso':
                        statusBadge.classList.add('bg-primary');
                        break;
                    case 'completada':
                        statusBadge.classList.add('bg-secondary');
                        break;
                    case 'cancelada':
                        statusBadge.classList.add('bg-info');
                        break;
                }

                document.getElementById('detailObservacionesAprobacion').textContent = button.getAttribute('data-observaciones-aprobacion');
            });
        });
    </script>
</body>

</html>