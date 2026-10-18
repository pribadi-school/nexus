-- Short-lived Android TV pairing requests. Never store a TV's device secret.
SET NAMES utf8mb4;

CREATE TABLE signage_pairing_ips (
  ip_hash         CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  last_request_at DATETIME NOT NULL,
  PRIMARY KEY (ip_hash),
  KEY ix_signage_pairing_ip_age (last_request_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE signage_pairings (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_key_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  code            CHAR(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  device_name     VARCHAR(160) NOT NULL,
  ip_hash         CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  screen_id       INT UNSIGNED NULL,
  paired_token    CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_at      DATETIME NOT NULL,
  expires_at      DATETIME NOT NULL,
  paired_at       DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_signage_pairing_device (device_key_hash),
  UNIQUE KEY uq_signage_pairing_code (code),
  KEY ix_signage_pairing_expiry (expires_at),
  KEY ix_signage_pairing_rate (ip_hash, created_at),
  KEY ix_signage_pairing_screen (screen_id),
  CONSTRAINT fk_signage_pairing_screen FOREIGN KEY (screen_id)
    REFERENCES signage_screens (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('007_signage_pairing');
