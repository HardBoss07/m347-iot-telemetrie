<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';
require_once 'cron/mock_worker.php';

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle CSV export request
if (isset($_POST['export_csv_admin'])) {
    $stmt = $pdo->query("
        SELECT t.recorded_at, d.device_name, d.location, t.status, t.metrics 
        FROM telemetry_data t 
        JOIN devices d ON t.device_id = d.id 
        ORDER BY t.recorded_at DESC
    ");
    $allLogs = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=telemetrie_export_full_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Zeitstempel', 'Gerät', 'Standort', 'Status', 'Messwerte_JSON'], ';');
    foreach ($allLogs as $row) {
        fputcsv($output, [$row['recorded_at'], $row['device_name'], $row['location'], $row['status'], $row['metrics']], ';');
    }
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_device'])) {
        $name = trim($_POST['device_name'] ?? '');
        $type = trim($_POST['device_type'] ?? '');
        $location = trim($_POST['location'] ?? '');

        $keys = $_POST['metric_key'] ?? [];
        $minOk = $_POST['metric_min_ok'] ?? [];
        $maxOk = $_POST['metric_max_ok'] ?? [];
        $minWarn = $_POST['metric_min_warn'] ?? [];
        $maxWarn = $_POST['metric_max_warn'] ?? [];

        $thresholdConfig = [];
        for ($i = 0; $i < count($keys); $i++) {
            $keyName = strtolower(trim($keys[$i]));
            if ($keyName !== '') {
                $thresholdConfig[$keyName] = [
                    'min_ok' => (float) ($minOk[$i] ?? 0),
                    'max_ok' => (float) ($maxOk[$i] ?? 0),
                    'min_warn' => (float) ($minWarn[$i] ?? 0),
                    'max_warn' => (float) ($maxWarn[$i] ?? 0)
                ];
            }
        }

        if ($name !== '' && $type !== '' && $location !== '' && !empty($thresholdConfig)) {
            $token = 'tok_' . bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("
                INSERT INTO devices (device_name, device_type, location, api_token, threshold_config, is_paused) 
                VALUES (:name, :type, :location, :token, :config, 0)
            ");
            $stmt->execute([
                ':name' => $name,
                ':type' => $type,
                ':location' => $location,
                ':token' => $token,
                ':config' => json_encode($thresholdConfig)
            ]);
            $message = "Gerät '$name' erfolgreich registriert!";
        } else {
            $error = "Bitte alle Pflichtfelder ausfüllen und mindestens ein Messfeld definieren.";
        }
    }

    if (isset($_POST['toggle_pause'])) {
        $deviceId = (int) ($_POST['device_id'] ?? 0);
        if ($deviceId > 0) {
            $stmt = $pdo->prepare("UPDATE devices SET is_paused = NOT is_paused WHERE id = :id");
            $stmt->execute([':id' => $deviceId]);
            $message = "Sensor-Status wurde geändert!";
        }
    }

    if (isset($_POST['regenerate_token'])) {
        $deviceId = (int) ($_POST['device_id'] ?? 0);
        if ($deviceId > 0) {
            $newToken = 'tok_' . bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("UPDATE devices SET api_token = :token WHERE id = :id");
            $stmt->execute([':token' => $newToken, ':id' => $deviceId]);
            $message = "API-Token erneuert!";
        }
    }

    if (isset($_POST['delete_device'])) {
        $deviceId = (int) ($_POST['device_id'] ?? 0);
        if ($deviceId > 0) {
            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = :id");
            $stmt->execute([':id' => $deviceId]);
            $message = "Gerät entfernt!";
        }
    }

    if (isset($_POST['generate'])) {
        $amount = (int) ($_POST['amount'] ?? 10);
        $count = generateMockData($amount);
        $message = "Erfolgreich $count neue Telemetrie-Datensätze generiert!";
    }

    if (isset($_POST['clear_data'])) {
        $pdo->exec("DELETE FROM telemetry_data");
        $message = "Alle Telemetrie-Daten wurden gelöscht!";
    }
}

$devices = $pdo->query("SELECT * FROM devices ORDER BY device_name ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="flex-between mb-3">
    <h2>Admin Verwaltung</h2>
    <a href="logout.php" class="btn btn-secondary btn-sm">Abmelden (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
</div>

<?php if ($message): ?>
    <div class="alert"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card-grid">
    <div class="card">
        <h3>Neues Gerät Registrieren</h3>
        <form method="POST">
            <div class="form-group">
                <label for="device_name">Gerätename:</label>
                <input type="text" name="device_name" id="device_name" class="form-control" required
                    placeholder="z. B. Sensor-Kühlraum">
            </div>
            <div class="form-group">
                <label for="device_type">Gerätetyp:</label>
                <input type="text" name="device_type" id="device_type" class="form-control" required
                    placeholder="z. B. Umwelt-Monitor">
            </div>
            <div class="form-group mb-3">
                <label for="location">Standort / Raum:</label>
                <input type="text" name="location" id="location" class="form-control" required
                    placeholder="z. B. Lagerhalle B">
            </div>

            <h4>Dynamische Messfelder & Schwellenwerte</h4>
            <div id="metricsContainer">
                <div class="metric-builder-row">
                    <div class="form-group">
                        <label>Messwert Name (z. B. temperature, humidity, voltage, co2):</label>
                        <input type="text" name="metric_key[]" class="form-control" value="temperature" required>
                    </div>
                    <div class="form-row mt-2">
                        <div>
                            <small class="text-muted">Min OK:</small>
                            <input type="number" step="0.1" name="metric_min_ok[]" class="form-control form-control-sm"
                                value="18.0">
                        </div>
                        <div>
                            <small class="text-muted">Max OK:</small>
                            <input type="number" step="0.1" name="metric_max_ok[]" class="form-control form-control-sm"
                                value="24.0">
                        </div>
                        <div>
                            <small class="text-muted">Min Warnung:</small>
                            <input type="number" step="0.1" name="metric_min_warn[]"
                                class="form-control form-control-sm" value="15.0">
                        </div>
                        <div>
                            <small class="text-muted">Max Warnung:</small>
                            <input type="number" step="0.1" name="metric_max_warn[]"
                                class="form-control form-control-sm" value="28.0">
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" id="addMetricBtn" class="btn btn-secondary btn-sm mb-3">+ Weiteres Messfeld
                hinzufügen</button>
            <br>
            <button type="submit" name="add_device" class="btn btn-primary">Gerät Speichern</button>
        </form>
    </div>

    <div class="card">
        <h3>Telemetrie Simulieren & Daten Export</h3>
        <form method="POST" class="mb-4">
            <p class="text-muted">Generiere Messwerte basierend auf den definierten Schwellenwerten:</p>
            <div class="form-group mb-3">
                <label for="amount">Durchläufe pro Gerät:</label>
                <select name="amount" id="amount" class="form-select">
                    <option value="1">1 Durchlauf</option>
                    <option value="10" selected>10 Durchläufe</option>
                    <option value="50">50 Durchläufe</option>
                </select>
            </div>
            <button type="submit" name="generate" class="btn btn-primary">Simulierung starten</button>
        </form>

        <hr style="border-color: var(--border-color); margin: 20px 0;">

        <h3>Gesamter Datensatz Export</h3>
        <p class="text-muted">Exportiere alle gespeicherten Telemetrie-Einträge der Datenbank als CSV File:</p>
        <form method="POST">
            <button type="submit" name="export_csv_admin" class="btn btn-success">Alle Telemetriedaten als CSV
                herunterladen</button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <h3>Registrierte Geräte & API Tokens</h3>
    <table>
        <thead>
            <tr>
                <th>Gerät</th>
                <th>Standort</th>
                <th>Status</th>
                <th>API Bearer Token</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($devices as $d): ?>
                <tr>
                    <td>
                        <a href="sensor.php?id=<?= $d['id'] ?>" class="btn-link"
                            style="color: var(--accent-color); font-weight: bold; text-decoration: none;">
                            <?= htmlspecialchars($d['device_name']) ?>
                        </a><br>
                        <small class="text-muted"><?= htmlspecialchars($d['device_type']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($d['location']) ?></td>
                    <td>
                        <?php if (!empty($d['is_paused'])): ?>
                            <span class="badge" style="background-color: #f59e0b; color: #000;">PAUSIERT</span>
                        <?php else: ?>
                            <span class="badge badge-OK">AKTIV</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="token-code"><?= htmlspecialchars($d['api_token']) ?></span></td>
                    <td>
                        <div class="flex-gap-2">
                            <a href="sensor.php?id=<?= $d['id'] ?>" class="btn btn-secondary btn-sm">Details</a>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="device_id" value="<?= $d['id'] ?>">
                                <?php if (!empty($d['is_paused'])): ?>
                                    <button type="submit" name="toggle_pause" class="btn btn-success btn-sm">Fortsetzen</button>
                                <?php else: ?>
                                    <button type="submit" name="toggle_pause" class="btn btn-warning btn-sm">Pausieren</button>
                                <?php endif; ?>
                            </form>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="device_id" value="<?= $d['id'] ?>">
                                <button type="submit" name="regenerate_token" class="btn btn-warning btn-sm">Token
                                    Reset</button>
                            </form>
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Gerät wirklich löschen?');">
                                <input type="hidden" name="device_id" value="<?= $d['id'] ?>">
                                <button type="submit" name="delete_device" class="btn btn-danger btn-sm">Löschen</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Datenbank Verwalten</h3>
    <form method="POST" onsubmit="return confirm('Möchtest du wirklich alle Telemetrie-Daten löschen?');">
        <button type="submit" name="clear_data" class="btn btn-danger">Alle Messdaten löschen</button>
    </form>
</div>

<script>
    document.getElementById('addMetricBtn').addEventListener('click', () => {
        const container = document.getElementById('metricsContainer');
        const row = document.createElement('div');
        row.className = 'metric-builder-row';
        row.innerHTML = `
            <div class="form-group">
                <div class="flex-between">
                    <label>Messwert Name:</label>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.metric-builder-row').remove();">Entfernen</button>
                </div>
                <input type="text" name="metric_key[]" class="form-control" placeholder="z. B. humidity, voltage, co2" required>
            </div>
            <div class="form-row mt-2">
                <div>
                    <small class="text-muted">Min OK:</small>
                    <input type="number" step="0.1" name="metric_min_ok[]" class="form-control form-control-sm" value="0.0">
                </div>
                <div>
                    <small class="text-muted">Max OK:</small>
                    <input type="number" step="0.1" name="metric_max_ok[]" class="form-control form-control-sm" value="100.0">
                </div>
                <div>
                    <small class="text-muted">Min Warnung:</small>
                    <input type="number" step="0.1" name="metric_min_warn[]" class="form-control form-control-sm" value="-10.0">
                </div>
                <div>
                    <small class="text-muted">Max Warnung:</small>
                    <input type="number" step="0.1" name="metric_max_warn[]" class="form-control form-control-sm" value="110.0">
                </div>
            </div>
        `;
        container.appendChild(row);
    });
</script>

<?php include 'includes/footer.php'; ?>