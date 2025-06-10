<?php
// public/gestion_solicitudes.php - CÓDIGO COMPLETO Y CORREGIDO
session_start();
require_once '../app/config/database.php';

// **VERIFICACIÓN DE ROL:**
// Solo 'flotilla_manager' y 'admin' pueden acceder a esta página.
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'flotilla_manager' && $_SESSION['user_role'] !== 'admin')) {
    header('Location: dashboard.php'); // Redirige al dashboard si no tiene permisos
    exit();
}

$nombre_usuario = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$rol_usuario = $_SESSION['user_role'];

$success_message = '';
$error_message = '';

$db = connectDB();
$solicitudes = []; // Para guardar las solicitudes que se mostrarán
$vehiculos_disponibles_para_seleccion = []; // Para la lista desplegable en el modal

// --- Lógica para procesar las acciones de aprobar/rechazar/asignar ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $solicitud_id = $_POST['solicitud_id'] ?? null;
    $action = $_POST['action'] ?? '';
    $observaciones = trim($_POST['observaciones_aprobacion'] ?? '');
    $vehiculo_asignado_id = filter_var($_POST['vehiculo_asignado_id'] ?? null, FILTER_VALIDATE_INT); // Nuevo: ID del vehículo seleccionado

    if ($solicitud_id && ($action === 'aprobar' || $action === 'rechazar')) {
        try {
            $db->beginTransaction();

            $new_status = ($action === 'aprobar') ? 'aprobada' : 'rechazada';
            $vehiculo_to_update = null; // Guardará el ID del vehículo si se aprueba

            // Obtener datos actuales de la solicitud para verificar fechas y vehículo
            $stmt_check = $db->prepare("SELECT vehiculo_id, fecha_salida_solicitada, fecha_regreso_solicitada FROM solicitudes_vehiculos WHERE id = :solicitud_id FOR UPDATE"); // Bloqueamos la fila
            $stmt_check->bindParam(':solicitud_id', $solicitud_id);
            $stmt_check->execute();
            $current_solicitud = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if (!$current_solicitud) {
                throw new Exception("Solicitud no encontrada.");
            }
            
            // Si la acción es 'aprobar', necesitamos validar y asignar el vehículo
            if ($action === 'aprobar') {
                if (!$vehiculo_asignado_id) {
                    throw new Exception("Debes seleccionar un vehículo para aprobar la solicitud.");
                }

                // **Verificar disponibilidad del vehículo para las fechas solicitadas**
                // Esta es una verificación simple. En un sistema real, sería más robusta.
                // Verifica que no haya otro vehículo_id asignado a una solicitud
                // con estatus 'aprobada' o 'en_curso' que se solape con las fechas
                $stmt_overlap = $db->prepare("
                    SELECT COUNT(*) FROM solicitudes_vehiculos
                    WHERE vehiculo_id = :vehiculo_id
                    AND estatus_solicitud IN ('aprobada', 'en_curso')
                    AND (
                        (fecha_salida_solicitada < :fecha_regreso AND fecha_regreso_solicitada > :fecha_salida)
                    )
                    AND id != :solicitud_id_exclude -- Excluir la solicitud actual si es una re-aprobación o edición
                ");
                $stmt_overlap->bindParam(':vehiculo_id', $vehiculo_asignado_id);
                $stmt_overlap->bindParam(':fecha_salida', $current_solicitud['fecha_salida_solicitada']);
                $stmt_overlap->bindParam(':fecha_regreso', $current_solicitud['fecha_regreso_solicitada']);
                $stmt_overlap->bindParam(':solicitud_id_exclude', $solicitud_id);
                $stmt_overlap->execute();

                if ($stmt_overlap->fetchColumn() > 0) {
                    throw new Exception("El vehículo seleccionado no está disponible en las fechas solicitadas. Por favor, elige otro.");
                }

                $vehiculo_to_update = $vehiculo_asignado_id; // Guardamos el ID para actualizar
                
                // Si la solicitud ya tenía un vehículo asignado y se va a cambiar, liberar el anterior (opcional, si manejan ese flujo)
                // Por ahora, solo asignamos el nuevo.

            } else { // Si la acción es 'rechazar'
                // Si se rechaza, aseguramos que no se asigne ningún vehículo
                $vehiculo_to_update = null;
            }

            // 1. Actualizar el estado de la solicitud y asignar vehículo
            $stmt = $db->prepare("UPDATE solicitudes_vehiculos SET estatus_solicitud = :new_status, fecha_aprobacion = NOW(), aprobado_por = :aprobado_por, observaciones_aprobacion = :observaciones, vehiculo_id = :vehiculo_id WHERE id = :solicitud_id");
            $stmt->bindParam(':new_status', $new_status);
            $stmt->bindParam(':aprobado_por', $user_id);
            $stmt->bindParam(':observaciones', $observaciones);
            $stmt->bindParam(':vehiculo_id', $vehiculo_to_update); // Aquí se asigna el ID o NULL
            $stmt->bindParam(':solicitud_id', $solicitud_id);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $success_message = 'Solicitud ' . ($action === 'aprobar' ? 'aprobada' : 'rechazada') . ' con éxito.';

                // Si la solicitud fue aprobada, el vehículo se considera "en uso" por esta aprobación en el calendario
                // Si la solicitud se rechaza, no se actualiza el estatus del vehículo en la tabla de vehículos
                // El estatus del vehículo en la tabla 'vehiculos' solo se cambiará a 'en_uso'
                // cuando se registre en historial_uso_vehiculos (la entrega real).
                // Por ahora, la tabla 'vehiculos' solo tiene el 'estatus' general.
                // Podríamos considerar un campo `proxima_reserva_id` en `vehiculos` para bloquearlo.

            } else {
                $error_message = 'La solicitud no pudo ser actualizada. Asegúrate de que no esté ya procesada o de que el ID sea correcto.';
            }

            $db->commit(); // Confirmar los cambios

        } catch (Exception $e) {
            $db->rollBack(); // Revertir si algo falla
            error_log("Error al gestionar solicitud: " . $e->getMessage());
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
                s.proposito,
                s.destino,
                s.estatus_solicitud,
                s.observaciones_aprobacion,
                v.marca,
                v.modelo,
                v.placas,
                v.id AS vehiculo_actual_id -- Para precargar en el modal
            FROM solicitudes_vehiculos s
            JOIN usuarios u ON s.usuario_id = u.id
            LEFT JOIN vehiculos v ON s.vehiculo_id = v.id
            ORDER BY s.fecha_creacion DESC
        ");
        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Obtener vehículos disponibles para el dropdown en el modal ---
        // Solo vehículos con estatus 'disponible' o 'en_uso' si son vehículos que ya están siendo usados por otras solicitudes aprobadas
        // Lo más simple por ahora es listar los disponibles para nuevas asignaciones
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
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Flotilla Interna</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="solicitar_vehiculo.php">Solicitar Vehículo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mis_solicitudes.php">Mis Solicitudes</a>
                    </li>
                    <?php if ($rol_usuario === 'flotilla_manager' || $rol_usuario === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="gestion_vehiculos.php">Gestión de Vehículos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="gestion_solicitudes.php">Gestión de Solicitudes</a>
                        </li>
                    <?php endif; ?>
                    <?php if ($rol_usuario === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="gestion_usuarios.php">Gestión de Usuarios</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Hola, <?php echo htmlspecialchars($nombre_usuario); ?>
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
                            <th>Propósito</th>
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
                                <td><?php echo htmlspecialchars($solicitud['proposito']); ?></td>
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
                                            case 'pendiente': $status_class = 'badge bg-warning text-dark'; break;
                                            case 'aprobada': $status_class = 'badge bg-success'; break;
                                            case 'rechazada': $status_class = 'badge bg-danger'; break;
                                            case 'en_curso': $status_class = 'badge bg-primary'; break;
                                            case 'completada': $status_class = 'badge bg-secondary'; break;
                                            case 'cancelada': $status_class = 'badge bg-info'; break;
                                        }
                                    ?>
                                    <span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($solicitud['estatus_solicitud'])); ?></span>
                                </td>
                                <td>
                                    <?php if ($solicitud['estatus_solicitud'] === 'pendiente'): ?>
                                        <button type="button" class="btn btn-success btn-sm me-1" data-bs-toggle="modal" data-bs-target="#approveRejectModal"
                                                data-solicitud-id="<?php echo $solicitud['solicitud_id']; ?>" data-action="aprobar"
                                                data-usuario="<?php echo htmlspecialchars($solicitud['usuario_nombre']); ?>"
                                                data-salida="<?php echo htmlspecialchars($solicitud['fecha_salida_solicitada']); ?>"
                                                data-regreso="<?php echo htmlspecialchars($solicitud['fecha_regreso_solicitada']); ?>"
                                                data-observaciones-aprobacion="<?php echo htmlspecialchars($solicitud['observaciones_aprobacion']); ?>"
                                                data-vehiculo-actual-id="<?php echo htmlspecialchars($solicitud['vehiculo_actual_id']); ?>">
                                            Aprobar
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#approveRejectModal"
                                                data-solicitud-id="<?php echo $solicitud['solicitud_id']; ?>" data-action="rechazar"
                                                data-usuario="<?php echo htmlspecialchars($solicitud['usuario_nombre']); ?>"
                                                data-observaciones-aprobacion="<?php echo htmlspecialchars($solicitud['observaciones_aprobacion']); ?>">
                                            Rechazar
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#viewDetailsModal"
                                            data-solicitud-id="<?php echo $solicitud['solicitud_id']; ?>"
                                            data-usuario="<?php echo htmlspecialchars($solicitud['usuario_nombre']); ?>"
                                            data-salida="<?php echo htmlspecialchars($solicitud['fecha_salida_solicitada']); ?>"
                                            data-regreso="<?php echo htmlspecialchars($solicitud['fecha_regreso_solicitada']); ?>"
                                            data-proposito="<?php echo htmlspecialchars($solicitud['proposito']); ?>"
                                            data-destino="<?php echo htmlspecialchars($solicitud['destino']); ?>"
                                            data-vehiculo="<?php echo htmlspecialchars($solicitud['marca'] ? $solicitud['marca'] . ' ' . $solicitud['modelo'] . ' (' . $solicitud['placas'] . ')' : 'Sin asignar'); ?>"
                                            data-estatus="<?php echo htmlspecialchars(ucfirst($solicitud['estatus_solicitud'])); ?>"
                                            data-observaciones-aprobacion="<?php echo htmlspecialchars($solicitud['observaciones_aprobacion']); ?>">
                                            Ver Detalles
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
                        <p><strong>Regreso Deseada:</strong> <span id="detailFechaRegreso"></span></p>
                        <p><strong>Propósito:</strong> <span id="detailProposito"></span></p>
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
            approveRejectModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; // Botón que activó el modal
                var solicitudId = button.getAttribute('data-solicitud-id');
                var action = button.getAttribute('data-action');
                var userName = button.getAttribute('data-usuario');
                var salida = button.getAttribute('data-salida');
                var regreso = button.getAttribute('data-regreso');
                var observacionesAprobacion = button.getAttribute('data-observaciones-aprobacion');
                var vehiculoActualId = button.getAttribute('data-vehiculo-actual-id'); // ID del vehículo ya asignado

                var modalSolicitudId = approveRejectModal.querySelector('#modalSolicitudId');
                var modalAction = approveRejectModal.querySelector('#modalAction');
                var modalActionText = approveRejectModal.querySelector('#modalActionText');
                var modalUserName = approveRejectModal.querySelector('#modalUserName');
                var modalSubmitBtn = approveRejectModal.querySelector('#modalSubmitBtn');
                var vehiculoAssignmentSection = approveRejectModal.querySelector('#vehicleAssignmentSection');
                var vehiculoAsignadoSelect = approveRejectModal.querySelector('#vehiculo_asignado_id');
                var observacionesModal = approveRejectModal.querySelector('#observaciones_aprobacion_modal');

                modalSolicitudId.value = solicitudId;
                modalAction.value = action;
                modalUserName.textContent = userName;
                observacionesModal.value = observacionesAprobacion; // Precarga observaciones

                // Resetear el select de vehículos y seleccionar el asignado si existe
                vehiculoAsignadoSelect.value = vehiculoActualId || ''; // Seleccionar el actual o vacío

                if (action === 'aprobar') {
                    modalActionText.textContent = 'APROBAR';
                    modalSubmitBtn.textContent = 'Aprobar Solicitud';
                    modalSubmitBtn.className = 'btn btn-success';
                    vehiculoAssignmentSection.style.display = 'block'; // Mostrar selector de vehículo
                    vehiculoAsignadoSelect.setAttribute('required', 'required'); // Hacerlo obligatorio
                } else if (action === 'rechazar') {
                    modalActionText.textContent = 'RECHAZAR';
                    modalSubmitBtn.textContent = 'Rechazar Solicitud';
                    modalSubmitBtn.className = 'btn btn-danger';
                    vehiculoAssignmentSection.style.display = 'none'; // Ocultar selector de vehículo
                    vehiculoAsignadoSelect.removeAttribute('required'); // No es obligatorio
                    vehiculoAsignadoSelect.value = ''; // Limpiar selección si se rechaza
                }
            });

            // JavaScript para manejar el modal de Ver Detalles
            var viewDetailsModal = document.getElementById('viewDetailsModal');
            viewDetailsModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; // Botón que activó el modal

                document.getElementById('detailUserName').textContent = button.getAttribute('data-usuario');
                document.getElementById('detailFechaSalida').textContent = new Date(button.getAttribute('data-salida')).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
                document.getElementById('detailFechaRegreso').textContent = new Date(button.getAttribute('data-regreso')).toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
                document.getElementById('detailProposito').textContent = button.getAttribute('data-proposito');
                document.getElementById('detailDestino').textContent = button.getAttribute('data-destino');
                document.getElementById('detailVehiculoAsignado').textContent = button.getAttribute('data-vehiculo');
                
                var statusBadge = document.getElementById('detailEstatus');
                statusBadge.textContent = button.getAttribute('data-estatus');
                statusBadge.className = 'badge'; // Resetear clases
                switch (button.getAttribute('data-estatus').toLowerCase()) {
                    case 'pendiente': statusBadge.classList.add('bg-warning', 'text-dark'); break;
                    case 'aprobada': statusBadge.classList.add('bg-success'); break;
                    case 'rechazada': statusBadge.classList.add('bg-danger'); break;
                    case 'en_curso': statusBadge.classList.add('bg-primary'); break;
                    case 'completada': statusBadge.classList.add('bg-secondary'); break;
                    case 'cancelada': statusBadge.classList.add('bg-info'); break;
                }

                document.getElementById('detailObservacionesAprobacion').textContent = button.getAttribute('data-observaciones-aprobacion') || 'N/A';
            });
        });
    </script>
</body>
</html>