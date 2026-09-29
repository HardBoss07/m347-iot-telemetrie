<?php
require_once __DIR__ . '/../config/db.php';

/*
 * Physik Luftfeuchtigkeit (Magnus-Formel):
 * In einem geschlossenen Raum bleibt die Wassermenge in der Luft (Taupunkt) fast gleich.
 * Wird es waermer, kann die Luft mehr Wasser aufnehmen -> relative Feuchtigkeit SINKT.
 * Wird es kaelter -> relative Feuchtigkeit STEIGT. (ca. -3 % pro +1 °C bei 50 %)
 */
const MAGNUS_A = 17.62;
const MAGNUS_B = 243.12;

/** Taupunkt (°C) aus Temperatur und relativer Feuchtigkeit */
function dewPoint(float $temp, float $rh): float
{
    $gamma = log(max($rh, 0.1) / 100.0) + (MAGNUS_A * $temp) / (MAGNUS_B + $temp);
    return (MAGNUS_B * $gamma) / (MAGNUS_A - $gamma);
}

/** Relative Feuchtigkeit (%) aus Temperatur und Taupunkt */
function relativeHumidity(float $temp, float $dew): float
{
    return 100.0 * exp((MAGNUS_A * $dew) / (MAGNUS_B + $dew) - (MAGNUS_A * $temp) / (MAGNUS_B + $temp));
}

/** Ein Schritt Random Walk: zufaellige Drift + Zug Richtung Zielwert */
function randomWalkStep(float $lastVal, float $target, float $driftSize, float $pullStrength): float
{
    $drift = (mt_rand(-1000, 1000) / 1000.0) * $driftSize;
    $pull = ($target - $lastVal) * $pullStrength;
    return $lastVal + $drift + $pull;
}

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

            // Temperatur zuerst berechnen, damit die Feuchtigkeit darauf reagieren kann
            uksort($thresholdConfig, function ($a, $b) {
                return ($b === 'temperature') <=> ($a === 'temperature');
            });

            $newMetrics = [];

            foreach ($thresholdConfig as $metricKey => $cfg) {
                $targetMid = ((float) ($cfg['min_ok'] ?? 20.0) + (float) ($cfg['max_ok'] ?? 25.0)) / 2.0;
                $lastVal = (float) ($lastMetrics[$metricKey] ?? $targetMid);

                if ($metricKey === 'humidity' && isset($newMetrics['temperature'], $thresholdConfig['temperature'])) {
                    // --- Feuchtigkeit physikalisch aus Temperatur + Taupunkt ---
                    $tCfg = $thresholdConfig['temperature'];
                    $tMid = ((float) ($tCfg['min_ok'] ?? 20.0) + (float) ($tCfg['max_ok'] ?? 25.0)) / 2.0;
                    $lastTemp = (float) ($lastMetrics['temperature'] ?? $tMid);

                    // Ziel-Taupunkt: der Taupunkt, bei dem Mitte-Temperatur = Mitte-Feuchtigkeit ergibt
                    $targetDew = dewPoint($tMid, $targetMid);
                    // Aktueller Taupunkt aus dem letzten Messwert
                    $lastDew = dewPoint($lastTemp, $lastVal);

                    // Die Wassermenge in der Luft aendert sich nur langsam (Luefter, Tueren, Personen)
                    $newDew = randomWalkStep($lastDew, $targetDew, 0.15, 0.05);

                    // Relative Feuchtigkeit ergibt sich aus der NEUEN Temperatur und dem Taupunkt
                    $newVal = relativeHumidity($newMetrics['temperature'], $newDew);
                    $newVal = max(0.0, min(100.0, $newVal)); // bleibt zwischen 0 und 100 %
                } else {
                    // --- Normaler Random Walk (Temperatur, Spannung usw.) ---
                    $newVal = randomWalkStep($lastVal, $targetMid, 1.0, 0.05);
                }

                $newMetrics[$metricKey] = round($newVal, 2);
            }

            $success = sendTelemetryViaApi($apiUrl, $device['api_token'], $newMetrics);
            if ($success) {
                $inserted++;
            }
        }
    }

    return $inserted;
}
