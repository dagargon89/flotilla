<?php
// public/index.php
session_start(); // Inicia la sesión PHP al principio de cada página que la use

// Incluye el archivo de conexión a la base de datos
require_once '../app/config/database.php';

// Opcional: Aquí podrías incluir un archivo de controlador para manejar el login
// require_once '../app/controllers/AuthController.php';

// Si el usuario ya está logueado, redirigirlo al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php'); // Redirige al dashboard
    exit();
}

$error_message = ''; // Variable para guardar mensajes de error

// Lógica para procesar el formulario de login (por ahora simplificada)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo_electronico'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($correo) || empty($password)) {
        $error_message = 'Por favor, ingresa tu correo y contraseña.';
    } else {
        // **Aquí iría la lógica para verificar en la base de datos**
        // Por ahora, solo un ejemplo simple. Esto lo haremos más robusto después.

        $db = connectDB(); // Conecta a la base de datos
        if ($db) {
            try {
                // Consulta para buscar al usuario por correo
                $stmt = $db->prepare("SELECT id, nombre, correo_electronico, password, rol FROM usuarios WHERE correo_electronico = :correo");
                $stmt->bindParam(':correo', $correo);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Verifica la contraseña (¡OJO! Esto debe ser con password_verify si la guardas hasheada)
                    // Por ahora, lo dejamos simple, pero lo mejoraremos con hash
                    if (password_verify($password, $user['password'])) { // Usar password_verify es lo correcto
                        // Contraseña correcta, inicia la sesión
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['nombre'];
                        $_SESSION['user_role'] = $user['rol'];

                        // Actualiza la última sesión del usuario en la BD
                        $update_stmt = $db->prepare("UPDATE usuarios SET ultima_sesion = NOW() WHERE id = :id");
                        $update_stmt->bindParam(':id', $user['id']);
                        $update_stmt->execute();

                        header('Location: dashboard.php'); // Redirige al dashboard
                        exit();
                    } else {
                        $error_message = 'Correo o contraseña incorrectos.';
                    }
                } else {
                    $error_message = 'Correo o contraseña incorrectos.';
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
    <title>Flotilla Interna - Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css"> </head>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script> </body>
</html>