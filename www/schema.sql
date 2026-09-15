SET
    NAMES utf8mb4;

USE iot_telemetrie;

ALTER DATABASE iot_telemetrie CHARACTER
SET
    utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP TABLE IF EXISTS telemetry_data;

DROP TABLE IF EXISTS devices;

DROP TABLE IF EXISTS users;

CREATE TABLE
    users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE
    devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_name VARCHAR(100) NOT NULL,
        device_type VARCHAR(50) NOT NULL,
        location VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE
    telemetry_data (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        device_id INT NOT NULL,
        temperature DECIMAL(5, 2) NOT NULL,
        humidity DECIMAL(5, 2) NOT NULL,
        status ENUM ('OK', 'WARNUNG', 'KRITISCH') DEFAULT 'OK',
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO users (username, password_hash)
VALUES ('admin', '$2y$10$heuMG7aElXF5IiS4rCN49.T.smRQfhlCmVuoAh/SPpjQ6YA6qzZO6');

INSERT INTO
    devices (device_name, device_type, location)
VALUES
    (
        'Sensor-Alpha-01',
        'Temperatur & Feuchtigkeit',
        'Serverraum A'
    ),
    ('Sensor-Beta-02', 'Umweltsensor', 'Lagerhalle 3'),
    (
        'Sensor-Gamma-03',
        'Kühlraum-Monitor',
        'Küche Hauptgebäude'
    );