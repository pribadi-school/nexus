-- School bell scheduler: two independent zones (floors), each with its own
-- profiles, schedule, holidays and volume. The bell PC downloads this data
-- and rings from its own copy, so bells still work if the internet is down.
-- Bell times are school wall-clock times (Asia/Jakarta); they are not UTC.
SET NAMES utf8mb4;

CREATE TABLE bell_zones (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(60) NOT NULL,
  color             VARCHAR(12) CHARACTER SET ascii NOT NULL DEFAULT 'blue',
  volume            TINYINT UNSIGNED NOT NULL DEFAULT 100,
  active_profile_id INT UNSIGNED NULL,
  sort_order        INT UNSIGNED NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL,
  updated_at        DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bell_zone_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bell_profiles (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone_id    INT UNSIGNED NOT NULL,
  name       VARCHAR(60) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bell_profile_name (zone_id, name),
  CONSTRAINT fk_bell_profile_zone FOREIGN KEY (zone_id) REFERENCES bell_zones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- days: ISO weekday digits, 1 = Monday ... 7 = Sunday (e.g. "12345").
-- sound_ref: "builtin:<key>" or "sound:<bell_sounds.id>".
CREATE TABLE bell_entries (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone_id     INT UNSIGNED NOT NULL,
  profile_id  INT UNSIGNED NOT NULL,
  ring_time   TIME NOT NULL,
  days        VARCHAR(7) CHARACTER SET ascii NOT NULL,
  sound_ref   VARCHAR(40) CHARACTER SET ascii NOT NULL,
  label       VARCHAR(120) NOT NULL DEFAULT '',
  enabled     TINYINT(1) NOT NULL DEFAULT 1,
  both_floors TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL,
  updated_at  DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_bell_entry_profile (profile_id, ring_time),
  KEY ix_bell_entry_zone (zone_id),
  CONSTRAINT fk_bell_entry_zone FOREIGN KEY (zone_id) REFERENCES bell_zones (id) ON DELETE CASCADE,
  CONSTRAINT fk_bell_entry_profile FOREIGN KEY (profile_id) REFERENCES bell_profiles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bell_holidays (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone_id      INT UNSIGNED NOT NULL,
  holiday_date DATE NOT NULL,
  label        VARCHAR(120) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_bell_holiday (zone_id, holiday_date),
  CONSTRAINT fk_bell_holiday_zone FOREIGN KEY (zone_id) REFERENCES bell_zones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uploaded bell sounds (MP3 or WAV). Files live in storage/bell/sounds/.
CREATE TABLE bell_sounds (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(80) NOT NULL,
  stored_filename   VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  original_filename VARCHAR(200) NOT NULL,
  mime_type         VARCHAR(60) CHARACTER SET ascii NOT NULL,
  size_bytes        INT UNSIGNED NOT NULL,
  sha256            CHAR(64) CHARACTER SET ascii NOT NULL,
  created_by        INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bell_sound_name (name),
  CONSTRAINT fk_bell_sound_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The one bell PC: a revocable connection key (only its hash is stored),
-- when it last checked in, and what it reported about its sound devices.
CREATE TABLE bell_pc (
  id               TINYINT UNSIGNED NOT NULL,
  token_hash       CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  token_created_at DATETIME NULL,
  last_seen_at     DATETIME NULL,
  pc_name          VARCHAR(80) NULL,
  app_version      VARCHAR(20) NULL,
  status           LONGTEXT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What the bell PC rang, skipped or failed. occurred_at is the PC's local time.
CREATE TABLE bell_events (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone_id     INT UNSIGNED NULL,
  occurred_at DATETIME NOT NULL,
  kind        VARCHAR(12) CHARACTER SET ascii NOT NULL,
  message     VARCHAR(300) NOT NULL,
  received_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_bell_event_time (occurred_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bell_pc (id) VALUES (1);

INSERT INTO bell_zones (name, color, volume, sort_order, created_at, updated_at) VALUES
  ('Lantai 2', 'blue', 100, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  ('Lantai 3', 'orange', 100, 2, UTC_TIMESTAMP(), UTC_TIMESTAMP());

INSERT INTO bell_profiles (zone_id, name, sort_order, created_at)
SELECT z.id, p.name, p.sort_order, UTC_TIMESTAMP()
FROM bell_zones z
JOIN (SELECT 'Normal' AS name, 1 AS sort_order UNION ALL SELECT 'Jumat', 2
      UNION ALL SELECT 'Ujian', 3 UNION ALL SELECT 'Pendek', 4) p;

UPDATE bell_zones z SET active_profile_id =
  (SELECT p.id FROM bell_profiles p WHERE p.zone_id = z.id AND p.name = 'Normal');

INSERT INTO schema_migrations (version) VALUES ('017_bell_scheduler');
