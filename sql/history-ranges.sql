CREATE TABLE IF NOT EXISTS history_rollups (
 kind CHAR(1) NOT NULL,
 source_id BIGINT UNSIGNED NOT NULL,
 target_id SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 period_seconds INT UNSIGNED NOT NULL,
 bucket BIGINT NOT NULL,
 samples BIGINT UNSIGNED NOT NULL,
 online_samples BIGINT UNSIGNED NOT NULL,
 losses BIGINT UNSIGNED NOT NULL,
 unknowns BIGINT UNSIGNED NOT NULL,
 weight DOUBLE NOT NULL,
 sum_a DOUBLE NOT NULL,
 sum_b DOUBLE NOT NULL,
 max_a DOUBLE NULL,
 max_b DOUBLE NULL,
 first_ts BIGINT NOT NULL,
 last_ts BIGINT NOT NULL,
 gap_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(kind,source_id,target_id,period_seconds,bucket)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS traffic_interfaces (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 router_id INT UNSIGNED NOT NULL,
 port_key VARCHAR(190) NOT NULL,
 name VARCHAR(190) NOT NULL,
 label VARCHAR(190) NOT NULL,
 type VARCHAR(60) NOT NULL DEFAULT '',
 selected TINYINT NOT NULL DEFAULT 0,
 last_seen BIGINT NOT NULL DEFAULT 0,
 last_probe_at BIGINT NOT NULL DEFAULT 0,
 last_status VARCHAR(7) NOT NULL DEFAULT 'UNKNOWN',
 last_rx_bps DOUBLE NULL,
 last_tx_bps DOUBLE NULL,
 rx_counter VARCHAR(20) NULL,
 tx_counter VARCHAR(20) NULL,
 counter_at BIGINT NOT NULL DEFAULT 0,
 uptime_seconds BIGINT UNSIGNED NULL,
 open_outage_id BIGINT UNSIGNED NULL,
 last_error VARCHAR(120) NOT NULL DEFAULT '',
 UNIQUE KEY source_port(router_id,port_key),
 INDEX chosen(router_id,selected)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS traffic_samples (
 interface_id BIGINT UNSIGNED NOT NULL,
 ts BIGINT NOT NULL,
 status VARCHAR(7) NOT NULL,
 rx_bps DOUBLE NULL,
 tx_bps DOUBLE NULL,
 duration INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(interface_id,ts)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS traffic_outages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 interface_id BIGINT UNSIGNED NOT NULL,
 started_at BIGINT NOT NULL,
 recovered_at BIGINT NULL,
 last_offline_at BIGINT NOT NULL,
 partial_start TINYINT NOT NULL DEFAULT 0,
 uncertain TINYINT NOT NULL DEFAULT 0,
 INDEX interface_start(interface_id,started_at)
) ENGINE=InnoDB;
