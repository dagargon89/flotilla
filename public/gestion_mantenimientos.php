<?php
// public/gestion_mantenimientos.php
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

$success_message = '';
$error_message = '';
$db = connectDB();
$mantenimientos = []; // Para guardar la lista de mantenimientos
$vehiculos_flotilla = []; // Para el dropdown de vehículos en los modales

// --- Lógica para procesar el formulario (Agregar/Editar/Eliminar Mantenimiento) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ''; // 'add', 'edit', 'delete'

    try {
        if ($action === 'add') {
            $vehiculo_id = filter_var($_POST['vehiculo_id'] ?? '', FILTER_VALIDATE_INT);
            $tipo_mantenimiento = trim($_POST['tipo_mantenimiento'] ?? '');
            $fecha_mantenimiento = trim($_POST['fecha_mantenimiento'] ?? '');
            $kilometraje_mantenimiento = filter_var($_POST['kilometraje_mantenimiento'] ?? '', FILTER_VALIDATE_INT);
            $costo = filter_var($_POST['costo'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE); // Permite null
            $taller = trim($_POST['taller'] ?? '');
            $observaciones = trim($_POST['observaciones'] ?? '');
            $proximo_mantenimiento_km = filter_var($_POST['proximo_mantenimiento_km'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            $proximo_mantenimiento_fecha = trim($_POST['proximo_mantenimiento_fecha'] ?? '');

            if ($vehiculo_id === false || empty($tipo_mantenimiento) || empty($fecha_mantenimiento) || $kilometraje_mantenimiento === false) {
                throw new Exception("Por favor, completa los campos obligatorios para agregar el mantenimiento (vehículo, tipo, fecha, kilometraje).");
            }

            // Asegurar que las fechas vacías se guarden como NULL
            $proximo_mantenimiento_fecha = empty($proximo_mantenimiento_fecha) ? NULL : $proximo_mantenimiento_fecha;

            $db->beginTransaction(); // Inicia la transacción

            $stmt = $db->prepare("INSERT INTO mantenimientos (vehiculo_id, tipo_mantenimiento, fecha_mantenimiento, kilometraje_mantenimiento, costo, taller, observaciones, proximo_mantenimiento_km, proximo_mantenimiento_fecha) VALUES (:vehiculo_id, :tipo_mantenimiento, :fecha_mantenimiento, :kilometraje_mantenimiento, :costo, :taller, :observaciones, :proximo_mantenimiento_km, :proximo_mantenimiento_fecha)");
            $stmt->bindParam(':vehiculo_id', $vehiculo_id);
            $stmt->bindParam(':tipo_mantenimiento', $tipo_mantenimiento);
            $stmt->bindParam(':fecha_mantenimiento', $fecha_mantenimiento);
            $stmt->bindParam(':kilometraje_mantenimiento', $kilometraje_mantenimiento);
            $stmt->bindParam(':costo', $costo);
            $stmt->bindParam(':taller', $taller);
            $stmt->bindParam(':observaciones', $observaciones);
            $stmt->bindParam(':proximo_mantenimiento_km', $proximo_mantenimiento_km);
            $stmt->bindParam(':proximo_mantenimiento_fecha', $proximo_mantenimiento_fecha);
            $stmt->execute();

            // Opcional: Actualizar el kilometraje actual del vehículo si este mantenimiento es el más reciente
            // Podrías hacer una consulta para ver si este es el KM más alto registrado para el vehículo
            $stmt_update_veh_km = $db->prepare("UPDATE vehiculos SET kilometraje_actual = GREATEST(kilometraje_actual, :new_km) WHERE id = :vehiculo_id");
            $stmt_update_veh_km->bindParam(':new_km', $kilometraje_mantenimiento);
            $stmt_update_veh_km->bindParam(':vehiculo_id', $vehiculo_id);
            $stmt_update_veh_km->execute();

            $db->commit(); // Confirma la transacción
            $success_message = 'Mantenimiento registrado con éxito.';

        } elseif ($action === 'edit') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $vehiculo_id = filter_var($_POST['vehiculo_id'] ?? '', FILTER_VALIDATE_INT);
            $tipo_mantenimiento = trim($_POST['tipo_mantenimiento'] ?? '');
            $fecha_mantenimiento = trim($_POST['fecha_mantenimiento'] ?? '');
            $kilometraje_mantenimiento = filter_var($_POST['kilometraje_mantenimiento'] ?? '', FILTER_VALIDATE_INT);
            $costo = filter_var($_POST['costo'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            $taller = trim($_POST['taller'] ?? '');
            $observaciones = trim($_POST['observaciones'] ?? '');
            $proximo_mantenimiento_km = filter_var($_POST['proximo_mantenimiento_km'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            $proximo_mantenimiento_fecha = trim($_POST['proximo_mantenimiento_fecha'] ?? '');

            if ($id === false || $vehiculo_id === false || empty($tipo_mantenimiento) || empty($fecha_mantenimiento) || $kilometraje_mantenimiento === false) {
                throw new Exception("Por favor, completa los campos obligatorios para editar el mantenimiento.");
            }

            $proximo_mantenimiento_fecha = empty($proximo_mantenimiento_fecha) ? NULL : $proximo_mantenimiento_fecha;

            $db->beginTransaction(); // Inicia la transacción

            $stmt = $db->prepare("UPDATE mantenimientos SET vehiculo_id = :vehiculo_id, tipo_mantenimiento = :tipo_mantenimiento, fecha_mantenimiento = :fecha_mantenimiento, kilometraje_mantenimiento = :kilometraje_mantenimiento, costo = :costo, taller = :taller, observaciones = :observaciones, proximo_mantenimiento_km = :proximo_mantenimiento_km, proximo_mantenimiento_fecha = :proximo_mantenimiento_fecha WHERE id = :id");
            $stmt->bindParam(':vehiculo_id', $vehiculo_id);
            $stmt->bindParam(':tipo_mantenimiento', $tipo_mantenimiento);
            $stmt->bindParam(':fecha_mantenimiento', $fecha_mantenimiento);
            $stmt->bindParam(':kilometraje_mantenimiento', $kilometraje_mantenimiento);
            $stmt->bindParam(':costo', $costo);
            $stmt->bindParam(':taller', $taller);
            $stmt->bindParam(':observaciones', $observaciones);
            $stmt->bindParam(':proximo_mantenimiento_km', $proximo_mantenimiento_km);
            $stmt->bindParam(':proximo_mantenimiento_fecha', $proximo_mantenimiento_fecha);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            // Opcional: Actualizar el kilometraje actual del vehículo si este mantenimiento es el más reciente
            $stmt_update_veh_km = $db->prepare("UPDATE vehiculos SET kilometraje_actual = GREATEST(kilometraje_actual, :new_km) WHERE id = :vehiculo_id");
            $stmt_update_veh_km->bindParam(':new_km', $kilometraje_mantenimiento);
            $stmt_update_veh_km->bindParam(':vehiculo_id', $vehiculo_id);
            $stmt_update_veh_km->execute();

            $db->commit(); // Confirma la transacción
            $success_message = 'Mantenimiento actualizado con éxito.';

        } elseif ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            if ($id === false) {
                throw new Exception("ID de mantenimiento inválido para eliminar.");
            }

            $db->beginTransaction(); // Inicia la transacción
            $stmt = $db->prepare("DELETE FROM mantenimientos WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $db->commit(); // Confirma la transacción
            $success_message = 'Mantenimiento eliminado con éxito.';
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) { // Si hay una transacción abierta, la revierte
            $db->rollBack();
        }
        $error_message = 'Error: ' . $e->getMessage();
        error_log("Error en gestión de mantenimientos: " . $e->getMessage());
    }
}

// --- Obtener todos los mantenimientos para mostrar en la tabla ---
if ($db) {
    try {
        $stmt_mantenimientos = $db->query("
            SELECT m.*, v.marca, v.modelo, v.placas
            FROM mantenimientos m
            JOIN vehiculos v ON m.vehiculo_id = v.id
            ORDER BY m.fecha_mantenimiento DESC
        ");
        $mantenimientos = $stmt_mantenimientos->fetchAll(PDO::FETCH_ASSOC);

        // Obtener todos los vehículos para el dropdown en los modales
        $stmt_vehiculos_flotilla = $db->query("SELECT id, marca, modelo, placas FROM vehiculos ORDER BY marca, modelo");
        $vehiculos_flotilla = $stmt_vehiculos_flotilla->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error al cargar mantenimientos o vehículos para el formulario: " . $e->getMessage());
        $error_message = 'No se pudieron cargar los datos.';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mantenimientos - Flotilla Interna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"> </head>
<body>
<?php require_once '../app/includes/navbar.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4">Gestión de Mantenimientos de Vehículos</h1>

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

        <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addEditMaintenanceModal" data-action="add">
            <i class="bi bi-tools"></i> Registrar Nuevo Mantenimiento
        </button>

        <?php if (empty($mantenimientos)): ?>
            <div class="alert alert-info" role="alert">
                No hay mantenimientos registrados.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vehículo</th>
                            <th>Tipo de Mantenimiento</th>
                            <th>Fecha</th>
                            <th>KM</th>
                            <th>Costo</th>
                            <th>Taller</th>
                            <th>Próx. KM</th>
                            <th>Próx. Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mantenimientos as $mantenimiento): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($mantenimiento['id']); ?></td>
                                <td><?php echo htmlspecialchars($mantenimiento['marca'] . ' ' . $mantenimiento['modelo'] . ' (' . $mantenimiento['placas'] . ')'); ?></td>
                                <td><?php echo htmlspecialchars($mantenimiento['tipo_mantenimiento']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($mantenimiento['fecha_mantenimiento'])); ?></td>
                                <td><?php echo htmlspecialchars(number_format($mantenimiento['kilometraje_mantenimiento'])); ?></td>
                                <td><?php echo $mantenimiento['costo'] !== null ? '$' . number_format($mantenimiento['costo'], 2) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($mantenimiento['taller'] ?? 'N/A'); ?></td>
                                <td><?php echo $mantenimiento['proximo_mantenimiento_km'] !== null ? htmlspecialchars(number_format($mantenimiento['proximo_mantenimiento_km'])) . ' KM' : 'N/A'; ?></td>
                                <td><?php echo $mantenimiento['proximo_mantenimiento_fecha'] !== null ? date('d/m/Y', strtotime($mantenimiento['proximo_mantenimiento_fecha'])) : 'N/A'; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info text-white me-1" data-bs-toggle="modal" data-bs-target="#addEditMaintenanceModal" data-action="edit"
                                        data-id="<?php echo htmlspecialchars($mantenimiento['id']); ?>"
                                        data-vehiculo-id="<?php echo htmlspecialchars($mantenimiento['vehiculo_id']); ?>"
                                        data-tipo-mantenimiento="<?php echo htmlspecialchars($mantenimiento['tipo_mantenimiento']); ?>"
                                        data-fecha-mantenimiento="<?php echo date('Y-m-d\TH:i', strtotime($mantenimiento['fecha_mantenimiento'])); ?>"
                                        data-kilometraje-mantenimiento="<?php echo htmlspecialchars($mantenimiento['kilometraje_mantenimiento']); ?>"
                                        data-costo="<?php echo htmlspecialchars($mantenimiento['costo'] ?? ''); ?>"
                                        data-taller="<?php echo htmlspecialchars($mantenimiento['taller'] ?? ''); ?>"
                                        data-observaciones="<?php echo htmlspecialchars($mantenimiento['observaciones'] ?? ''); ?>"
                                        data-proximo-mantenimiento-km="<?php echo htmlspecialchars($mantenimiento['proximo_mantenimiento_km'] ?? ''); ?>"
                                        data-proximo-mantenimiento-fecha="<?php echo htmlspecialchars($mantenimiento['proximo_mantenimiento_fecha'] ? date('Y-m-d', strtotime($mantenimiento['proximo_mantenimiento_fecha'])) : ''); ?>">
                                        Editar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteMaintenanceModal" data-id="<?php echo htmlspecialchars($mantenimiento['id']); ?>" data-tipo="<?php echo htmlspecialchars($mantenimiento['tipo_mantenimiento']); ?>" data-placas="<?php echo htmlspecialchars($mantenimiento['placas']); ?>">
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="modal fade" id="addEditMaintenanceModal" tabindex="-1" aria-labelledby="addEditMaintenanceModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addEditMaintenanceModalLabel"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="gestion_mantenimientos.php" method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" id="modalActionMaintenance">
                            <input type="hidden" name="id" id="maintenanceId">

                            <div class="mb-3">
                                <label for="vehiculo_id" class="form-label">Vehículo</label>
                                <select class="form-select" id="vehiculo_id" name="vehiculo_id" required>
                                    <option value="">Selecciona un vehículo...</option>
                                    <?php foreach ($vehiculos_flotilla as $vehiculo_opt): ?>
                                        <option value="<?php echo htmlspecialchars($vehiculo_opt['id']); ?>">
                                            <?php echo htmlspecialchars($vehiculo_opt['marca'] . ' ' . $vehiculo_opt['modelo'] . ' (' . $vehiculo_opt['placas'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="tipo_mantenimiento" class="form-label">Tipo de Mantenimiento</label>
                                <input type="text" class="form-control" id="tipo_mantenimiento" name="tipo_mantenimiento" required>
                            </div>
                            <div class="mb-3">
                                <label for="fecha_mantenimiento" class="form-label">Fecha y Hora del Mantenimiento</label>
                                <input type="datetime-local" class="form-control" id="fecha_mantenimiento" name="fecha_mantenimiento" required>
                            </div>
                            <div class="mb-3">
                                <label for="kilometraje_mantenimiento" class="form-label">Kilometraje del Vehículo</label>
                                <input type="number" class="form-control" id="kilometraje_mantenimiento" name="kilometraje_mantenimiento" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="costo" class="form-label">Costo ($)</label>
                                <input type="number" class="form-control" id="costo" name="costo" step="0.01" min="0">
                            </div>
                            <div class="mb-3">
                                <label for="taller" class="form-label">Taller / Proveedor</label>
                                <input type="text" class="form-control" id="taller" name="taller">
                            </div>
                            <div class="mb-3">
                                <label for="observaciones_mantenimiento" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones_mantenimiento" name="observaciones" rows="3"></textarea>
                            </div>
                            <hr>
                            <h6>Próximo Mantenimiento (Opcional)</h6>
                            <div class="mb-3">
                                <label for="proximo_mantenimiento_km" class="form-label">Próximo KM</label>
                                <input type="number" class="form-control" id="proximo_mantenimiento_km" name="proximo_mantenimiento_km" min="0">
                            </div>
                            <div class="mb-3">
                                <label for="proximo_mantenimiento_fecha" class="form-label">Próxima Fecha</label>
                                <input type="date" class="form-control" id="proximo_mantenimiento_fecha" name="proximo_mantenimiento_fecha">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="submitMaintenanceBtn"></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteMaintenanceModal" tabindex="-1" aria-labelledby="deleteMaintenanceModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteMaintenanceModalLabel">Confirmar Eliminación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="gestion_mantenimientos.php" method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteMaintenanceId">
                        <div class="modal-body">
                            ¿Estás seguro de que quieres eliminar el mantenimiento <strong id="deleteMaintenanceType"></strong> para el vehículo con placas <strong id="deleteMaintenancePlacas"></strong>?
                            Esta acción es irreversible.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Eliminar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script> <script src="js/main.js"></script>
    <script>
        // JavaScript para manejar los modales de agregar/editar mantenimiento
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar Flatpickr para los campos de fecha y hora
            flatpickr("#fecha_mantenimiento", {
                enableTime: true,
                dateFormat: "Y-m-dTH:i",
                defaultDate: new Date()
            });
            flatpickr("#proximo_mantenimiento_fecha", {
                dateFormat: "Y-m-d", // Solo fecha
                minDate: "today"
            });


            var addEditMaintenanceModal = document.getElementById('addEditMaintenanceModal');
            addEditMaintenanceModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; // Botón que activó el modal
                var action = button.getAttribute('data-action'); // 'add' o 'edit'

                var modalTitle = addEditMaintenanceModal.querySelector('#addEditMaintenanceModalLabel');
                var modalActionInput = addEditMaintenanceModal.querySelector('#modalActionMaintenance');
                var maintenanceIdInput = addEditMaintenanceModal.querySelector('#maintenanceId');
                var submitBtn = addEditMaintenanceModal.querySelector('#submitMaintenanceBtn');
                var form = addEditMaintenanceModal.querySelector('form');

                // Resetear el formulario
                form.reset();

                if (action === 'add') {
                    modalTitle.textContent = 'Registrar Nuevo Mantenimiento';
                    modalActionInput.value = 'add';
                    submitBtn.textContent = 'Guardar Mantenimiento';
                    submitBtn.className = 'btn btn-primary';
                    maintenanceIdInput.value = ''; // Asegurarse de que el ID esté vacío
                    // Resetear Flatpickr para "add"
                    flatpickr("#fecha_mantenimiento").setDate(new Date());
                    flatpickr("#proximo_mantenimiento_fecha").clear(); // Limpiar fecha si existe
                } else if (action === 'edit') {
                    modalTitle.textContent = 'Editar Mantenimiento';
                    modalActionInput.value = 'edit';
                    submitBtn.textContent = 'Actualizar Mantenimiento';
                    submitBtn.className = 'btn btn-info text-white';

                    // Llenar el formulario con los datos del mantenimiento
                    maintenanceIdInput.value = button.getAttribute('data-id');
                    addEditMaintenanceModal.querySelector('#vehiculo_id').value = button.getAttribute('data-vehiculo-id');
                    addEditMaintenanceModal.querySelector('#tipo_mantenimiento').value = button.getAttribute('data-tipo-mantenimiento');
                    addEditMaintenanceModal.querySelector('#fecha_mantenimiento').value = button.getAttribute('data-fecha-mantenimiento');
                    addEditMaintenanceModal.querySelector('#kilometraje_mantenimiento').value = button.getAttribute('data-kilometraje-mantenimiento');
                    addEditMaintenanceModal.querySelector('#costo').value = button.getAttribute('data-costo');
                    addEditMaintenanceModal.querySelector('#taller').value = button.getAttribute('data-taller');
                    addEditMaintenanceModal.querySelector('#observaciones_mantenimiento').value = button.getAttribute('data-observaciones');
                    addEditMaintenanceModal.querySelector('#proximo_mantenimiento_km').value = button.getAttribute('data-proximo-mantenimiento-km');
                    addEditMaintenanceModal.querySelector('#proximo_mantenimiento_fecha').value = button.getAttribute('data-proximo-mantenimiento-fecha');
                    // Asegurar que Flatpickr actualice sus valores al cargar el modal
                    flatpickr("#fecha_mantenimiento").setDate(button.getAttribute('data-fecha-mantenimiento'));
                    if (button.getAttribute('data-proximo-mantenimiento-fecha')) {
                         flatpickr("#proximo_mantenimiento_fecha").setDate(button.getAttribute('data-proximo-mantenimiento-fecha'));
                    } else {
                        flatpickr("#proximo_mantenimiento_fecha").clear();
                    }
                }
            });

            // JavaScript para manejar el modal de eliminar mantenimiento
            var deleteMaintenanceModal = document.getElementById('deleteMaintenanceModal');
            deleteMaintenanceModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; // Botón que activó el modal
                var maintenanceId = button.getAttribute('data-id');
                var maintenanceType = button.getAttribute('data-tipo');
                var maintenancePlacas = button.getAttribute('data-placas');

                var modalMaintenanceId = deleteMaintenanceModal.querySelector('#deleteMaintenanceId');
                var modalMaintenanceType = deleteMaintenanceModal.querySelector('#deleteMaintenanceType');
                var modalMaintenancePlacas = deleteMaintenanceModal.querySelector('#deleteMaintenancePlacas');

                modalMaintenanceId.value = maintenanceId;
                modalMaintenanceType.textContent = maintenanceType;
                modalMaintenancePlacas.textContent = maintenancePlacas;
            });
        });
    </script>
</body>
</html>