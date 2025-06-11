<?php
// public/api/get_vehiculo_availability.php - CÓDIGO ACTUALIZADO CON MÁS DETALLES
header('Content-Type: application/json');

require_once '../../app/config/database.php';

$vehiculo_id = filter_var($_GET['vehiculo_id'] ?? null, FILTER_VALIDATE_INT);
$occupied_ranges = [];

if (!$vehiculo_id) {
    echo json_encode(['error' => 'ID de vehículo no proporcionado o inválido.']);
    exit();
}

$db = connectDB();

if ($db) {
    try {
        // Consulta para obtener las solicitudes aprobadas o en curso para este vehículo
        // Ahora también selecciona el propósito y el nombre del usuario
        $stmt = $db->prepare("
            SELECT
                s.fecha_salida_solicitada,
                s.fecha_regreso_solicitada,
                s.proposito,
                u.nombre AS solicitante_nombre
            FROM solicitudes_vehiculos s
            JOIN usuarios u ON s.usuario_id = u.id
            WHERE s.vehiculo_id = :vehiculo_id
            AND s.estatus_solicitud IN ('aprobada', 'en_curso')
            ORDER BY s.fecha_salida_solicitada ASC
        ");
        $stmt->bindParam(':vehiculo_id', $vehiculo_id);
        $stmt->execute();
        $occupied_ranges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error en API de disponibilidad: " . $e->getMessage());
        echo json_encode(['error' => 'Error al cargar la disponibilidad: ' . $e->getMessage()]);
        exit();
    }
} else {
    echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
    exit();
}

echo json_encode(['occupied_ranges' => $occupied_ranges]);
?>