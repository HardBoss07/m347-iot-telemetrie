<?php
require_once __DIR__ . '/mock_worker.php';

// Warten, bis die Datenbank erreichbar ist
$maxTries = 30;
$connected = false;
echo sprintf("[%s] [Worker] Verbinde mit Datenbank...\n", date('Y-m-d H:i:s'));

for ($i = 1; $i <= $maxTries; $i++) {
    try {
        $pdo = getDBConnection();
        $pdo->query("SELECT 1");
        $connected = true;
        break;
    } catch (Exception $e) {
        echo sprintf("[%s] [Worker] DB noch nicht bereit (Versuch %d/%d). Warte 2s...\n", date('Y-m-d H:i:s'), $i, $maxTries);
        sleep(2);
    }
}

if (!$connected) {
    echo sprintf("[%s] [Worker] FEHLER: Datenbank konnte nicht erreicht werden. Abbruch.\n", date('Y-m-d H:i:s'));
    exit(1);
}

$interval = (int) (getenv('GENERATOR_INTERVAL') ?: getenv('INTERVAL') ?: 5);

echo sprintf("[%s] [Worker] Telemetrie Background Generator gestartet (Interval: %ds)...\n", date('Y-m-d H:i:s'), $interval);

while (true) {
    if ($interval > 0) {
        $inserted = generateMockData(1);
        echo sprintf("[%s] %d Telemetrie-Datensätze verarbeitet.\n", date('Y-m-d H:i:s'), $inserted);
        sleep($interval);
    } else {
        sleep(5);
    }
}
