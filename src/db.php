<?php
declare(strict_types=1);
require_once __DIR__.'/../config/config.php';

function db(): mysqli {
    static $db;
    if ($db instanceof mysqli) return $db;

    // Connect without selecting a database first so the application can recover
    // automatically when the database has not been created yet.
    $server = @new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($server->connect_errno) {
        throw new RuntimeException('MySQL tidak dapat dihubungkan: '.$server->connect_error);
    }
    $server->set_charset('utf8mb4');
    if (!$server->query("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        throw new RuntimeException('Gagal membuat database: '.$server->error);
    }
    $server->close();

    $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_errno) {
        throw new RuntimeException('Database belum siap: '.$db->connect_error);
    }
    $db->set_charset('utf8mb4');

    // Auto-heal tables for installs upgraded from an older version.
    $queries = [
        "CREATE TABLE IF NOT EXISTS routers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            host VARCHAR(255) NOT NULL,
            port INT UNSIGNED NOT NULL DEFAULT 8728,
            username VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            use_ssl TINYINT(1) NOT NULL DEFAULT 0,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_enabled(enabled)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS router_interfaces (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            router_id INT UNSIGNED NOT NULL,
            interface_name VARCHAR(120) NOT NULL,
            display_name VARCHAR(120) NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_router_interface(router_id,interface_name),
            CONSTRAINT fk_router_interfaces_router FOREIGN KEY(router_id) REFERENCES routers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS ping_targets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            host VARCHAR(255) NOT NULL,
            icon VARCHAR(40) NOT NULL DEFAULT 'bi-hdd-network',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ping_enabled(enabled)
        ) ENGINE=InnoDB",
    ];
    foreach ($queries as $q) {
        if (!$db->query($q)) throw new RuntimeException('Gagal menyiapkan database: '.$db->error);
    }

    // Upgrade/migrate routers table from older installations.
    // CREATE TABLE IF NOT EXISTS does not alter an existing table, so older
    // databases may be missing columns such as username/password.
    $migrations = [
        'name'     => "ALTER TABLE routers ADD COLUMN name VARCHAR(100) NOT NULL DEFAULT '' AFTER id",
        'host'     => "ALTER TABLE routers ADD COLUMN host VARCHAR(255) NOT NULL DEFAULT '' AFTER name",
        'port'     => "ALTER TABLE routers ADD COLUMN port INT UNSIGNED NOT NULL DEFAULT 8728 AFTER host",
        'username' => "ALTER TABLE routers ADD COLUMN username VARCHAR(100) NOT NULL DEFAULT 'admin' AFTER port",
        'password' => "ALTER TABLE routers ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT '' AFTER username",
        'use_ssl'  => "ALTER TABLE routers ADD COLUMN use_ssl TINYINT(1) NOT NULL DEFAULT 0 AFTER password",
        'enabled'  => "ALTER TABLE routers ADD COLUMN enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER use_ssl",
        'created_at' => "ALTER TABLE routers ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER enabled",
        'updated_at' => "ALTER TABLE routers ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    ];
    foreach ($migrations as $column => $alterSql) {
        $escDb = $db->real_escape_string(DB_NAME);
        $escCol = $db->real_escape_string($column);
        $check = $db->query(
            "SELECT COUNT(*) AS c FROM information_schema.COLUMNS " .
            "WHERE TABLE_SCHEMA='{$escDb}' AND TABLE_NAME='routers' AND COLUMN_NAME='{$escCol}'"
        );
        if (!$check) throw new RuntimeException('Gagal memeriksa struktur tabel routers: '.$db->error);
        $exists = (int)$check->fetch_assoc()['c'] > 0;
        $check->free();
        if (!$exists && !$db->query($alterSql)) {
            throw new RuntimeException("Gagal memperbarui tabel routers (kolom {$column}): ".$db->error);
        }
    }

    return $db;
}
