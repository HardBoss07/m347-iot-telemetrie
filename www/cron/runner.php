<?php
require_once __DIR__ . '/mock_worker.php';

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
