<?php
// app/config/database.php

// Define las constantes para la conexión a la base de datos
define('DB_HOST', 'localhost'); // El host de tu base de datos (normalmente 'localhost' en XAMPP)
define('DB_USER', 'david');     // Tu usuario de MySQL (por defecto 'root' en XAMPP)
define('DB_PASS', 'Comunica25!');         // Tu contraseña de MySQL (por defecto vacía en XAMPP)
define('DB_NAME', 'flotilla_interna'); // El nombre de la base de datos que creamos

/**
 * Función para establecer la conexión a la base de datos.
 * @return PDO|null Objeto PDO si la conexión es exitosa, null si falla.
 */
function connectDB() {
    try {
        // Crea una nueva instancia de PDO (PHP Data Objects)
        // Esto es más seguro y moderno que mysqli_connect()
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en caso de error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve los resultados como arrays asociativos
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Deshabilita la emulación de prepared statements (más seguro)
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Si hay un error en la conexión, lo mostramos
        error_log("Error de conexión a la base de datos: " . $e->getMessage());
        // Podrías mostrar un mensaje amigable al usuario o redirigirlo a una página de error.
        die("Lo sentimos, no podemos conectar con la base de datos en este momento. Inténtalo más tarde.");
    }
}

// Opcional: Para probar la conexión una vez que tengas este archivo
// $db = connectDB();
// if ($db) {
//     echo "¡Conexión a la base de datos exitosa!";
// }
?>