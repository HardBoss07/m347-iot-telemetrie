<?php
$host = "mysql";
$user = "meinuser";
$password = "meinpasswort";
$database = "meine_db";
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
 die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}
echo "<h1>Docker Apache + PHP + MySQL</h1>";
echo "<p>Verbindung zur MySQL-Datenbank erfolgreich!</p>";
$conn->close();
?>