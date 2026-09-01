<?php
header('Content-Type: application/json');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Nur POST-Anfragen erlaubt']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['device_id'], $input['temperature'], $input['humidity'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Unvollständige Parameter']);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        INSERT INTO telemetry_data (device_id, temperature, humidity, status) 
        VALUES (:device_id, :temperature, :humidity, :status)
    ");

    $status = $input['status'] ?? 'OK';

    $stmt->execute([
        ':device_id' => (int) $input['device_id'],
        ':temperature' => (float) $input['temperature'],
        ':humidity' => (float) $input['humidity'],
        ':status' => $status
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Telemetriedaten gespeichert']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Fehler beim Speichern: ' . $e->getMessage()]);
}