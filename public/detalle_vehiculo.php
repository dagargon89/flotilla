<?php
// public/detalle_vehiculo.php
session_start();
require_once '../app/config/database.php';

// **VERIFICACIÓN DE ROL:**
// Solo 'flotilla_manager' y 'admin' pueden acceder a esta página.
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'flotilla_manager' && $_SESSION['user_role'] !== 'admin')) {
    header('Location: dashboard.php'); // Redirige al dashboard si no tiene permisos
    exit();
}

$nombre_usuario_sesion = $_SESSION['user_name'];
$rol_usuario_sesion = $_SESSION['user_role'];
$nombre_usuario_sesion = $_SESSION['user_name'] ?? 'Usuario';
$rol_usuario_sesion = $_SESSION['user_role'] ?? 'empleado';

$error_message = '';
$vehiculo_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

$db = connectDB();
$vehiculo = null;
$solicitudes_historicas = [];
$mantenimientos_historicos = [];
$documentos_vehiculo = []; // Para los documentos, si los implementamos más adelante

if (!$vehiculo_id) {
    $error_message = 'ID de vehículo no proporcionado o inválido.';
} else {
    if ($db) {
        try {
            // 1. Obtener datos generales del vehículo
            $stmt_vehiculo = $db->prepare("SELECT * FROM vehiculos WHERE id = :vehiculo_id");
            $stmt_vehiculo->bindParam(':vehiculo_id', $vehiculo_id);
            $stmt_vehiculo->execute();
            $vehiculo = $stmt_vehiculo->fetch(PDO::FETCH_ASSOC);

            if (!$vehiculo) {
                $error_message = 'Vehículo no encontrado.';
            } else {
                // 2. Obtener historial de solicitudes y uso para este vehículo
                $stmt_solicitudes = $db->prepare("
                    SELECT
                        s.id AS solicitud_id,
                        u.nombre AS usuario_nombre,
                        s.fecha_salida_solicitada,
                        s.fecha_regreso_solicitada,
                        s.proposito,
                        s.destino,
                        s.estatus_solicitud,
                        s.observaciones_aprobacion,
                        hu.kilometraje_salida,
                        hu.nivel_combustible_salida,
                        hu.fecha_salida_real,
                        hu.observaciones_salida,
                        hu.fotos_salida_url,
                        hu.kilometraje_regreso,
                        hu.nivel_combustible_regreso,
                        hu.fecha_regreso_real,
                        hu.observaciones_regreso,
                        hu.fotos_regreso_url
                    FROM solicitudes_vehiculos s
                    JOIN usuarios u ON s.usuario_id = u.id
                    LEFT JOIN historial_uso_vehiculos hu ON s.id = hu.solicitud_id
                    WHERE s.vehiculo_id = :vehiculo_id
                    ORDER BY s.fecha_salida_solicitada DESC
                ");
                $stmt_solicitudes->bindParam(':vehiculo_id', $vehiculo_id);
                $stmt_solicitudes->execute();
                $solicitudes_historicas = $stmt_solicitudes->fetchAll(PDO::FETCH_ASSOC);

                // 3. Obtener historial de mantenimientos para este vehículo
                $stmt_mantenimientos = $db->prepare("SELECT * FROM mantenimientos WHERE vehiculo_id = :vehiculo_id ORDER BY fecha_mantenimiento DESC");
                $stmt_mantenimientos->bindParam(':vehiculo_id', $vehiculo_id);
                $stmt_mantenimientos->execute();
                $mantenimientos_historicos = $stmt_mantenimientos->fetchAll(PDO::FETCH_ASSOC);

                // 4. Obtener documentos del vehículo (si ya tuvieras la lógica para subirlos)
                // $stmt_documentos = $db->prepare("SELECT * FROM documentos_vehiculos WHERE vehiculo_id = :vehiculo_id ORDER BY fecha_subida DESC");
                // $stmt_documentos->bindParam(':vehiculo_id', $vehiculo_id);
                // $stmt_documentos->execute();
                // $documentos_vehiculo = $stmt_documentos->fetchAll(PDO::FETCH_ASSOC);

            }
        } catch (PDOException $e) {
            error_log("Error al cargar detalle de vehículo: " . $e->getMessage());
            $error_message = 'Ocurrió un error al cargar los detalles del vehículo: ' . $e->getMessage();
        }
    } else {
        $error_message = 'No se pudo conectar a la base de datos.';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Vehículo - Flotilla Interna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php require_once '../app/includes/navbar.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
            <a href="gestion_vehiculos.php" class="btn btn-secondary mt-3">Regresar a Gestión de Vehículos</a>
        <?php elseif (!$vehiculo): ?>
             <div class="alert alert-info" role="alert">
                Vehículo no encontrado o no válido.
            </div>
            <a href="gestion_vehiculos.php" class="btn btn-secondary mt-3">Regresar a Gestión de Vehículos</a>
        <?php else: ?>
            <h1 class="mb-4">Detalle de Vehículo: <?php echo htmlspecialchars($vehiculo['marca'] . ' ' . $vehiculo['modelo'] . ' (' . $vehiculo['placas'] . ')'); ?></h1>

            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    Información General
                    <a href="gestion_vehiculos.php?action=edit&id=<?php echo $vehiculo['id']; ?>" class="btn btn-sm btn-outline-info float-end me-2" data-bs-toggle="modal" data-bs-target="#addEditVehicleModal"
                        data-id="<?php echo $vehiculo['id']; ?>"
                        data-marca="<?php echo htmlspecialchars($vehiculo['marca']); ?>"
                        data-modelo="<?php echo htmlspecialchars($vehiculo['modelo']); ?>"
                        data-anio="<?php echo htmlspecialchars($vehiculo['anio']); ?>"
                        data-placas="<?php echo htmlspecialchars($vehiculo['placas']); ?>"
                        data-vin="<?php echo htmlspecialchars($vehiculo['vin']); ?>"
                        data-tipo-combustible="<?php echo htmlspecialchars($vehiculo['tipo_combustible']); ?>"
                        data-kilometraje-actual="<?php echo htmlspecialchars($vehiculo['kilometraje_actual']); ?>"
                        data-estatus="<?php echo htmlspecialchars($vehiculo['estatus']); ?>"
                        data-ubicacion-actual="<?php echo htmlspecialchars($vehiculo['ubicacion_actual']); ?>"
                        data-observaciones="<?php echo htmlspecialchars($vehiculo['observaciones']); ?>">
                        Editar Vehículo
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6"><p><strong>Marca:</strong> <?php echo htmlspecialchars($vehiculo['marca']); ?></p></div>
                        <div class="col-md-6"><p><strong>Modelo:</strong> <?php echo htmlspecialchars($vehiculo['modelo']); ?></p></div>
                        <div class="col-md-6"><p><strong>Año:</strong> <?php echo htmlspecialchars($vehiculo['anio']); ?></p></div>
                        <div class="col-md-6"><p><strong>Placas:</strong> <?php echo htmlspecialchars($vehiculo['placas']); ?></p></div>
                        <div class="col-md-6"><p><strong>VIN:</strong> <?php echo htmlspecialchars($vehiculo['vin'] ?? 'N/A'); ?></p></div>
                        <div class="col-md-6"><p><strong>Tipo de Combustible:</strong> <?php echo htmlspecialchars($vehiculo['tipo_combustible']); ?></p></div>
                        <div class="col-md-6"><p><strong>Kilometraje Actual:</strong> <?php echo htmlspecialchars(number_format($vehiculo['kilometraje_actual'])); ?> KM</p></div>
                        <div class="col-md-6"><p><strong>Estatus:</strong>
                            <?php
                                $status_class = '';
                                switch ($vehiculo['estatus']) {
                                    case 'disponible': $status_class = 'badge bg-success'; break;
                                    case 'en_uso': $status_class = 'badge bg-primary'; break;
                                    case 'en_mantenimiento': $status_class = 'badge bg-warning text-dark'; break;
                                    case 'inactivo': $status_class = 'badge bg-danger'; break;
                                }
                            ?>
                            <span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($vehiculo['estatus'])); ?></span>
                        </p></div>
                        <div class="col-md-6"><p><strong>Ubicación Actual:</strong> <?php echo htmlspecialchars($vehiculo['ubicacion_actual'] ?? 'N/A'); ?></p></div>
                        <div class="col-12"><p><strong>Observaciones:</strong> <?php echo htmlspecialchars($vehiculo['observaciones'] ?? 'Ninguna.'); ?></p></div>
                        <div class="col-12"><p><strong>Fecha de Registro:</strong> <?php echo date('d/m/Y H:i', strtotime($vehiculo['fecha_registro'])); ?></p></div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    Historial de Solicitudes y Uso
                </div>
                <div class="card-body">
                    <?php if (empty($solicitudes_historicas)): ?>
                        <div class="alert alert-info text-center" role="alert">
                            Este vehículo no tiene solicitudes o historial de uso registrado.
                        </div>
                    <?php else: ?>
                        <div class="accordion" id="accordionSolicitudes">
                            <?php foreach ($solicitudes_historicas as $index => $solicitud): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading<?php echo $solicitud['solicitud_id']; ?>">
                                        <button class="accordion-button <?php echo ($index !== 0) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $solicitud['solicitud_id']; ?>" aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $solicitud['solicitud_id']; ?>">
                                            Solicitud #<?php echo htmlspecialchars($solicitud['solicitud_id']); ?> - <?php echo htmlspecialchars($solicitud['proposito']); ?> (<?php echo htmlspecialchars($solicitud['usuario_nombre']); ?>)
                                            <span class="badge bg-<?php
                                                switch ($solicitud['estatus_solicitud']) {
                                                    case 'pendiente': echo 'warning text-dark'; break;
                                                    case 'aprobada': echo 'success'; break;
                                                    case 'rechazada': echo 'danger'; break;
                                                    case 'en_curso': echo 'primary'; break;
                                                    case 'completada': echo 'secondary'; break;
                                                    case 'cancelada': echo 'info'; break;
                                                }
                                            ?> ms-3"><?php echo htmlspecialchars(ucfirst($solicitud['estatus_solicitud'])); ?></span>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $solicitud['solicitud_id']; ?>" class="accordion-collapse collapse <?php echo ($index === 0) ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $solicitud['solicitud_id']; ?>" data-bs-parent="#accordionSolicitudes">
                                        <div class="accordion-body">
                                            <p><strong>Solicitante:</strong> <?php echo htmlspecialchars($solicitud['usuario_nombre']); ?></p>
                                            <p><strong>Fechas Solicitadas:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_salida_solicitada'])); ?> a <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_regreso_solicitada'])); ?></p>
                                            <p><strong>Destino:</strong> <?php echo htmlspecialchars($solicitud['destino']); ?></p>
                                            <p><strong>Observaciones del Gestor:</strong> <?php echo htmlspecialchars($solicitud['observaciones_aprobacion'] ?? 'Ninguna.'); ?></p>

                                            <?php if (!empty($solicitud['fecha_salida_real'])): ?>
                                                <h6>Registro de Salida:</h6>
                                                <p><strong>Fecha/Hora Salida Real:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_salida_real'])); ?></p>
                                                <p><strong>KM Salida:</strong> <?php echo htmlspecialchars(number_format($solicitud['kilometraje_salida'])); ?></p>
                                                <p><strong>Nivel Combustible Salida:</strong> <?php echo htmlspecialchars($solicitud['nivel_combustible_salida']); ?>%</p>
                                                <p><strong>Obs. Salida:</strong> <?php echo htmlspecialchars($solicitud['observaciones_salida'] ?? 'Ninguna.'); ?></p>
                                                <?php
                                                    $fotos_salida_urls = json_decode($solicitud['fotos_salida_url'] ?? '[]', true);
                                                    if (!empty($fotos_salida_urls)):
                                                ?>
                                                    <div class="row mb-3">
                                                        <p><strong>Fotos de Salida:</strong></p>
                                                        <?php foreach ($fotos_salida_urls as $url): ?>
                                                            <div class="col-4 col-md-3 mb-2">
                                                                <a href="<?php echo htmlspecialchars($url); ?>" target="_blank">
                                                                    <img src="<?php echo htmlspecialchars($url); ?>" class="img-fluid rounded shadow-sm" alt="Foto Salida">
                                                                </a>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if (!empty($solicitud['fecha_regreso_real'])): ?>
                                                <h6>Registro de Regreso:</h6>
                                                <p><strong>Fecha/Hora Regreso Real:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_regreso_real'])); ?></p>
                                                <p><strong>KM Regreso:</strong> <?php echo htmlspecialchars(number_format($solicitud['kilometraje_regreso'])); ?></p>
                                                <p><strong>Nivel Combustible Regreso:</strong> <?php echo htmlspecialchars($solicitud['nivel_combustible_regreso']); ?>%</p>
                                                <p><strong>Obs. Regreso:</strong> <?php echo htmlspecialchars($solicitud['observaciones_regreso'] ?? 'Ninguna.'); ?></p>
                                                <?php
                                                    $fotos_regreso_urls = json_decode($solicitud['fotos_regreso_url'] ?? '[]', true);
                                                    if (!empty($fotos_regreso_urls)):
                                                ?>
                                                    <div class="row mb-3">
                                                        <p><strong>Fotos de Regreso:</strong></p>
                                                        <?php foreach ($fotos_regreso_urls as $url): ?>
                                                            <div class="col-4 col-md-3 mb-2">
                                                                <a href="<?php echo htmlspecialchars($url); ?>" target="_blank">
                                                                    <img src="<?php echo htmlspecialchars($url); ?>" class="img-fluid rounded shadow-sm" alt="Foto Regreso">
                                                                </a>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    Historial de Mantenimientos
                </div>
                <div class="card-body">
                    <?php if (empty($mantenimientos_historicos)): ?>
                        <div class="alert alert-info text-center" role="alert">
                            Este vehículo no tiene mantenimientos registrados.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Fecha</th>
                                        <th>KM</th>
                                        <th>Costo</th>
                                        <th>Taller</th>
                                        <th>Observaciones</th>
                                        <th>Próx. KM</th>
                                        <th>Próx. Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mantenimientos_historicos as $mantenimiento): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($mantenimiento['tipo_mantenimiento']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($mantenimiento['fecha_mantenimiento'])); ?></td>
                                            <td><?php echo htmlspecialchars(number_format($mantenimiento['kilometraje_mantenimiento'])); ?></td>
                                            <td><?php echo $mantenimiento['costo'] !== null ? '$' . number_format($mantenimiento['costo'], 2) : 'N/A'; ?></td>
                                            <td><?php echo htmlspecialchars($mantenimiento['taller'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($mantenimiento['observaciones'] ?? 'N/A'); ?></td>
                                            <td><?php echo $mantenimiento['proximo_mantenimiento_km'] !== null ? htmlspecialchars(number_format($mantenimiento['proximo_mantenimiento_km'])) : 'N/A'; ?></td>
                                            <td><?php echo $mantenimiento['proximo_mantenimiento_fecha'] !== null ? date('d/m/Y', strtotime($mantenimiento['proximo_mantenimiento_fecha'])) : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4 shadow-sm">
                <div class="card-header">
                    Documentos del Vehículo
                </div>
                <div class="card-body">
                    <div class="alert alert-info text-center" role="alert">
                        La gestión y visualización de documentos se puede implementar en un paso posterior.
                        </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Asegúrate de incluir el modal de Editar Vehículo (addEditVehicleModal) de gestion_vehiculos.php
        // o un JavaScript que maneje este modal si lo abres desde aquí.
        // Para que el botón "Editar Vehículo" en esta página funcione, necesitarás que el modal
        // y el JavaScript que lo controla (del archivo gestion_vehiculos.php) estén disponibles.
        // Una forma simple es copiar y pegar el modal y su script JS asociado en esta página.
        // O, si estás refactorizando, tener esos modales en un archivo JS común.

        // Ejemplo: Si copias el script JS del modal de gestion_vehiculos.php aquí:
        document.addEventListener('DOMContentLoaded', function() {
            var addEditVehicleModal = document.getElementById('addEditVehicleModal');
            if (addEditVehicleModal) { // Asegúrate de que el modal existe en esta página
                addEditVehicleModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget; // Botón que activó el modal
                    var action = button.getAttribute('data-action'); // 'add' o 'edit' (aquí será 'edit')

                    var modalTitle = addEditVehicleModal.querySelector('#addEditVehicleModalLabel');
                    var modalActionInput = addEditVehicleModal.querySelector('#modalActionVehicle');
                    var vehicleIdInput = addEditVehicleModal.querySelector('#vehicleId');
                    var submitBtn = addEditVehicleModal.querySelector('#submitVehicleBtn');
                    var estatusField = addEditVehicleModal.querySelector('#estatusField');
                    var form = addEditVehicleModal.querySelector('form');

                    // Resetear el formulario y ocultar campos de edición
                    form.reset();
                    estatusField.style.display = 'none'; // No se muestra en el modal de detalle

                    if (action === 'edit') {
                        modalTitle.textContent = 'Editar Vehículo';
                        modalActionInput.value = 'edit';
                        submitBtn.textContent = 'Actualizar Vehículo';
                        submitBtn.className = 'btn btn-info text-white';
                        estatusField.style.display = 'block'; // Mostrar el campo de estatus en edición

                        // Llenar el formulario con los datos del vehículo
                        vehicleIdInput.value = button.getAttribute('data-id');
                        addEditVehicleModal.querySelector('#marca').value = button.getAttribute('data-marca');
                        addEditVehicleModal.querySelector('#modelo').value = button.getAttribute('data-modelo');
                        addEditVehicleModal.querySelector('#anio').value = button.getAttribute('data-anio');
                        addEditVehicleModal.querySelector('#placas').value = button.getAttribute('data-placas');
                        addEditVehicleModal.querySelector('#vin').value = button.getAttribute('data-vin') === 'null' ? '' : button.getAttribute('data-vin');
                        addEditVehicleModal.querySelector('#tipo_combustible').value = button.getAttribute('data-tipo-combustible');
                        addEditVehicleModal.querySelector('#kilometraje_actual').value = button.getAttribute('data-kilometraje-actual');
                        addEditVehicleModal.querySelector('#estatus').value = button.getAttribute('data-estatus');
                        addEditVehicleModal.querySelector('#ubicacion_actual').value = button.getAttribute('data-ubicacion-actual') === 'null' ? '' : button.getAttribute('data-ubicacion-actual');
                        addEditVehicleModal.querySelector('#observaciones_vehiculo').value = button.getAttribute('data-observaciones') === 'null' ? '' : button.getAttribute('data-observaciones');
                    }
                });
            }
        });
    </script>
</body>
</html>