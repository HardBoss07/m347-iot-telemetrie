<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db.php';

$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');
$deviceFilter = (int) ($_GET['device_id'] ?? 0);
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
if (in_array($statusFilter, ['OK', 'WARNUNG', 'KRITISCH'])) {
    $whereClauses[] = "t.status = :status";
    $params[':status'] = $statusFilter;
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Summary Card Totals
$totalLogs = (int) $pdo->query("SELECT COUNT(*) FROM telemetry_data")->fetchColumn();
$criticalCount = (int) $pdo->query("SELECT COUNT(*) FROM telemetry_data WHERE status = 'KRITISCH'")->fetchColumn();

// Count Total Filtered
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM telemetry_data t JOIN devices d ON t.device_id = d.id $whereSql");
$countStmt->execute($params);
$totalFilteredLogs = (int) $countStmt->fetchColumn();
$totalPages = ceil($totalFilteredLogs / $limit);

// Fetch Table Data
$logStmt = $pdo->prepare("
    SELECT t.recorded_at, t.temperature, t.humidity, t.status, d.device_name, d.location 
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
$logs = $logStmt->fetchAll();

// Fetch Chart Data (letzte 15 Einträge)
$chartStmt = $pdo->prepare("
    SELECT t.temperature, t.humidity, DATE_FORMAT(t.recorded_at, '%H:%i:%s') as time_label, d.device_name
    FROM telemetry_data t
    JOIN devices d ON t.device_id = d.id
    $whereSql
    ORDER BY t.recorded_at DESC 
    LIMIT 15
");
$chartStmt->execute($params);
$chartRaw = array_reverse($chartStmt->fetchAll());

echo json_encode([
    'total_logs' => $totalLogs,
    'critical_count' => $criticalCount,
    'logs' => $logs,
    'total_filtered' => $totalFilteredLogs,
    'page' => $page,
    'total_pages' => $totalPages,
    'chart' => [
        'labels' => array_column($chartRaw, 'time_label'),
        'temperatures' => array_column($chartRaw, 'temperature'),
        'humidities' => array_column($chartRaw, 'humidity'),
        'device_names' => array_column($chartRaw, 'device_name')
    ]
], JSON_UNESCAPED_UNICODE);
