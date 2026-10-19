-- Google device reports are a cache, separate from the school's inventory.
SET NAMES utf8mb4;

CREATE TABLE chromebook_google_cache (
  device_id INT UNSIGNED NOT NULL,
  connection_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  serial_number VARCHAR(64) NOT NULL,
  google_device_id VARCHAR(128) NULL,
  report_json LONGTEXT NULL,
  refreshed_at DATETIME NULL,
  attempted_at DATETIME NULL,
  error VARCHAR(500) NULL,
  PRIMARY KEY (device_id),
  CONSTRAINT fk_google_cache_device FOREIGN KEY (device_id) REFERENCES devices (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chromebook_google_moves (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id INT UNSIGNED NOT NULL,
  google_device_id VARCHAR(128) NOT NULL,
  from_ou VARCHAR(1000) NOT NULL,
  to_ou VARCHAR(1000) NOT NULL,
  requested_by INT UNSIGNED NOT NULL,
  requested_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  state VARCHAR(20) NOT NULL,
  PRIMARY KEY (id),
  KEY ix_google_moves_device (device_id, id),
  CONSTRAINT fk_google_move_device FOREIGN KEY (device_id) REFERENCES devices (id),
  CONSTRAINT fk_google_move_user FOREIGN KEY (requested_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE google_management_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_google_management_attempts (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('009_chromebook_google');
