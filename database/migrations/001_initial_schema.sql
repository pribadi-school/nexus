-- Nexus: initial schema
--
-- Import into an EMPTY database (cPanel > phpMyAdmin > select the database >
-- Import). Runs on MySQL 8.0+ and MariaDB 10.6+.
--
-- Conventions
--   * Every device has one row in `devices` (shared: status, serial, model,
--     notes) plus one row in its category table (studytouch_boards,
--     cctv_cameras, chromebooks). A new category = a new detail table.
--   * Code-like values (category, status, kind, role, access) are short
--     VARCHARs checked in PHP, so adding a value needs no ALTER TABLE.
--   * All DATETIME values are UTC. The app converts to Asia/Jakarta for display.
--   * Deleting a device sets deleted_at; its notes stay.

SET NAMES utf8mb4;

CREATE TABLE schema_migrations (
  version     VARCHAR(100) NOT NULL,
  applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Everyone signs in with one shared password (APP_PASSWORD in .env) plus
-- their name. Each name typed at sign-in gets a row here, so notes and status
-- changes record who made them. Names match case-insensitively.
CREATE TABLE users (
  id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name           VARCHAR(60)   NOT NULL,
  last_login_at  DATETIME      NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed sign-ins per IP address, to slow down password guessing.
CREATE TABLE login_attempts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip            VARCHAR(45)  NOT NULL,
  attempted_at  DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY ix_login_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editable lists ------------------------------------------------------------

CREATE TABLE rooms (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(80)  NOT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rooms_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cctv_areas (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(80)  NOT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cctv_areas_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE classes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(80)  NOT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_classes_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Devices -------------------------------------------------------------------

CREATE TABLE devices (
  id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  category           VARCHAR(20)   NOT NULL,                -- studytouch | cctv | chromebook
  serial_number      VARCHAR(64)   NULL,
  model              VARCHAR(100)  NULL,
  status             VARCHAR(20)   NOT NULL DEFAULT 'ok',   -- ok | problem
  status_changed_at  DATETIME      NULL,
  last_checked_at    DATETIME      NULL,
  created_by         INT UNSIGNED  NULL,
  updated_by         INT UNSIGNED  NULL,
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at         DATETIME      NULL,
  -- Serial numbers are unique per category among devices that are not deleted.
  active_serial      VARCHAR(64)   AS (IF(deleted_at IS NULL, serial_number, NULL)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_devices_active_serial (category, active_serial),
  KEY ix_devices_category_status (category, deleted_at, status),
  KEY ix_devices_updated (updated_at),
  CONSTRAINT fk_devices_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_devices_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE studytouch_boards (
  device_id         INT UNSIGNED NOT NULL,
  room_id           INT UNSIGNED NOT NULL,
  firmware_version  VARCHAR(40)  NULL,
  PRIMARY KEY (device_id),
  KEY ix_studytouch_room (room_id),
  CONSTRAINT fk_studytouch_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE,
  CONSTRAINT fk_studytouch_room   FOREIGN KEY (room_id)   REFERENCES rooms (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cctv_cameras (
  device_id   INT UNSIGNED      NOT NULL,
  area_id     INT UNSIGNED      NOT NULL,
  label       VARCHAR(100)      NOT NULL,
  location    VARCHAR(160)      NULL,
  ip_address  VARCHAR(45)       NULL,
  sd_card_gb  SMALLINT UNSIGNED NULL,
  PRIMARY KEY (device_id),
  KEY ix_cctv_area (area_id),
  CONSTRAINT fk_cctv_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE,
  CONSTRAINT fk_cctv_area   FOREIGN KEY (area_id)   REFERENCES cctv_areas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chromebooks (
  device_id     INT UNSIGNED NOT NULL,
  student_name  VARCHAR(120) NULL,
  class_id      INT UNSIGNED NULL,
  assigned_at   DATETIME     NULL,
  PRIMARY KEY (device_id),
  KEY ix_chromebooks_student (student_name),
  KEY ix_chromebooks_class (class_id),
  CONSTRAINT fk_chromebooks_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE,
  CONSTRAINT fk_chromebooks_class  FOREIGN KEY (class_id)  REFERENCES classes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per assignment. The open row (ended_at IS NULL) matches the current
-- student in `chromebooks`. class_name is copied so history survives class
-- renames and the yearly move up.
CREATE TABLE chromebook_assignments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id     INT UNSIGNED NOT NULL,
  student_name  VARCHAR(120) NOT NULL,
  class_name    VARCHAR(80)  NULL,
  assigned_at   DATETIME     NOT NULL,
  ended_at      DATETIME     NULL,
  assigned_by   INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_assignments_device (device_id, assigned_at),
  CONSTRAINT fk_assignments_device FOREIGN KEY (device_id)   REFERENCES devices (id) ON DELETE CASCADE,
  CONSTRAINT fk_assignments_user   FOREIGN KEY (assigned_by) REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The notes log, shared by every device type. Append-only.
CREATE TABLE device_notes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id   INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NULL,
  kind        VARCHAR(20)  NOT NULL DEFAULT 'report',  -- report | problem | fix | firmware | status | assignment
  body        TEXT         NOT NULL,
  meta        JSON         NULL,                       -- e.g. {"from":"ok","to":"problem"}
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_notes_device (device_id, created_at),
  KEY ix_notes_created (created_at, id),
  CONSTRAINT fk_notes_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE,
  CONSTRAINT fk_notes_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starting data -------------------------------------------------------------

INSERT INTO classes (name, sort_order) VALUES
  ('7A', 10), ('7B', 20), ('7C', 30),
  ('8A', 40), ('8B', 50),
  ('9A', 60), ('9B', 70),
  ('10A', 80), ('10B', 90),
  ('11A', 100), ('11B', 110),
  ('12A', 120), ('12B', 130);

INSERT INTO schema_migrations (version) VALUES ('001_initial_schema');
