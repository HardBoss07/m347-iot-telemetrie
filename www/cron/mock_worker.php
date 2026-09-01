<?php
require_once __DIR__ . '/../config/db.php';

function generateMockData(int $count = 1): int
{
    $pdo = getDBConnection();

    // Alle existierenden Geräte holen
    $stmt = $pdo->query("SELECT id FROM devices");
    $devices = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($devices)) {
        return 0;
    }

    $inserted = 0;
    $insertStmt = $pdo->prepare("
        INSERT INTO telemetry_data (device_id, temperature, humidity, status) 
        VALUES (:device_id, :temperature, :humidity, :status)
    ");

    $statuses = ['OK', 'OK', 'OK', 'OK', 'WARNUNG', 'KRITISCH'];

    for ($i = 0; $i < $count; $i++) {
        $deviceId = $devices[array_rand($devices)];
        $temp = round(rand(150, 450) / 10, 2); // 15.0 - 45.0 °C
        $hum = round(rand(300, 900) / 10, 2);  // 30.0 - 90.0 %
        $status = $statuses[array_rand($statuses)];

        $insertStmt->execute([
            ':device_id' => $deviceId,
            ':temperature' => $temp,
            ':humidity' => $hum,
            ':status' => $status
        ]);
        $inserted++;
    }

    return $inserted;
}