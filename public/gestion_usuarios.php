<?php
// public/gestion_usuarios.php
session_start();
require_once '../app/config/database.php';

// **VERIFICACIÓN DE ROL:**
// Solo 'admin' puede acceder a esta página.
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php'); // Redirige al dashboard si no es admin
    exit();
}

$nombre_usuario_sesion = $_SESSION['user_name'];
$rol_usuario_sesion = $_SESSION['user_role'];

$success_message = '';
$error_message = '';
$db = connectDB();
$usuarios = []; // Para guardar la lista de usuarios

// --- Lógica para procesar el formulario (Agregar/Editar/Eliminar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ''; // 'add', 'edit', 'delete'

    try {
        if ($action === 'add') {
            $nombre = trim($_POST['nombre'] ?? '');
            $correo_electronico = trim($_POST['correo_electronico'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol = $_POST['rol'] ?? 'empleado'; // Default a 'empleado' si no se especifica

            if (empty($nombre) || empty($correo_electronico) || empty($password)) {
                throw new Exception("Por favor, completa todos los campos obligatorios para agregar un usuario.");
            }

            // Hashear la contraseña antes de guardarla
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("INSERT INTO usuarios (nombre, correo_electronico, password, rol) VALUES (:nombre, :correo_electronico, :password, :rol)");
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':correo_electronico', $correo_electronico);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':rol', $rol);
            $stmt->execute();
            $success_message = 'Usuario agregado con éxito.';

        } elseif ($action === 'edit') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $nombre = trim($_POST['nombre'] ?? '');
            $correo_electronico = trim($_POST['correo_electronico'] ?? '');
            $rol = $_POST['rol'] ?? 'empleado';
            $new_password = $_POST['new_password'] ?? ''; // Para cambiar la contraseña

            if ($id === false || empty($nombre) || empty($correo_electronico) || empty($rol)) {
                throw new Exception("Por favor, completa todos los campos obligatorios para editar el usuario.");
            }

            $sql = "UPDATE usuarios SET nombre = :nombre, correo_electronico = :correo_electronico, rol = :rol";
            if (!empty($new_password)) {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql .= ", password = :password";
            }
            $sql .= " WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':correo_electronico', $correo_electronico);
            $stmt->bindParam(':rol', $rol);
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

            // **PRECAUCIÓN:** No permitir que un admin se elimine a sí mismo
            if ($id == $_SESSION['user_id']) {
                throw new Exception("No puedes eliminar tu propia cuenta de administrador.");
            }

            $stmt = $db->prepare("DELETE FROM usuarios WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $success_message = 'Usuario eliminado con éxito.';
        }

    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
        // Para errores de integridad (correo duplicado)
        if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'correo_electronico') !== false) {
             $error_message = 'Error: El correo electrónico ya está registrado. Por favor, usa otro.';
        }
        error_log("Error en gestión de usuarios: " . $e->getMessage());
    }
}

// --- Obtener todos los usuarios para mostrar en la tabla ---
if ($db) {
    try {
        $stmt = $db->query("SELECT id, nombre, correo_electronico, rol, fecha_creacion, ultima_sesion FROM usuarios ORDER BY nombre ASC");
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
                    <?php if ($rol_usuario_sesion === 'flotilla_manager' || $rol_usuario_sesion === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="gestion_vehiculos.php">Gestión de Vehículos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="gestion_solicitudes.php">Gestión de Solicitudes</a>
                        </li>
                    <?php endif; ?>
                    <?php if ($rol_usuario_sesion === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="gestion_usuarios.php">Gestión de Usuarios</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Hola, <?php echo htmlspecialchars($nombre_usuario_sesion); ?>
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
            <i class="bi bi-plus-circle"></i> Agregar Nuevo Usuario
        </button>

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
                                            case 'admin': $rol_class = 'badge bg-danger'; break;
                                            case 'flotilla_manager': $rol_class = 'badge bg-warning text-dark'; break;
                                            case 'empleado': $rol_class = 'badge bg-primary'; break;
                                        }
                                    ?>
                                    <span class="<?php echo $rol_class; ?>"><?php echo htmlspecialchars(ucfirst($usuario['rol'])); ?></span>
                                </td>
                                <td><?php echo $usuario['ultima_sesion'] ? date('d/m/Y H:i', strtotime($usuario['ultima_sesion'])) : 'Nunca'; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info text-white me-1" data-bs-toggle="modal" data-bs-target="#addEditUserModal" data-action="edit"
                                        data-id="<?php echo $usuario['id']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>"
                                        data-correo="<?php echo htmlspecialchars($usuario['correo_electronico']); ?>"
                                        data-rol="<?php echo htmlspecialchars($usuario['rol']); ?>">
                                        Editar
                                    </button>
                                    <?php if ($usuario['id'] !== $_SESSION['user_id']): // No permitir eliminar al propio admin logueado ?>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal" data-id="<?php echo $usuario['id']; ?>" data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>">
                                            Eliminar
                                        </button>
                                    <?php endif; ?>
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
                                    <?php if ($_SESSION['user_role'] === 'admin'): // Solo un admin puede crear o asignar rol 'admin' ?>
                                        <option value="admin">Administrador</option>
                                    <?php endif; ?>
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

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // JavaScript para manejar los modales de agregar/editar usuario
        document.addEventListener('DOMContentLoaded', function() {
            var addEditUserModal = document.getElementById('addEditUserModal');
            addEditUserModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; // Botón que activó el modal
                var action = button.getAttribute('data-action'); // 'add' o 'edit'

                var modalTitle = addEditUserModal.querySelector('#addEditUserModalLabel');
                var modalActionInput = addEditUserModal.querySelector('#modalActionUser');
                var userIdInput = addEditUserModal.querySelector('#userId');
                var submitBtn = addEditUserModal.querySelector('#submitUserBtn');
                var passwordField = addEditUserModal.querySelector('#passwordField');
                var passwordInput = addEditUserModal.querySelector('#password');
                var passwordHelp = addEditUserModal.querySelector('#passwordHelp');
                var form = addEditUserModal.querySelector('form');

                // Resetear el formulario y ocultar/mostrar campos de edición
                form.reset();
                passwordField.style.display = 'block'; // Mostrar por defecto para "add"
                passwordInput.setAttribute('required', 'required'); // Hacerlo requerido por defecto
                passwordInput.name = 'password'; // Asegurar el nombre del campo para 'add'

                if (action === 'add') {
                    modalTitle.textContent = 'Agregar Nuevo Usuario';
                    modalActionInput.value = 'add';
                    submitBtn.textContent = 'Guardar Usuario';
                    submitBtn.className = 'btn btn-primary';
                    userIdInput.value = ''; // Asegurarse de que el ID esté vacío para agregar
                    passwordHelp.textContent = '';
                } else if (action === 'edit') {
                    modalTitle.textContent = 'Editar Usuario';
                    modalActionInput.value = 'edit';
                    submitBtn.textContent = 'Actualizar Usuario';
                    submitBtn.className = 'btn btn-info text-white'; // Estilo para el botón de editar

                    // Ocultar campo de contraseña, o cambiar a "nueva contraseña" opcional
                    passwordInput.removeAttribute('required'); // Ya no es obligatorio al editar
                    passwordInput.name = 'new_password'; // Cambiar nombre para no sobrescribir la contraseña si está vacía
                    passwordHelp.textContent = 'Deja este campo vacío para mantener la contraseña actual.';

                    // Llenar el formulario con los datos del usuario
                    userIdInput.value = button.getAttribute('data-id');
                    addEditUserModal.querySelector('#nombre').value = button.getAttribute('data-nombre');
                    addEditUserModal.querySelector('#correo_electronico').value = button.getAttribute('data-correo');
                    addEditUserModal.querySelector('#rol').value = button.getAttribute('data-rol');
                }
            });

            // JavaScript para manejar el modal de eliminar usuario
            var deleteUserModal = document.getElementById('deleteUserModal');
            deleteUserModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; // Botón que activó el modal
                var userId = button.getAttribute('data-id');
                var userName = button.getAttribute('data-nombre');

                var modalUserId = deleteUserModal.querySelector('#deleteUserId');
                var modalUserName = deleteUserModal.querySelector('#deleteUserName');

                modalUserId.value = userId;
                modalUserName.textContent = userName;
            });
        });
    </script>
</body>
</html>