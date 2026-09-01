# IoT Telemetrie

Ein webbasiertes IoT-Telemetrie Dashboard zur Live-Visualisierung und Simulation von IoT-Messdaten.

## 🚀 Quick Start

1. Docker-Container bauen und starten:

```bash
docker compose up -d --build
```

2. Datenbank-Schema importieren:

- **phpMyAdmin** öffnen: [http://localhost:8081](http://localhost:8081)
- Login nutzen (siehe unten) und die Datei `www/schema.sql` in die Datenbank `iot_telemetrie` importieren.

## 🔑 Zugangsdaten & Services

| Service           | URL / Host                                                         | Benutzername / Server | Passwort | Beschreibung         |
| ----------------- | ------------------------------------------------------------------ | --------------------- | -------- | -------------------- |
| **Web Dashboard** | [http://localhost:8080](http://localhost:8080)                     | -                     | -        | Hauptanwendung / UI  |
| **Admin Panel**   | [http://localhost:8080/admin.php](http://localhost:8080/admin.php) | -                     | -        | Testdaten generieren |
| **phpMyAdmin**    | [http://localhost:8081](http://localhost:8081)                     | Server: `mysql`<br>   |

<br>User: `meinuser` | `meinpasswort` | DB Administration |
| **Portainer** | [https://localhost:9443](https://localhost:9443) | _(Beim 1. Aufruf festlegen)_ | - | Container Mgmt |
| **MySQL DB** | `mysql:3306` _(intern)_ | `meinuser` | `meinpasswort` | Hauptdatenbank (`iot_telemetrie`) |
| **MySQL Root** | `mysql:3306` _(intern)_ | `root` | `rootpasswort` | DB Superuser |

## 📡 API Nutzung (Beispiel für externe IoT-Geräte)

Datensätze können manuell via HTTP POST gesendet werden:

```bash
curl -X POST http://localhost:8080/api/log.php \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": 1,
    "temperature": 23.4,
    "humidity": 55.0,
    "status": "OK"
  }'
```
