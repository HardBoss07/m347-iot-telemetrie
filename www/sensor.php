<?php
require_once 'config/db.php';
$pdo = getDBConnection();

$refreshIntervalMs = ((int) (getenv('GENERATOR_INTERVAL') ?: 5)) * 1000;

$deviceId = (int) ($_GET['id'] ?? 0);
if ($deviceId <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = :id");
$stmt->execute([':id' => $deviceId]);
$device = $stmt->fetch();

if (!$device) {
    die("Gerät nicht gefunden.");
}

$thresholdConfig = json_decode($device['threshold_config'] ?? '{}', true);

function getMetricTranslation(string $key): string
{
    $translations = [
        'temperature' => 'Temperatur (°C)',
        'humidity' => 'Luftfeuchtigkeit (%)',
        'voltage' => 'Spannung (V)',
        'co2' => 'CO2-Gehalt (ppm)',
        'pressure' => 'Luftdruck (hPa)'
    ];
    return $translations[strtolower($key)] ?? ucfirst($key);
}

include 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="mb-3">
    <a href="index.php" class="btn btn-secondary btn-sm">&larr; Zurück zur Übersicht</a>
</div>

<div class="flex-between mb-3">
    <div>
        <h2 style="margin: 0; display: inline-flex; align-items: center; gap: 8px;">
            <?= htmlspecialchars($device['device_name']) ?>
            <?php if (!empty($device['is_paused'])): ?>
                <span class="badge"
                    style="background-color: #f59e0b; color: #000; font-size: 0.5em; vertical-align: middle;">PAUSIERT</span>
            <?php endif; ?>
        </h2>
        <br>
        <span class="text-muted"><?= htmlspecialchars($device['device_type']) ?> : Standort:
            <?= htmlspecialchars($device['location']) ?></span>
    </div>
    <div>
        <button id="exportSensorCsv" class="btn btn-success">CSV Exportieren</button>
    </div>
</div>

<div class="card-grid">
    <div class="card" style="grid-column: span 2;">
        <h3>Live Verlauf (Automatische Aktualisierung)</h3>
        <div class="chart-box-lg">
            <canvas id="sensorChart"></canvas>
        </div>
    </div>

    <div class="card">
        <h3>Konfigurierte Schwellenwerte</h3>
        <?php if (empty($thresholdConfig)): ?>
            <p class="text-muted">Keine Schwellenwerte definiert.</p>
        <?php else: ?>
            <table style="margin: 0;">
                <thead>
                    <tr>
                        <th>Messwert</th>
                        <th>OK Bereich</th>
                        <th>Warnbereich</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($thresholdConfig as $metric => $cfg): ?>
                        <tr>
                            <td><strong><?= getMetricTranslation($metric) ?></strong></td>
                            <td style="color: var(--success);"><?= $cfg['min_ok'] ?? '-' ?> bis <?= $cfg['max_ok'] ?? '-' ?></td>
                            <td style="color: var(--warning);"><?= $cfg['min_warn'] ?? '-' ?> bis <?= $cfg['max_warn'] ?? '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3>Verlaufsprotokoll (Letzte Messungen)</h3>
    <table>
        <thead>
            <tr>
                <th>Zeitstempel</th>
                <th>Gemessene Werte</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody id="sensorTableBody"></tbody>
    </table>
</div>

<script>
    const refreshInterval = <?= $refreshIntervalMs ?>;
    const deviceId = <?= $deviceId ?>;
    let chartInstance = null;
    let currentRawLogs = [];

    const translations = {
        temperature: 'Temperatur',
        humidity: 'Feuchtigkeit',
        voltage: 'Spannung',
        co2: 'CO2',
        pressure: 'Druck'
    };

    const units = {
        temperature: '°C',
        humidity: '%',
        voltage: 'V',
        co2: 'ppm',
        pressure: 'hPa'
    };

    const palette = ['#38bdf8', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'];

    function fetchSensorData() {
        fetch(`api/fetch_dashboard.php?device_id=${deviceId}`)
            .then(res => res.json())
            .then(data => {
                currentRawLogs = data.logs || [];

                // Update Table
                const tbody = document.getElementById('sensorTableBody');
                tbody.innerHTML = '';

                if (currentRawLogs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">Keine Messdaten vorhanden.</td></tr>';
                } else {
                    currentRawLogs.forEach(log => {
                        const tr = document.createElement('tr');
                        const metricsPills = Object.entries(log.metrics_decoded || {})
                            .map(([k, v]) => {
                                const trName = translations[k] || k;
                                const uStr = units[k] ? ` ${units[k]}` : '';
                                return `<span class="metric-pill"><strong>${trName}:</strong> ${v}${uStr}</span>`;
                            })
                            .join(' ');

                        tr.innerHTML = `
                            <td>${log.recorded_at}</td>
                            <td>${metricsPills || '<em>Keine Werte</em>'}</td>
                            <td><span class="badge badge-${log.status}">${log.status}</span></td>
                        `;
                        tbody.appendChild(tr);
                    });
                }

                // Update Chart
                const chartData = data.charts && data.charts.length > 0 ? data.charts[0] : null;
                if (chartData) {
                    const chartBox = document.querySelector('.chart-box-lg');
                    if (chartBox) {
                        chartBox.style.opacity = chartData.is_paused ? '0.5' : '1';
                        chartBox.style.filter = chartData.is_paused ? 'grayscale(0.8)' : 'none';
                    }

                    const datasets = [];
                    let cIdx = 0;
                    for (const [key, values] of Object.entries(chartData.series || {})) {
                        const labelName = (translations[key] || key) + (units[key] ? ` (${units[key]})` : '');
                        datasets.push({
                            label: labelName,
                            data: values,
                            borderColor: palette[cIdx % palette.length],
                            fill: false,
                            tension: 0.2
                        });
                        cIdx++;
                    }

                    if (!chartInstance) {
                        const ctx = document.getElementById('sensorChart').getContext('2d');
                        chartInstance = new Chart(ctx, {
                            type: 'line',
                            data: { labels: chartData.labels, datasets: datasets },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: chartData.is_paused ? false : { duration: 300 },
                                plugins: { legend: { labels: { color: '#f8fafc' } } },
                                scales: {
                                    x: { ticks: { color: '#94a3b8' }, grid: { color: '#334155' } },
                                    y: { ticks: { color: '#94a3b8' }, grid: { color: '#334155' } }
                                }
                            }
                        });
                    } else {
                        const currentLabelsJson = JSON.stringify(chartInstance.data.labels);
                        const newLabelsJson = JSON.stringify(chartData.labels);

                        if (chartData.is_paused && currentLabelsJson === newLabelsJson) {
                            return;
                        }

                        chartInstance.data.labels = chartData.labels;
                        chartInstance.data.datasets = datasets;

                        if (chartData.is_paused) {
                            chartInstance.update('none');
                        } else {
                            chartInstance.update();
                        }
                    }
                }
            });
    }

    document.getElementById('exportSensorCsv').addEventListener('click', () => {
        if (currentRawLogs.length === 0) {
            alert('Keine Datensätze vorhanden.');
            return;
        }
        let csv = 'Zeitstempel;Status;Messwerte\n';
        currentRawLogs.forEach(l => {
            const mStr = JSON.stringify(l.metrics_decoded || {}).replace(/"/g, '""');
            csv += `"${l.recorded_at}";"${l.status}";"${mStr}"\n`;
        });
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', `sensor_export_${deviceId}_${new Date().toISOString().slice(0, 10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    document.addEventListener('DOMContentLoaded', () => {
        fetchSensorData();
        setInterval(fetchSensorData, refreshInterval);
    });
</script>

<?php include 'includes/footer.php'; ?>