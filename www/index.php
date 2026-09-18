<?php
require_once 'config/db.php';
$pdo = getDBConnection();

$devices = $pdo->query("SELECT * FROM devices ORDER BY device_name ASC")->fetchAll();
$locations = $pdo->query("SELECT DISTINCT location FROM devices ORDER BY location ASC")->fetchAll(PDO::FETCH_COLUMN);
$totalDevices = count($devices);
$totalLogs = $pdo->query("SELECT COUNT(*) FROM telemetry_data")->fetchColumn();
$criticalCount = $pdo->query("SELECT COUNT(*) FROM telemetry_data WHERE status = 'KRITISCH'")->fetchColumn();

include 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="card-grid">
    <div class="card">
        <h3>Registrierte Geräte</h3>
        <h2><?= $totalDevices ?></h2>
    </div>
    <div class="card">
        <h3>Gesamt Messungen</h3>
        <h2 id="statTotalLogs"><?= $totalLogs ?></h2>
    </div>
    <div class="card">
        <h3>Kritische Warnungen</h3>
        <h2 id="statCriticalCount" style="color: var(--danger);"><?= $criticalCount ?></h2>
    </div>
</div>

<div class="card mb-4">
    <h3>Messdaten filtern und suchen</h3>
    <div class="flex-wrap-end">
        <div style="flex: 1; min-width: 180px;">
            <label for="search">Suchbegriff:</label>
            <input type="text" id="search" class="form-control" placeholder="Name...">
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label for="location">Raum / Standort:</label>
            <select id="location" class="form-select">
                <option value="">Alle Räume</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label for="device_id">Gerät:</label>
            <select id="device_id" class="form-select">
                <option value="0">Alle Geräte</option>
                <?php foreach ($devices as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['device_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 130px;">
            <label for="status">Status:</label>
            <select id="status" class="form-select">
                <option value="">Alle</option>
                <option value="OK">OK</option>
                <option value="WARNUNG">WARNUNG</option>
                <option value="KRITISCH">KRITISCH</option>
            </select>
        </div>

        <div class="flex-gap-2">
            <button id="resetBtn" class="btn btn-secondary">Zurücksetzen</button>
        </div>
    </div>
</div>

<h3 class="mb-3">Geräte-Telemetrie Verläufe</h3>
<div id="chartsContainer" class="card-grid mb-4"></div>

<h2>Telemetrie-Protokoll (<span id="totalRecords">0</span> Einträge)</h2>

<div id="tableContainer">
    <table>
        <thead>
            <tr>
                <th>Zeitstempel</th>
                <th>Gerät</th>
                <th>Standort</th>
                <th>Messwerte</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody id="telemetryBody"></tbody>
    </table>
</div>

<div id="paginationNav" class="flex-between mt-3">
    <span class="text-muted">Seite <span id="currentPage">1</span> von <span id="totalPages">1</span></span>
    <div class="flex-gap-2">
        <button id="prevBtn" class="btn btn-sm">Zurück</button>
        <button id="nextBtn" class="btn btn-sm">Weiter</button>
    </div>
</div>

<script>
    let currentPage = 1;
    let chartInstances = {};
    let currentFetchedLogs = [];

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

    const searchInput = document.getElementById('search');
    const locationSelect = document.getElementById('location');
    const deviceSelect = document.getElementById('device_id');
    const statusSelect = document.getElementById('status');
    const resetBtn = document.getElementById('resetBtn');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    const palette = ['#38bdf8', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'];

    function renderOrUpdateCharts(chartsData) {
        const container = document.getElementById('chartsContainer');
        const activeIds = chartsData.map(c => c.id);

        Object.keys(chartInstances).forEach(id => {
            if (!activeIds.includes(parseInt(id))) {
                chartInstances[id].destroy();
                delete chartInstances[id];
                const card = document.getElementById(`chart-card-${id}`);
                if (card) card.remove();
            }
        });

        chartsData.forEach(chart => {
            let card = document.getElementById(`chart-card-${chart.id}`);
            if (!card) {
                card = document.createElement('div');
                card.className = 'card';
                card.id = `chart-card-${chart.id}`;
                card.innerHTML = `
                    <div class="flex-between mb-2">
                        <h4 style="margin: 0; color: var(--accent-color);">${chart.name} <span class="text-muted">(${chart.location})</span></h4>
                        <a href="sensor.php?id=${chart.id}" style="color: var(--accent-color); font-size: 0.8em; text-decoration: none;">Details &rarr;</a>
                    </div>
                    <div class="chart-box">
                        <canvas id="canvas-${chart.id}"></canvas>
                    </div>
                `;
                container.appendChild(card);

                const datasets = [];
                let colorIndex = 0;
                for (const [metricKey, values] of Object.entries(chart.series || {})) {
                    const labelName = (translations[metricKey] || metricKey) + (units[metricKey] ? ` (${units[metricKey]})` : '');
                    datasets.push({
                        label: labelName,
                        data: values,
                        borderColor: palette[colorIndex % palette.length],
                        fill: false,
                        tension: 0.2
                    });
                    colorIndex++;
                }

                const ctx = document.getElementById(`canvas-${chart.id}`).getContext('2d');
                chartInstances[chart.id] = new Chart(ctx, {
                    type: 'line',
                    data: { labels: chart.labels, datasets: datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { labels: { color: '#f8fafc', font: { size: 10 } } } },
                        scales: {
                            x: { ticks: { color: '#94a3b8', font: { size: 9 } }, grid: { color: '#334155' } },
                            y: { ticks: { color: '#94a3b8', font: { size: 9 } }, grid: { color: '#334155' } }
                        }
                    }
                });
            } else {
                const inst = chartInstances[chart.id];
                inst.data.labels = chart.labels;
                const datasets = [];
                let colorIndex = 0;
                for (const [metricKey, values] of Object.entries(chart.series || {})) {
                    const labelName = (translations[metricKey] || metricKey) + (units[metricKey] ? ` (${units[metricKey]})` : '');
                    datasets.push({
                        label: labelName,
                        data: values,
                        borderColor: palette[colorIndex % palette.length],
                        fill: false,
                        tension: 0.2
                    });
                    colorIndex++;
                }
                inst.data.datasets = datasets;
                inst.update();
            }
        });
    }

    function loadDashboardData() {
        const search = searchInput.value;
        const location = locationSelect.value;
        const deviceId = deviceSelect.value;
        const status = statusSelect.value;

        const url = `api/fetch_dashboard.php?search=${encodeURIComponent(search)}&location=${encodeURIComponent(location)}&device_id=${deviceId}&status=${encodeURIComponent(status)}&page=${currentPage}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                currentFetchedLogs = data.logs || [];
                document.getElementById('statTotalLogs').innerText = data.total_logs;
                document.getElementById('statCriticalCount').innerText = data.critical_count;
                document.getElementById('totalRecords').innerText = data.total_filtered;
                document.getElementById('currentPage').innerText = data.page;
                document.getElementById('totalPages').innerText = data.total_pages || 1;

                const tbody = document.getElementById('telemetryBody');
                tbody.innerHTML = '';

                if (currentFetchedLogs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Keine Datensätze gefunden.</td></tr>';
                } else {
                    currentFetchedLogs.forEach(log => {
                        const tr = document.createElement('tr');
                        const metricsFormatted = Object.entries(log.metrics_decoded || {})
                            .map(([k, v]) => {
                                const translated = translations[k] || k;
                                const unitStr = units[k] ? ` ${units[k]}` : '';
                                return `<span class="metric-pill"><strong>${translated}:</strong> ${v}${unitStr}</span>`;
                            })
                            .join(' ');

                        tr.innerHTML = `
                            <td>${log.recorded_at}</td>
                            <td><a href="sensor.php?id=${log.device_id}" style="color: var(--accent-color); font-weight: bold; text-decoration: none;">${log.device_name}</a></td>
                            <td>${log.location}</td>
                            <td>${metricsFormatted || '<em>Keine Werte</em>'}</td>
                            <td><span class="badge badge-${log.status}">${log.status}</span></td>
                        `;
                        tbody.appendChild(tr);
                    });
                }

                prevBtn.disabled = data.page <= 1;
                prevBtn.style.opacity = data.page <= 1 ? '0.4' : '1';
                nextBtn.disabled = data.page >= data.total_pages || data.total_pages === 0;
                nextBtn.style.opacity = (data.page >= data.total_pages || data.total_pages === 0) ? '0.4' : '1';

                renderOrUpdateCharts(data.charts);
            });
    }

    let debounceTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => { currentPage = 1; loadDashboardData(); }, 250);
    });

    [locationSelect, deviceSelect, statusSelect].forEach(el => {
        el.addEventListener('change', () => { currentPage = 1; loadDashboardData(); });
    });

    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        locationSelect.value = '';
        deviceSelect.value = '0';
        statusSelect.value = '';
        currentPage = 1;
        loadDashboardData();
    });

    prevBtn.addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadDashboardData(); } });
    nextBtn.addEventListener('click', () => { currentPage++; loadDashboardData(); });

    document.addEventListener('DOMContentLoaded', () => {
        loadDashboardData();
        setInterval(loadDashboardData, 3000);
    });
</script>

<?php include 'includes/footer.php'; ?>