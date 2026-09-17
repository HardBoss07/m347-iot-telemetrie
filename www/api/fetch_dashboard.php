<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db.php';

$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');
$deviceFilter = (int) ($_GET['device_id'] ?? 0);
$locationFilter = trim($_GET['location'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(d.device_name LIKE :search OR d.location LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($deviceFilter > 0) {
    $whereClauses[] = "t.device_id = :device_id";
    $params[':device_id'] = $deviceFilter;
}
if ($locationFilter !== '') {
    $whereClauses[] = "d.location = :location";
    $params[':location'] = $locationFilter;
}
if (in_array($statusFilter, ['OK', 'WARNUNG', 'KRITISCH'])) {
    $whereClauses[] = "t.status = :status";
    $params[':status'] = $statusFilter;
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$totalLogs = (int) $pdo->query("SELECT COUNT(*) FROM telemetry_data")->fetchColumn();
$criticalCount = (int) $pdo->query("SELECT COUNT(*) FROM telemetry_data WHERE status = 'KRITISCH'")->fetchColumn();

$allDevices = $pdo->query("SELECT id, device_name, location, threshold_config FROM devices ORDER BY device_name ASC")->fetchAll();
$locations = array_values(array_unique(array_column($allDevices, 'location')));

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM telemetry_data t JOIN devices d ON t.device_id = d.id $whereSql");
$countStmt->execute($params);
$totalFilteredLogs = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($totalFilteredLogs / $limit);

$logStmt = $pdo->prepare("
    SELECT t.id, t.device_id, t.recorded_at, t.metrics, t.status, d.device_name, d.location 
    FROM telemetry_data t 
    JOIN devices d ON t.device_id = d.id 
    $whereSql 
    ORDER BY t.recorded_at DESC 
    LIMIT :limit OFFSET :offset
");

foreach ($params as $key => $val) {
    $logStmt->bindValue($key, $val);
}
$logStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$logStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$logStmt->execute();
$rawLogs = $logStmt->fetchAll();

$logs = array_map(function ($l) {
    $l['metrics_decoded'] = json_decode($l['metrics'], true);
    return $l;
}, $rawLogs);

$deviceCharts = [];
foreach ($allDevices as $dev) {
    if ($deviceFilter > 0 && $dev['id'] !== $deviceFilter)
        continue;
    if ($locationFilter !== '' && $dev['location'] !== $locationFilter)
        continue;

    $cStmt = $pdo->prepare("
        SELECT t.metrics, DATE_FORMAT(t.recorded_at, '%H:%i:%s') as time_label
        FROM telemetry_data t
        WHERE t.device_id = :dev_id
        ORDER BY t.recorded_at DESC LIMIT 15
    ");
    $cStmt->execute([':dev_id' => $dev['id']]);
    $raw = array_reverse($cStmt->fetchAll());

    $labels = array_column($raw, 'time_label');
    $series = [];

    foreach ($raw as $row) {
        $m = json_decode($row['metrics'], true) ?? [];
        foreach ($m as $k => $v) {
            $series[$k][] = $v;
        }
    }

    $deviceCharts[] = [
        'id' => $dev['id'],
        'name' => $dev['device_name'],
        'location' => $dev['location'],
        'labels' => $labels,
        'series' => $series
    ];
}

echo json_encode([
    'total_logs' => $totalLogs,
    'critical_count' => $criticalCount,
    'locations' => $locations,
    'devices' => $allDevices,
    'logs' => $logs,
    'total_filtered' => $totalFilteredLogs,
    'page' => $page,
    'total_pages' => $totalPages,
    'charts' => $deviceCharts
], JSON_UNESCAPED_UNICODE);
