<?php
require_once __DIR__ . '/../config/db.php';

function generateMockData(int $rounds = 1): int
{
    $pdo = getDBConnection();
    $devices = $pdo->query("SELECT id, device_name, device_type FROM devices")->fetchAll();

    if (empty($devices)) {
        return 0;
    }

    $inserted = 0;
    $insertStmt = $pdo->prepare("
        INSERT INTO telemetry_data (device_id, temperature, humidity, status) 
        VALUES (:device_id, :temperature, :humidity, :status)
    ");

    $latestStmt = $pdo->prepare("
        SELECT temperature, humidity FROM telemetry_data 
        WHERE device_id = :device_id 
        ORDER BY recorded_at DESC, id DESC LIMIT 1
    ");

    for ($r = 0; $r < $rounds; $r++) {
        foreach ($devices as $device) {
            $latestStmt->execute([':device_id' => $device['id']]);
            $last = $latestStmt->fetch();

            if ($last) {
                // Realistische kontinuierliche Abweichung (Random Walk)
                $tempDelta = (rand(-8, 8) / 10.0); // -0.8 bis +0.8 °C
                $humDelta = (rand(-15, 15) / 10.0); // -1.5 bis +1.5 %

                $temp = round(max(5.0, min(50.0, (float) $last['temperature'] + $tempDelta)), 2);
                $hum = round(max(10.0, min(95.0, (float) $last['humidity'] + $humDelta)), 2);
            } else {
                // Basiswerte je nach Gerätetyp
                $temp = 22.0 + (rand(-20, 20) / 10.0);
                $hum = 45.0 + (rand(-40, 40) / 10.0);
            }

            // Statusbestimmung nach Schwellenwerten
            $status = 'OK';
            if ($temp > 35.0 || $temp < 10.0 || $hum > 80.0 || $hum < 20.0) {
                $status = 'KRITISCH';
            } elseif ($temp > 28.0 || $temp < 15.0 || $hum > 65.0 || $hum < 30.0) {
                $status = 'WARNUNG';
            }

            $insertStmt->execute([
                ':device_id' => $device['id'],
                ':temperature' => $temp,
                ':humidity' => $hum,
                ':status' => $status
            ]);
            $inserted++;
        }
    }

    return $inserted;
}
