<?php
// public/index.php - CÓDIGO COMPLETO Y ACTUALIZADO (Login con estatus_cuenta)
session_start(); // Inicia la sesión PHP al principio de cada página que la use

// Incluye el archivo de conexión a la base de datos
require_once '../app/config/database.php';

// Si el usuario ya está logueado, redirigirlo al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php'); // Redirige al dashboard
    exit();
}

$error_message = ''; // Variable para guardar mensajes de error

// Lógica para procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo_electronico'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($correo) || empty($password)) {
        $error_message = 'Por favor, ingresa tu correo y contraseña.';
    } else {
        $db = connectDB(); // Conecta a la base de datos
        if ($db) {
            try {
                // Consulta para buscar al usuario por correo y OBTENER estatus_cuenta
                $stmt = $db->prepare("SELECT id, nombre, correo_electronico, password, rol, estatus_cuenta FROM usuarios WHERE correo_electronico = :correo");
                $stmt->bindParam(':correo', $correo);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Verifica la contraseña
                    if (password_verify($password, $user['password'])) {
                        // Contraseña correcta
                        // AHORA VERIFICAR ESTATUS DE LA CUENTA
                        if ($user['estatus_cuenta'] === 'activa') {
                            // Cuenta activa, iniciar sesión
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_name'] = $user['nombre'];
                            $_SESSION['user_role'] = $user['rol'];

                            // Actualiza la última sesión del usuario en la BD
                            $update_stmt = $db->prepare("UPDATE usuarios SET ultima_sesion = NOW() WHERE id = :id");
                            $update_stmt->bindParam(':id', $user['id']);
                            $update_stmt->execute();

                            header('Location: dashboard.php');
                            exit();
                        } elseif ($user['estatus_cuenta'] === 'pendiente_aprobacion') {
                            $error_message = 'Tu cuenta está pendiente de aprobación. Por favor, espera a que el administrador la active.';
                        } elseif ($user['estatus_cuenta'] === 'rechazada') {
                            $error_message = 'Tu solicitud de cuenta ha sido rechazada. Contacta al administrador si crees que es un error.';
                        } else { // 'inactiva' o cualquier otro estatus
                            $error_message = 'Tu cuenta está inactiva. Contacta al administrador.';
                        }
                    } else {
                        $error_message = 'Correo o contraseña incorrectos.';
                    }
                } else {
                    $error_message = 'Correo o contraseña incorrectos.'; // No diferenciar entre correo no existente y contraseña incorrecta por seguridad
                }
            } catch (PDOException $e) {
                error_log("Error de login: " . $e->getMessage());
                $error_message = 'Ocurrió un error al intentar iniciar sesión. Por favor, intenta de nuevo.';
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
    <title>Iniciar Sesión - Flotilla Interna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
    <div class="card shadow p-4" style="max-width: 400px; width: 100%;">
        <h2 class="card-title text-center mb-4">Iniciar Sesión</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="mb-3">
                <label for="correo_electronico" class="form-label">Correo Electrónico</label>
                <input type="email" class="form-control" id="correo_electronico" name="correo_electronico" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>

        <hr class="my-4">

        <div class="text-center">
            <button type="button" class="btn btn-outline-secondary w-100 mb-2" disabled>
                <img src="https://img.icons8.com/color/16/000000/google-logo.png" alt="Google icon" class="me-2">
                Iniciar Sesión con Google (próximamente)
            </button>
        </div>
        <p class="text-center mt-3">¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>

</html>