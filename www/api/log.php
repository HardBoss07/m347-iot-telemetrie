<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Nur POST-Anfragen erlaubt']);
    exit;
}

// 1. Bearer Token Extraction (mit Fallback für Apache HTTP Header Stripping)
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

if (empty($authHeader) && function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $name => $value) {
        if (strtolower($name) === 'authorization') {
            $authHeader = $value;
            break;
        }
    }
}

if (!preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['error' => 'Fehlender oder ungültiger Authorization Bearer Header']);
    exit;
}
$token = $matches[1];

$pdo = getDBConnection();

// 2. Device Lookup by Token & Pause Verification
$stmt = $pdo->prepare("SELECT id, device_name, threshold_config, is_paused FROM devices WHERE api_token = :token");
$stmt->execute([':token' => $token]);
$device = $stmt->fetch();

if (!$device) {
    http_response_code(401);
    echo json_encode(['error' => 'Ungültiges API-Token']);
    exit;
}

if (!empty($device['is_paused'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Gerät ist pausiert und nimmt keine Telemetriedaten entgegen']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['metrics']) || !is_array($input['metrics'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültiges Payload-Format. "metrics" Objekt erforderlich']);
    exit;
}

$metrics = $input['metrics'];
$thresholdConfig = json_decode($device['threshold_config'] ?? '{}', true);

// 3. Dynamic Threshold Evaluation Engine
$status = 'OK';
foreach ($metrics as $metricKey => $value) {
    if (isset($thresholdConfig[$metricKey]) && is_numeric($value)) {
        $cfg = $thresholdConfig[$metricKey];
        $val = (float) $value;

        // Check Critical Boundary
        if ((isset($cfg['min_warn']) && $val < $cfg['min_warn']) || (isset($cfg['max_warn']) && $val > $cfg['max_warn'])) {
            $status = 'KRITISCH';
            break;
        }
        // Check Warning Boundary
        if ((isset($cfg['min_ok']) && $val < $cfg['min_ok']) || (isset($cfg['max_ok']) && $val > $cfg['max_ok'])) {
            $status = 'WARNUNG';
        }
    }
}

// 4. Persistence
try {
    $insertStmt = $pdo->prepare("
        INSERT INTO telemetry_data (device_id, metrics, status) 
        VALUES (:device_id, :metrics, :status)
    ");
    $insertStmt->execute([
        ':device_id' => $device['id'],
        ':metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
        ':status' => $status
    ]);

    echo json_encode([
        'status' => 'success',
        'device' => $device['device_name'],
        'evaluated_status' => $status,
        'metrics_received' => $metrics
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Fehler beim Speichern: ' . $e->getMessage()]);
}
