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
        api_token VARCHAR(64) NOT NULL UNIQUE,
        -- Configuration specifying thresholds per metric (e.g., {"temperature": {"min_ok": 2, "max_ok": 6, "min_warn": 0, "max_warn": 8}})
        threshold_config JSON NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE
    telemetry_data (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        device_id INT NOT NULL,
        -- Dynamic Key-Value store for readings (e.g., {"temperature": 4.2, "humidity": 65.0})
        metrics JSON NOT NULL,
        status ENUM ('OK', 'WARNUNG', 'KRITISCH') DEFAULT 'OK',
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE,
        INDEX idx_device_time (device_id, recorded_at),
        INDEX idx_status (status)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Default Admin User (Password: admin)
INSERT INTO
    users (username, password_hash)
VALUES
    (
        'admin',
        '$2y$10$heuMG7aElXF5IiS4rCN49.T.smRQfhlCmVuoAh/SPpjQ6YA6qzZO6'
    );

-- Initial Seed Devices with custom thresholds and tokens
INSERT INTO
    devices (
        device_name,
        device_type,
        location,
        api_token,
        threshold_config
    )
VALUES
    (
        'Kühlraum Sensor 01',
        'Kühlraum-Monitor',
        'Hauptküche',
        'tok_kuehle_a1b2c3d4e5f6789012345678',
        '{"temperature": {"min_ok": 2.0, "max_ok": 6.0, "min_warn": 0.0, "max_warn": 8.0}}'
    ),
    (
        'Lagerhalle Temp & Volt',
        'Umweltsensor',
        'Sektor B',
        'tok_lager_b9876543210fedcba9876543',
        '{"temperature": {"min_ok": 18.0, "max_ok": 24.0, "min_warn": 15.0, "max_warn": 28.0}, "voltage": {"min_ok": 220.0, "max_ok": 240.0, "min_warn": 200.0, "max_warn": 250.0}}'
    ),
    (
        'Serverraum Alpha',
        'Klimamonitor',
        'Rack 04',
        'tok_server_f0e1d2c3b4a5987654321098',
        '{"temperature": {"min_ok": 18.0, "max_ok": 23.0, "min_warn": 15.0, "max_warn": 27.0}, "humidity": {"min_ok": 40.0, "max_ok": 60.0, "min_warn": 30.0, "max_warn": 70.0}}'
    );
