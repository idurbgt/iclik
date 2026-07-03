-- Skema database Iclik (MySQL)
-- Import: mysql -u root -p < sql/schema.sql

CREATE DATABASE IF NOT EXISTS iclik
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE iclik;

-- Daftar server yang dipantau
CREATE TABLE IF NOT EXISTS servers (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(100),
    ip_address  VARCHAR(45) NOT NULL,
    latitude    DECIMAL(10, 8) NOT NULL,
    longitude   DECIMAL(11, 8) NOT NULL,
    description TEXT,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Riwayat hasil ping
CREATE TABLE IF NOT EXISTS ping_logs (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    server_id     INT NOT NULL,
    status        ENUM('up', 'down') NOT NULL,
    response_time INT,                          -- milidetik, NULL bila down
    checked_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    INDEX idx_server_checked (server_id, checked_at)
) ENGINE=InnoDB;

-- Statistik & state machine per server (satu baris per server)
CREATE TABLE IF NOT EXISTS server_stats (
    server_id            INT PRIMARY KEY,
    last_status          ENUM('up', 'down', 'unknown') DEFAULT 'unknown',
    last_raw_status      ENUM('up', 'down') NULL,      -- hasil ping mentah terakhir
    last_check           DATETIME NULL,
    last_response_time   INT NULL,
    uptime_percentage    DECIMAL(5,2) DEFAULT 0,
    total_checks         INT DEFAULT 0,
    total_up             INT DEFAULT 0,
    total_down           INT DEFAULT 0,
    consecutive_failures INT DEFAULT 0,               -- untuk ambang batas alert
    down_since           DATETIME NULL,               -- untuk hitung durasi downtime
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Catatan insiden (transisi status untuk Incident Feed)
CREATE TABLE IF NOT EXISTS incidents (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    server_id        INT NOT NULL,
    server_name      VARCHAR(100),
    ip_address       VARCHAR(45),
    type             ENUM('down', 'up') NOT NULL,
    downtime_seconds INT NULL,                        -- terisi saat type='up' (recovery)
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_server (server_id)
) ENGINE=InnoDB;
