<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';
require_once 'cron/mock_worker.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate'])) {
        $amount = (int) ($_POST['amount'] ?? 10);
        $count = generateMockData($amount);
        $message = "Erfolgreich $count neue Telemetrie-Datensätze generiert!";
    }

    if (isset($_POST['clear_data'])) {
        $pdo = getDBConnection();
        $pdo->exec("DELETE FROM telemetry_data");
        $message = "Alle Telemetrie-Daten wurden gelöscht!";
    }
}

include 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Admin Dashboard</h2>
    <a href="logout.php"
        style="background-color: #475569; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 0.9em;">Abmelden
        (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
</div>

<p>Hier kannst du die Datenbank zentral steuern und Testdaten simulieren.</p>

<?php if ($message): ?>
    <div class="alert"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card-grid">
    <div class="card">
        <h3>Telemetrie Simulieren</h3>
        <form method="POST">
            <p>Generiere zufällige Daten für alle registrierten Geräte:</p>
            <div style="margin-bottom: 15px;">
                <label for="amount">Anzahl Datensätze:</label><br>
                <select name="amount" id="amount"
                    style="padding: 8px; border-radius: 4px; background: #0f172a; color: #fff; border: 1px solid var(--border-color); margin-top: 5px;">
                    <option value="1">1 Datensatz</option>
                    <option value="10" selected>10 Datensätze</option>
                    <option value="50">50 Datensätze</option>
                    <option value="200">200 Datensätze</option>
                </select>
            </div>
            <button type="submit" name="generate">Simulierung starten</button>
        </form>
    </div>

    <div class="card">
        <h3>Datenbank Verwalten</h3>
        <form method="POST" onsubmit="return confirm('Möchtest du wirklich alle Telemetrie-Daten löschen?');">
            <p>Zurücksetzen aller gesammelten Telemetrie-Einträge:</p>
            <button type="submit" name="clear_data" style="background-color: var(--danger); color: white;">Alle
                Messdaten löschen</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
