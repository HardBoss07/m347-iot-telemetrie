<?php
require_once __DIR__ . '/../config/db.php';

function sendTelemetryViaApi(string $apiUrl, string $token, array $metrics): bool
{
    $ch = curl_init($apiUrl);
    $payload = json_encode(['metrics' => $metrics]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $errorMsg = curl_error($ch);
        $msg = sprintf("[%s] [cURL Fehler] %s (Ziel: %s)\n", date('Y-m-d H:i:s'), $errorMsg, $apiUrl);
        if (php_sapi_name() === 'cli') {
            echo $msg;
        } else {
            error_log($msg);
        }
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        $msg = sprintf("[%s] [API Fehler] HTTP %d: %s\n", date('Y-m-d H:i:s'), $httpCode, $response);
        if (php_sapi_name() === 'cli') {
            echo $msg;
        } else {
            error_log($msg);
        }
        return false;
    }

    return true;
}

function generateMockData(int $rounds = 1): int
{
    $pdo = getDBConnection();
    $devices = $pdo->query("SELECT id, device_name, api_token, threshold_config, is_paused FROM devices WHERE is_paused = 0")->fetchAll();

    if (empty($devices)) {
        return 0;
    }

    // Resolve API target URL
    $apiUrl = getenv('API_URL');
    if (!$apiUrl) {
        if (isset($_SERVER['HTTP_HOST'])) {
            // Inside the Apache web container, listen directly on internal port 80
            $apiUrl = 'http://127.0.0.1/api/log.php';
        } else {
            $apiUrl = 'http://web/api/log.php';
        }
    }

    $inserted = 0;

    $latestStmt = $pdo->prepare("
        SELECT metrics FROM telemetry_data 
        WHERE device_id = :device_id 
        ORDER BY recorded_at DESC, id DESC LIMIT 1
    ");

    for ($r = 0; $r < $rounds; $r++) {
        foreach ($devices as $device) {
            if (!empty($device['is_paused'])) {
                continue;
            }

            $thresholdConfig = json_decode($device['threshold_config'] ?? '{}', true);
            if (empty($thresholdConfig)) {
                continue;
            }

            $latestStmt->execute([':device_id' => $device['id']]);
            $lastRow = $latestStmt->fetch();
            $lastMetrics = $lastRow ? json_decode($lastRow['metrics'], true) : [];

            $newMetrics = [];

            foreach ($thresholdConfig as $metricKey => $cfg) {
                $targetMid = (($cfg['min_ok'] ?? 20.0) + ($cfg['max_ok'] ?? 25.0)) / 2.0;
                $lastVal = $lastMetrics[$metricKey] ?? $targetMid;

                $drift = (rand(-10, 10) / 10.0);
                $pull = ($targetMid - $lastVal) * 0.05;
                $newVal = round($lastVal + $drift + $pull, 2);

                $newMetrics[$metricKey] = $newVal;
            }

            $success = sendTelemetryViaApi($apiUrl, $device['api_token'], $newMetrics);
            if ($success) {
                $inserted++;
            }
        }
    }

    return $inserted;
}
