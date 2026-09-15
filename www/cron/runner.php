<?php
require_once __DIR__ . '/mock_worker.php';

$interval = (int) (getenv('GENERATOR_INTERVAL') ?: getenv('INTERVAL') ?: 5);

echo "[Worker] Telemetrie Background Generator gestartet (Interval: {$interval}s)...\n";

while (true) {
    if ($interval > 0) {
        $inserted = generateMockData(1);
        echo sprintf("[%s] %d Datensatz generiert.\n", date('Y-m-d H:i:s'), $inserted);
        sleep($interval);
    } else {
        sleep(5);
    }
}
