<?php
require_once 'config/db.php';
$pdo = getDBConnection();

$devices = $pdo->query("SELECT * FROM devices ORDER BY device_name ASC")->fetchAll();
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
    <h3>Telemetrie Verlaufsdiagramm</h3>
    <div style="max-height: 350px; position: relative;">
        <canvas id="telemetryChart"></canvas>
    </div>
</div>

<div class="card" style="margin-bottom: 25px;">
    <h3>Messdaten filtern und suchen</h3>
    <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 200px;">
            <label for="search" style="font-size: 0.9em; color: #94a3b8;">Suchbegriff (Name / Ort):</label>
            <input type="text" id="search" placeholder="z. B. Serverraum..."
                style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border-color); background: #0f172a; color: #fff; margin-top: 5px;">
        </div>

        <div style="flex: 1; min-width: 180px;">
            <label for="device_id" style="font-size: 0.9em; color: #94a3b8;">Gerät:</label>
            <select id="device_id"
                style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border-color); background: #0f172a; color: #fff; margin-top: 5px;">
                <option value="0">Alle Geräte</option>
                <?php foreach ($devices as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['device_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 150px;">
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
        <tbody id="telemetryBody">
        </tbody>
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
    let chartInstance = null;
    let currentDeviceNames = [];

    const searchInput = document.getElementById('search');
    const deviceSelect = document.getElementById('device_id');
    const statusSelect = document.getElementById('status');
    const resetBtn = document.getElementById('resetBtn');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    function initChart() {
        const ctx = document.getElementById('telemetryChart').getContext('2d');
        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Temperatur (°C)',
                        data: [],
                        borderColor: '#38bdf8',
                        backgroundColor: 'rgba(56, 189, 248, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Luftfeuchtigkeit (%)',
                        data: [],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#f8fafc' } },
                    tooltip: {
                        callbacks: {
                            afterBody: function (context) {
                                const index = context[0].dataIndex;
                                const deviceName = currentDeviceNames[index] || 'Unbekannt';
                                return 'Gerät: ' + deviceName;
                            }
                        }
                    }
                },
                scales: {
                    x: { ticks: { color: '#94a3b8' }, grid: { color: '#334155' } },
                    y: { ticks: { color: '#94a3b8' }, grid: { color: '#334155' } }
                }
            }
        });
    }

    function loadDashboardData() {
        const search = searchInput.value;
        const deviceId = deviceSelect.value;
        const status = statusSelect.value;

        const url = `api/fetch_dashboard.php?search=${encodeURIComponent(search)}&device_id=${deviceId}&status=${encodeURIComponent(status)}&page=${currentPage}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.total_logs !== undefined) {
                    document.getElementById('statTotalLogs').innerText = data.total_logs;
                }
                if (data.critical_count !== undefined) {
                    document.getElementById('statCriticalCount').innerText = data.critical_count;
                }

                // Tabelle aktualisieren
                const tbody = document.getElementById('telemetryBody');
                tbody.innerHTML = '';

                document.getElementById('totalRecords').innerText = data.total_filtered;
                document.getElementById('currentPage').innerText = data.page;
                document.getElementById('totalPages').innerText = data.total_pages || 1;

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

                // Pagination Steuerung
                prevBtn.disabled = data.page <= 1;
                prevBtn.style.opacity = data.page <= 1 ? '0.4' : '1';
                prevBtn.style.cursor = data.page <= 1 ? 'not-allowed' : 'pointer';

                nextBtn.disabled = data.page >= data.total_pages || data.total_pages === 0;
                nextBtn.style.opacity = (data.page >= data.total_pages || data.total_pages === 0) ? '0.4' : '1';
                nextBtn.style.cursor = (data.page >= data.total_pages || data.total_pages === 0) ? 'not-allowed' : 'pointer';

                // Chart aktualisieren
                currentDeviceNames = data.chart.device_names;
                chartInstance.data.labels = data.chart.labels;
                chartInstance.data.datasets[0].data = data.chart.temperatures;
                chartInstance.data.datasets[1].data = data.chart.humidities;
                chartInstance.update();
            });
    }

    // Attach auto-polling on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', () => {
        initChart();
        loadDashboardData();
        setInterval(loadDashboardData, 3000);
    });

    // Event-Listener für sofortiges Suchen/Filtern
    let debounceTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            currentPage = 1;
            loadDashboardData();
        }, 300);
    });

    deviceSelect.addEventListener('change', () => {
        currentPage = 1;
        loadDashboardData();
    });

    statusSelect.addEventListener('change', () => {
        currentPage = 1;
        loadDashboardData();
    });

    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        deviceSelect.value = '0';
        statusSelect.value = '';
        currentPage = 1;
        loadDashboardData();
    });

    prevBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadDashboardData();
        }
    });

    nextBtn.addEventListener('click', () => {
        currentPage++;
        loadDashboardData();
    });

    // Initialisieren
    document.addEventListener('DOMContentLoaded', () => {
        initChart();
        loadDashboardData();
    });
</script>

<?php include 'includes/footer.php'; ?>