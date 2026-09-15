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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_device'])) {
        $name = trim($_POST['device_name'] ?? '');
        $type = trim($_POST['device_type'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if ($name !== '' && $type !== '' && $location !== '') {
            $stmt = $pdo->prepare("INSERT INTO devices (device_name, device_type, location) VALUES (:name, :type, :location)");
            $stmt->execute([':name' => $name, ':type' => $type, ':location' => $location]);
            $message = "Gerät '$name' erfolgreich hinzugefügt!";
        } else {
            $error = "Bitte alle Felder für das neue Gerät ausfüllen.";
        }
    }

    if (isset($_POST['delete_device'])) {
        $deviceId = (int) ($_POST['device_id'] ?? 0);
        if ($deviceId > 0) {
            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = :id");
            $stmt->execute([':id' => $deviceId]);
            $message = "Gerät erfolgreich entfernt!";
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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Admin Dashboard</h2>
    <a href="logout.php"
        style="background-color: #475569; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 0.9em;">Abmelden
        (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
</div>

<?php if ($message): ?>
    <div class="alert"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert" style="background-color: var(--danger); color: white;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card-grid">
    <div class="card">
        <h3>Neues Gerät Registrieren</h3>
        <form method="POST">
            <div style="margin-bottom: 10px;">
                <label for="device_name">Gerätename:</label>
                <input type="text" name="device_name" id="device_name" required
                    style="width: 100%; padding: 8px; border-radius: 4px; background: #0f172a; color: #fff; border: 1px solid var(--border-color); margin-top: 5px;">
            </div>
            <div style="margin-bottom: 10px;">
                <label for="device_type">Gerätetyp:</label>
                <input type="text" name="device_type" id="device_type" required placeholder="z. B. Temperatursensor"
                    style="width: 100%; padding: 8px; border-radius: 4px; background: #0f172a; color: #fff; border: 1px solid var(--border-color); margin-top: 5px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label for="location">Standort / Raum:</label>
                <input type="text" name="location" id="location" required placeholder="z. B. Raum 101"
                    style="width: 100%; padding: 8px; border-radius: 4px; background: #0f172a; color: #fff; border: 1px solid var(--border-color); margin-top: 5px;">
            </div>
            <button type="submit" name="add_device">Gerät Speichern</button>
        </form>
    </div>

    <div class="card">
        <h3>Telemetrie Simulieren</h3>
        <form method="POST">
            <p>Generiere zufällige Daten für alle registrierten Geräte:</p>
            <div style="margin-bottom: 15px;">
                <label for="amount">Durchläufe pro Gerät:</label><br>
                <select name="amount" id="amount"
                    style="padding: 8px; border-radius: 4px; background: #0f172a; color: #fff; border: 1px solid var(--border-color); margin-top: 5px;">
                    <option value="1">1 Durchlauf</option>
                    <option value="10" selected>10 Durchläufe</option>
                    <option value="50">50 Durchläufe</option>
                </select>
            </div>
            <button type="submit" name="generate">Simulierung starten</button>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom: 25px;">
    <h3>Registrierte Geräte Verwaltung</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Typ</th>
                <th>Standort</th>
                <th>Aktion</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($devices as $d): ?>
                <tr>
                    <td><?= $d['id'] ?></td>
                    <td><strong><?= htmlspecialchars($d['device_name']) ?></strong></td>
                    <td><?= htmlspecialchars($d['device_type']) ?></td>
                    <td><?= htmlspecialchars($d['location']) ?></td>
                    <td>
                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Gerät wirklich löschen?');">
                            <input type="hidden" name="device_id" value="<?= $d['id'] ?>">
                            <button type="submit" name="delete_device"
                                style="background-color: var(--danger); padding: 4px 10px; font-size: 0.8em;">Löschen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Datenbank Verwalten</h3>
    <form method="POST" onsubmit="return confirm('Möchtest du wirklich alle Telemetrie-Daten löschen?');">
        <p>Zurücksetzen aller gesammelten Telemetrie-Einträge:</p>
        <button type="submit" name="clear_data" style="background-color: var(--danger); color: white;">Alle Messdaten
            löschen</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
