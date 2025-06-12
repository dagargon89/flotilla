<?php
// public/gestion_usuarios.php - CÓDIGO COMPLETO Y ACTUALIZADO (Estilos de botones y lógica de estatus)
session_start();
require_once '../app/config/database.php';

// **VERIFICACIÓN DE ROL:**
// Solo 'admin' puede acceder a esta página.
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php'); // Redirige al dashboard si no es admin
    exit();
}

$nombre_usuario_sesion = $_SESSION['user_name'] ?? 'Usuario';
$rol_usuario_sesion = $_SESSION['user_role'] ?? 'empleado';
$user_id_sesion = $_SESSION['user_id'];

$success_message = '';
$error_message = '';
$db = connectDB();
$usuarios = []; // Para guardar la lista de usuarios

// --- Lógica para procesar el formulario (Agregar/Editar/Eliminar/Aprobar/Rechazar Usuario) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ''; // 'add', 'edit', 'delete', 'approve_account', 'reject_account'

    try {
        if ($action === 'add') {
            $nombre = trim($_POST['nombre'] ?? '');
            $correo_electronico = trim($_POST['correo_electronico'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol = $_POST['rol'] ?? 'empleado';

            if (empty($nombre) || empty($correo_electronico) || empty($password)) {
                throw new Exception("Por favor, completa todos los campos obligatorios para agregar un usuario.");
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Nuevo usuario añadido por admin se activa directamente
            $stmt = $db->prepare("INSERT INTO usuarios (nombre, correo_electronico, password, rol, estatus_cuenta) VALUES (:nombre, :correo_electronico, :password, :rol, 'activa')");
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':correo_electronico', $correo_electronico);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':rol', $rol);
            $stmt->execute();
            $success_message = 'Usuario agregado con éxito (activado directamente).';
        } elseif ($action === 'edit') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $nombre = trim($_POST['nombre'] ?? '');
            $correo_electronico = trim($_POST['correo_electronico'] ?? '');
            $rol = $_POST['rol'] ?? 'empleado';
            $estatus_cuenta = $_POST['estatus_cuenta'] ?? 'pendiente_aprobacion'; // Nuevo
            $new_password = $_POST['new_password'] ?? '';

            if ($id === false || empty($nombre) || empty($correo_electronico) || empty($rol) || empty($estatus_cuenta)) {
                throw new Exception("Por favor, completa todos los campos obligatorios para editar el usuario.");
            }

            $sql = "UPDATE usuarios SET nombre = :nombre, correo_electronico = :correo_electronico, rol = :rol, estatus_cuenta = :estatus_cuenta";
            if (!empty($new_password)) {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql .= ", password = :password";
            }
            $sql .= " WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':correo_electronico', $correo_electronico);
            $stmt->bindParam(':rol', $rol);
            $stmt->bindParam(':estatus_cuenta', $estatus_cuenta); // Nuevo
            if (!empty($new_password)) {
                $stmt->bindParam(':password', $hashed_new_password);
            }
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            $success_message = 'Usuario actualizado con éxito.';
        } elseif ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

            if ($id === false) {
                throw new Exception("ID de usuario inválido para eliminar.");
            }

            if ($id == $_SESSION['user_id_sesion']) { // Usar user_id_sesion para comparar
                throw new Exception("No puedes eliminar tu propia cuenta de administrador.");
            }

            $stmt = $db->prepare("DELETE FROM usuarios WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $success_message = 'Usuario eliminado con éxito.';
        } elseif ($action === 'approve_account' || $action === 'reject_account') {
            $id = filter_var($_POST['user_id_action'] ?? '', FILTER_VALIDATE_INT);
            if ($id === false) {
                throw new Exception("ID de usuario inválido para aprobar/rechazar.");
            }

            if ($id == $_SESSION['user_id_sesion']) { // Usar user_id_sesion para comparar
                throw new Exception("No puedes aprobar/rechazar tu propia cuenta.");
            }

            $new_estatus = ($action === 'approve_account') ? 'activa' : 'rechazada';

            $stmt = $db->prepare("UPDATE usuarios SET estatus_cuenta = :estatus_cuenta WHERE id = :id AND estatus_cuenta = 'pendiente_aprobacion'");
            $stmt->bindParam(':estatus_cuenta', $new_estatus);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $success_message = 'Cuenta de usuario ' . ($action === 'approve_account' ? 'aprobada' : 'rechazada') . ' con éxito.';
            } else {
                $error_message = 'No se pudo actualizar el estatus de la cuenta. Puede que ya haya sido procesada.';
            }
        }
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
        if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'correo_electronico') !== false) {
            $error_message = 'Error: El correo electrónico ya está registrado. Por favor, usa otro.';
        }
        error_log("Error en gestión de usuarios: " . $e->getMessage());
    }
}

// --- Obtener todos los usuarios para mostrar en la tabla ---
if ($db) {
    try {
        // Añadir estatus_cuenta a la consulta
        $stmt = $db->query("SELECT id, nombre, correo_electronico, rol, estatus_cuenta, fecha_creacion, ultima_sesion FROM usuarios ORDER BY nombre ASC");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al cargar usuarios: " . $e->getMessage());
        $error_message = 'No se pudieron cargar los usuarios.';
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Flotilla Interna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php
    $nombre_usuario_sesion = $_SESSION['user_name'] ?? 'Usuario';
    $rol_usuario_sesion = $_SESSION['user_role'] ?? 'empleado';
    require_once '../app/includes/navbar.php';
    ?>

    <div class="container mt-4">
        <h1 class="mb-4">Gestión de Usuarios</h1>

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

        <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addEditUserModal" data-action="add">
            <i class="bi bi-plus-circle"></i> Agregar Nuevo Usuario (Admin)
        </button>
        <p class="text-muted small">Para solicitudes de cuenta, ve a la tabla de abajo y busca el estatus "Pendiente de Aprobación".</p>

        <?php if (empty($usuarios)): ?>
            <div class="alert alert-info" role="alert">
                No hay usuarios registrados en el sistema.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Correo Electrónico</th>
                            <th>Rol</th>
                            <th>Estatus Cuenta</th>
                            <th>Última Sesión</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['correo_electronico']); ?></td>
                                <td>
                                    <?php
                                    $rol_class = '';
                                    switch ($usuario['rol']) {
                                        case 'admin':
                                            $rol_class = 'badge bg-danger';
                                            break;
                                        case 'flotilla_manager':
                                            $rol_class = 'badge bg-warning text-dark';
                                            break;
                                        case 'empleado':
                                            $rol_class = 'badge bg-primary';
                                            break;
                                    }
                                    ?>
                                    <span class="<?php echo $rol_class; ?>"><?php echo htmlspecialchars(ucfirst($usuario['rol'])); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $estatus_cuenta_class = '';
                                    switch ($usuario['estatus_cuenta']) {
                                        case 'pendiente_aprobacion':
                                            $estatus_cuenta_class = 'badge bg-info';
                                            break;
                                        case 'activa':
                                            $estatus_cuenta_class = 'badge bg-success';
                                            break;
                                        case 'rechazada':
                                            $estatus_cuenta_class = 'badge bg-danger';
                                            break;
                                        case 'inactiva':
                                            $estatus_cuenta_class = 'badge bg-secondary';
                                            break;
                                    }
                                    ?>
                                    <span class="<?php echo $estatus_cuenta_class; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $usuario['estatus_cuenta']))); ?></span>
                                </td>
                                <td><?php echo $usuario['ultima_sesion'] ? date('d/m/Y H:i', strtotime($usuario['ultima_sesion'])) : 'Nunca'; ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1"> <?php if ($usuario['estatus_cuenta'] === 'pendiente_aprobacion'): ?>
                                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveRejectUserModal"
                                                data-user-id="<?php echo $usuario['id']; ?>" data-action="approve_account" data-user-name="<?php echo htmlspecialchars($usuario['nombre']); ?>">
                                                Aprobar
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#approveRejectUserModal"
                                                data-user-id="<?php echo $usuario['id']; ?>" data-action="reject_account" data-user-name="<?php echo htmlspecialchars($usuario['nombre']); ?>">
                                                Rechazar
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#addEditUserModal" data-action="edit"
                                            data-id="<?php echo $usuario['id']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>"
                                            data-correo="<?php echo htmlspecialchars($usuario['correo_electronico']); ?>"
                                            data-rol="<?php echo htmlspecialchars($usuario['rol']); ?>"
                                            data-estatus-cuenta="<?php echo htmlspecialchars($usuario['estatus_cuenta']); ?>">
                                            Editar
                                        </button>
                                        <?php if ($usuario['id'] !== $user_id_sesion): // Se usa $user_id_sesion, que es el ID del usuario logueado 
                                        ?>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal" data-id="<?php echo $usuario['id']; ?>" data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>">
                                                Eliminar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="modal fade" id="addEditUserModal" tabindex="-1" aria-labelledby="addEditUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addEditUserModalLabel"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="gestion_usuarios.php" method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" id="modalActionUser">
                            <input type="hidden" name="id" id="userId">

                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            <div class="mb-3">
                                <label for="correo_electronico" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="correo_electronico" name="correo_electronico" required>
                            </div>
                            <div class="mb-3" id="passwordField">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="form-text text-muted" id="passwordHelp"></small>
                            </div>
                            <div class="mb-3">
                                <label for="rol" class="form-label">Rol</label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="empleado">Empleado</option>
                                    <option value="flotilla_manager">Manager de Flotilla</option>
                                    <?php if ($rol_usuario_sesion === 'admin'): ?>
                                        <option value="admin">Administrador</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="mb-3" id="estatusCuentaField">
                                <label for="estatus_cuenta" class="form-label">Estatus de Cuenta</label>
                                <select class="form-select" id="estatus_cuenta" name="estatus_cuenta" required>
                                    <option value="pendiente_aprobacion">Pendiente de Aprobación</option>
                                    <option value="activa">Activa</option>
                                    <option value="rechazada">Rechazada</option>
                                    <option value="inactiva">Inactiva</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="submitUserBtn"></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteUserModalLabel">Confirmar Eliminación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="gestion_usuarios.php" method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteUserId">
                        <div class="modal-body">
                            ¿Estás seguro de que quieres eliminar al usuario <strong id="deleteUserName"></strong>?
                            Esta acción es irreversible y eliminará también sus solicitudes asociadas.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Eliminar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="approveRejectUserModal" tabindex="-1" aria-labelledby="approveRejectUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="approveRejectUserModalLabel">Gestionar Solicitud de Cuenta</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="gestion_usuarios.php" method="POST">
                        <input type="hidden" name="user_id_action" id="modalUserActionId">
                        <input type="hidden" name="action" id="modalUserActionType">
                        <div class="modal-body">
                            Estás a punto de <strong id="modalUserActionText"></strong> la solicitud de cuenta para <strong id="modalUserActionName"></strong>.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn" id="modalUserSubmitBtn"></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // JavaScript para manejar los modales de agregar/editar usuario
        document.addEventListener('DOMContentLoaded', function() {
            var addEditUserModal = document.getElementById('addEditUserModal');
            addEditUserModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var action = button.getAttribute('data-action');

                var modalTitle = addEditUserModal.querySelector('#addEditUserModalLabel');
                var modalActionInput = addEditUserModal.querySelector('#modalActionUser');
                var userIdInput = addEditUserModal.querySelector('#userId');
                var submitBtn = addEditUserModal.querySelector('#submitUserBtn');
                var passwordField = addEditUserModal.querySelector('#passwordField');
                var passwordInput = addEditUserModal.querySelector('#password');
                var passwordHelp = addEditUserModal.querySelector('#passwordHelp');
                var estatusCuentaField = addEditUserModal.querySelector('#estatusCuentaField');
                var estatusCuentaSelect = addEditUserModal.querySelector('#estatus_cuenta');
                var form = addEditUserModal.querySelector('form');

                form.reset();
                passwordField.style.display = 'block';
                passwordInput.setAttribute('required', 'required');
                passwordInput.name = 'password';
                estatusCuentaField.style.display = 'none';

                if (action === 'add') {
                    modalTitle.textContent = 'Agregar Nuevo Usuario';
                    modalActionInput.value = 'add';
                    submitBtn.textContent = 'Guardar Usuario';
                    submitBtn.className = 'btn btn-primary';
                    userIdInput.value = '';
                    passwordHelp.textContent = '';
                } else if (action === 'edit') {
                    modalTitle.textContent = 'Editar Usuario';
                    modalActionInput.value = 'edit';
                    submitBtn.textContent = 'Actualizar Usuario';
                    submitBtn.className = 'btn btn-info text-white';

                    passwordInput.removeAttribute('required');
                    passwordInput.name = 'new_password';
                    passwordHelp.textContent = 'Deja este campo vacío para mantener la contraseña actual.';
                    estatusCuentaField.style.display = 'block';

                    userIdInput.value = button.getAttribute('data-id');
                    addEditUserModal.querySelector('#nombre').value = button.getAttribute('data-nombre');
                    addEditUserModal.querySelector('#correo_electronico').value = button.getAttribute('data-correo');
                    addEditUserModal.querySelector('#rol').value = button.getAttribute('data-rol');
                    estatusCuentaSelect.value = button.getAttribute('data-estatus-cuenta');
                }
            });

            // JavaScript para manejar el modal de eliminar usuario
            var deleteUserModal = document.getElementById('deleteUserModal');
            deleteUserModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var userId = button.getAttribute('data-id');
                var userName = button.getAttribute('data-nombre');

                var modalUserId = deleteUserModal.querySelector('#deleteUserId');
                var modalUserName = deleteUserModal.querySelector('#deleteUserName');

                modalUserId.value = userId;
                modalUserName.textContent = userName;
            });

            // JavaScript para manejar el modal de Aprobar/Rechazar Usuario (Solicitud de Cuenta)
            var approveRejectUserModal = document.getElementById('approveRejectUserModal');
            approveRejectUserModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var userId = button.getAttribute('data-user-id');
                var action = button.getAttribute('data-action');
                var userName = button.getAttribute('data-user-name');

                var modalUserActionId = approveRejectUserModal.querySelector('#modalUserActionId');
                var modalUserActionType = approveRejectUserModal.querySelector('#modalUserActionType');
                var modalUserActionText = approveRejectUserModal.querySelector('#modalUserActionText');
                var modalUserActionName = approveRejectUserModal.querySelector('#modalUserActionName');
                var modalUserSubmitBtn = approveRejectUserModal.querySelector('#modalUserSubmitBtn');

                modalUserActionId.value = userId;
                modalUserActionType.value = action;
                modalUserActionName.textContent = userName;

                if (action === 'approve_account') {
                    modalUserActionText.textContent = 'APROBAR';
                    modalUserSubmitBtn.textContent = 'Aprobar Cuenta';
                    modalUserSubmitBtn.className = 'btn btn-success';
                } else if (action === 'reject_account') {
                    modalUserActionText.textContent = 'RECHAZAR';
                    modalUserSubmitBtn.textContent = 'Rechazar Cuenta';
                    modalUserSubmitBtn.className = 'btn btn-danger';
                }
            });
        });
    </script>
</body>

</html>