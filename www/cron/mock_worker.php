<?php
require_once __DIR__ . '/../config/db.php';

function generateMockData(int $rounds = 1): int
{
    $pdo = getDBConnection();
    $devices = $pdo->query("SELECT id, device_name, threshold_config FROM devices")->fetchAll();

    if (empty($devices)) {
        return 0;
    }

    $inserted = 0;
    $insertStmt = $pdo->prepare("
        INSERT INTO telemetry_data (device_id, metrics, status) 
        VALUES (:device_id, :metrics, :status)
    ");

    $latestStmt = $pdo->prepare("
        SELECT metrics FROM telemetry_data 
        WHERE device_id = :device_id 
        ORDER BY recorded_at DESC, id DESC LIMIT 1
    ");

    for ($r = 0; $r < $rounds; $r++) {
        foreach ($devices as $device) {
            $thresholdConfig = json_decode($device['threshold_config'] ?? '{}', true);
            if (empty($thresholdConfig)) {
                continue;
            }

            $latestStmt->execute([':device_id' => $device['id']]);
            $lastRow = $latestStmt->fetch();
            $lastMetrics = $lastRow ? json_decode($lastRow['metrics'], true) : [];

            $newMetrics = [];
            $evaluatedStatus = 'OK';

            foreach ($thresholdConfig as $metricKey => $cfg) {
                $targetMid = (($cfg['min_ok'] ?? 20.0) + ($cfg['max_ok'] ?? 25.0)) / 2.0;
                $lastVal = $lastMetrics[$metricKey] ?? $targetMid;

                // Random walk drift with slight gravitational pull back to center target
                $drift = (rand(-10, 10) / 10.0);
                $pull = ($targetMid - $lastVal) * 0.05;
                $newVal = round($lastVal + $drift + $pull, 2);

                $newMetrics[$metricKey] = $newVal;

                // Status check
                if ((isset($cfg['min_warn']) && $newVal < $cfg['min_warn']) || (isset($cfg['max_warn']) && $newVal > $cfg['max_warn'])) {
                    $evaluatedStatus = 'KRITISCH';
                } elseif ($evaluatedStatus !== 'KRITISCH' && ((isset($cfg['min_ok']) && $newVal < $cfg['min_ok']) || (isset($cfg['max_ok']) && $newVal > $cfg['max_ok']))) {
                    $evaluatedStatus = 'WARNUNG';
                }
            }

            $insertStmt->execute([
                ':device_id' => $device['id'],
                ':metrics' => json_encode($newMetrics, JSON_UNESCAPED_UNICODE),
                ':status' => $evaluatedStatus
            ]);
            $inserted++;
        }
    }

    return $inserted;
}
