<?php
session_start();
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $pdo = getDBConnection();

        // Tabellen-Fallback für bestehende Datenbank-Volumen
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci");

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        // Fallback-Seeder falls die Tabelle leer war
        if (!$user && $username === 'admin' && $password === 'admin') {
            $hash = password_hash('admin', PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES ('admin', :hash)");
            $insert->execute([':hash' => $hash]);
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Ungültiger Benutzername oder Passwort!';
        }
    } else {
        $error = 'Bitte fülle alle Felder aus!';
    }
}

include 'includes/header.php';
?>

<div class="card" style="max-width: 400px; margin: 40px auto;">
    <h2>Admin Login</h2>

    <?php if ($error): ?>
        <div class="alert"
            style="background-color: var(--danger); color: white; margin-bottom: 15px; padding: 10px; border-radius: 4px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div style="margin-bottom: 15px;">
            <label for="username">Benutzername:</label><br>
            <input type="text" id="username" name="username" required
                style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border-color); background: #0f172a; color: #fff; margin-top: 5px;">
        </div>

        <div style="margin-bottom: 20px;">
            <label for="password">Passwort:</label><br>
            <input type="password" id="password" name="password" required
                style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid var(--border-color); background: #0f172a; color: #fff; margin-top: 5px;">
        </div>

        <button type="submit" style="width: 100%;">Anmelden</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
