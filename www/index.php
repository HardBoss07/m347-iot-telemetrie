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

<div class="card" style="margin-bottom: 25px;">
    <h3>Messdaten filtern und suchen</h3>
    <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 180px;">
            <label for="search" style="font-size: 0.9em; color: #94a3b8;">Suchbegriff:</label>
            <input type="text" id="search" placeholder="Name..."
                style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border-color); background: #0f172a; color: #fff; margin-top: 5px;">
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label for="location" style="font-size: 0.9em; color: #94a3b8;">Raum / Standort:</label>
            <select id="location"
                style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border-color); background: #0f172a; color: #fff; margin-top: 5px;">
                <option value="">Alle Räume</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label for="device_id" style="font-size: 0.9em; color: #94a3b8;">Gerät:</label>
            <select id="device_id"
                style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border-color); background: #0f172a; color: #fff; margin-top: 5px;">
                <option value="0">Alle Geräte</option>
                <?php foreach ($devices as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['device_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 130px;">
            <label for="status" style="font-size: 0.9em; color: #94a3b8;">Status:</label>
            <select id="status"
                style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border-color); background: #0f172a; color: #fff; margin-top: 5px;">
                <option value="">Alle</option>
                <option value="OK">OK</option>
                <option value="WARNUNG">WARNUNG</option>
                <option value="KRITISCH">KRITISCH</option>
            </select>
        </div>

        <div>
            <button id="resetBtn" style="background: #475569; color: #fff;">Zurücksetzen</button>
        </div>
    </div>
</div>

<h3 style="margin-bottom: 15px;">Geräte-Telemetrie Verläufe</h3>
<div id="chartsContainer" class="card-grid" style="margin-bottom: 25px;"></div>

<h2>Telemetrie-Protokoll (<span id="totalRecords">0</span> Einträge)</h2>

<div id="tableContainer">
    <table>
        <thead>
            <tr>
                <th>Zeitstempel</th>
                <th>Gerät</th>
                <th>Standort</th>
                <th>Temperatur</th>
                <th>Luftfeuchtigkeit</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody id="telemetryBody"></tbody>
    </table>
</div>

<div id="paginationNav" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
    <span style="color: #94a3b8; font-size: 0.9em;">Seite <span id="currentPage">1</span> von <span
            id="totalPages">1</span></span>
    <div style="display: flex; gap: 5px;">
        <button id="prevBtn" class="btn" style="padding: 6px 12px; font-size: 0.9em;">Zurück</button>
        <button id="nextBtn" class="btn" style="padding: 6px 12px; font-size: 0.9em;">Weiter</button>
    </div>
</div>

<script>
    let currentPage = 1;
    let chartInstances = {};

    const searchInput = document.getElementById('search');
    const locationSelect = document.getElementById('location');
    const deviceSelect = document.getElementById('device_id');
    const statusSelect = document.getElementById('status');
    const resetBtn = document.getElementById('resetBtn');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    function renderOrUpdateCharts(chartsData) {
        const container = document.getElementById('chartsContainer');
        const activeIds = chartsData.map(c => c.id);

        // Entferne nicht mehr zutreffende Diagramme
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
                    <h4 style="margin: 0 0 10px 0; color: var(--accent-color);">${chart.name} <span style="font-size:0.8em; color:#94a3b8;">(${chart.location})</span></h4>
                    <div style="height: 220px; position: relative;">
                        <canvas id="canvas-${chart.id}"></canvas>
                    </div>
                `;
                container.appendChild(card);

                const ctx = document.getElementById(`canvas-${chart.id}`).getContext('2d');
                chartInstances[chart.id] = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chart.labels,
                        datasets: [
                            { label: 'Temperatur (°C)', data: chart.temperatures, borderColor: '#38bdf8', fill: false, tension: 0.2 },
                            { label: 'Feuchtigkeit (%)', data: chart.humidities, borderColor: '#10b981', fill: false, tension: 0.2 }
                        ]
                    },
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
                inst.data.datasets[0].data = chart.temperatures;
                inst.data.datasets[1].data = chart.humidities;
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
                document.getElementById('statTotalLogs').innerText = data.total_logs;
                document.getElementById('statCriticalCount').innerText = data.critical_count;
                document.getElementById('totalRecords').innerText = data.total_filtered;
                document.getElementById('currentPage').innerText = data.page;
                document.getElementById('totalPages').innerText = data.total_pages || 1;

                const tbody = document.getElementById('telemetryBody');
                tbody.innerHTML = '';

                if (data.logs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Keine Datensätze gefunden.</td></tr>';
                } else {
                    data.logs.forEach(log => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${log.recorded_at}</td>
                            <td><strong>${log.device_name}</strong></td>
                            <td>${log.location}</td>
                            <td>${log.temperature} °C</td>
                            <td>${log.humidity} %</td>
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
