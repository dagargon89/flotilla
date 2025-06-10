<?php
// public/solicitar_vehiculo.php - CÓDIGO COMPLETO Y CORREGIDO
session_start();
require_once '../app/config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$nombre_usuario = $_SESSION['user_name'];
$rol_usuario = $_SESSION['user_role']; // Necesario para la barra de navegación

$success_message = '';
$error_message = '';

// --- INICIALIZAR VARIABLES DEL FORMULARIO ---
// Esto previene los "Undefined variable" Warnings cuando la página se carga por primera vez
$fecha_salida_solicitada = '';
$fecha_regreso_solicitada = '';
$proposito = '';
$destino = '';
// --- FIN DE INICIALIZACIÓN ---


// Lógica para procesar la solicitud cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_salida_solicitada = trim($_POST['fecha_salida_solicitada'] ?? '');
    $fecha_regreso_solicitada = trim($_POST['fecha_regreso_solicitada'] ?? '');
    $proposito = trim($_POST['proposito'] ?? '');
    $destino = trim($_POST['destino'] ?? '');

    // Validación básica de los campos
    if (empty($fecha_salida_solicitada) || empty($fecha_regreso_solicitada) || empty($proposito) || empty($destino)) {
        $error_message = 'Por favor, completa todos los campos requeridos.';
    } elseif (strtotime($fecha_salida_solicitada) >= strtotime($fecha_regreso_solicitada)) {
        $error_message = 'La fecha y hora de regreso deben ser posteriores a la fecha y hora de salida.';
    } else {
        // Conectar a la base de datos
        $db = connectDB();
        if ($db) {
            try {
                // Preparamos la consulta para insertar la solicitud
                // El vehículo_id se deja NULL porque aún no se asigna.
                // El estatus_solicitud por defecto es 'pendiente'.
                $stmt = $db->prepare("INSERT INTO solicitudes_vehiculos (usuario_id, fecha_salida_solicitada, fecha_regreso_solicitada, proposito, destino) VALUES (:usuario_id, :fecha_salida, :fecha_regreso, :proposito, :destino)");

                // Bindeamos los parámetros
                $stmt->bindParam(':usuario_id', $user_id);
                $stmt->bindParam(':fecha_salida', $fecha_salida_solicitada);
                $stmt->bindParam(':fecha_regreso', $fecha_regreso_solicitada);
                $stmt->bindParam(':proposito', $proposito);
                $stmt->bindParam(':destino', $destino);

                // Ejecutamos la consulta
                $stmt->execute();

                $success_message = '¡Tu solicitud ha sido enviada con éxito! Espera la aprobación.';

                // Limpiar los campos del formulario después de una solicitud exitosa
                $fecha_salida_solicitada = '';
                $fecha_regreso_solicitada = '';
                $proposito = '';
                $destino = '';

            } catch (PDOException $e) {
                error_log("Error al enviar solicitud de vehículo: " . $e->getMessage());
                $error_message = 'Ocurrió un error al procesar tu solicitud. Intenta de nuevo.';
            }
        } else {
            $error_message = 'No se pudo conectar a la base de datos.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Vehículo - Flotilla Interna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
                        <a class="nav-link active" aria-current="page" href="solicitar_vehiculo.php">Solicitar Vehículo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mis_solicitudes.php">Mis Solicitudes</a>
                    </li>
                    <?php if ($rol_usuario === 'flotilla_manager' || $rol_usuario === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="gestion_vehiculos.php">Gestión de Vehículos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="gestion_solicitudes.php">Gestión de Solicitudes</a>
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
        <h1 class="mb-4">Solicitar un Vehículo</h1>

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

        <div class="card p-4 shadow-sm">
            <form action="solicitar_vehiculo.php" method="POST">
                <div class="mb-3">
                    <label for="fecha_salida_solicitada" class="form-label">Fecha y Hora de Salida Deseada</label>
                    <input type="datetime-local" class="form-control" id="fecha_salida_solicitada" name="fecha_salida_solicitada" value="<?php echo htmlspecialchars($fecha_salida_solicitada); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="fecha_regreso_solicitada" class="form-label">Fecha y Hora de Regreso Deseada</label>
                    <input type="datetime-local" class="form-control" id="fecha_regreso_solicitada" name="fecha_regreso_solicitada" value="<?php echo htmlspecialchars($fecha_regreso_solicitada); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="proposito" class="form-label">Propósito del Viaje</label>
                    <textarea class="form-control" id="proposito" name="proposito" rows="3" required><?php echo htmlspecialchars($proposito); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="destino" class="form-label">Destino / Ruta</label>
                    <input type="text" class="form-control" id="destino" name="destino" value="<?php echo htmlspecialchars($destino); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Enviar Solicitud</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Inicializa Flatpickr para los campos de fecha y hora
        flatpickr("#fecha_salida_solicitada", {
            enableTime: true,
            dateFormat: "Y-m-dTH:i", // Formato compatible con input type="datetime-local"
            minDate: "today", // No permitir fechas pasadas
            defaultDate: new Date() // Establece la fecha y hora actual por defecto
        });
        flatpickr("#fecha_regreso_solicitada", {
            enableTime: true,
            dateFormat: "Y-m-dTH:i",
            minDate: "today",
            defaultDate: new Date().fp_incr(1) // Por defecto, un día después de la fecha actual
        });
    </script>
    <script src="js/main.js"></script>
</body>
</html>