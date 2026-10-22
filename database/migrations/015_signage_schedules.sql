-- Nexus: optional schedules that override a TV's normal playlist for a time window.
-- While a schedule is active, its shared playlist plays on the chosen TVs; when it
-- ends, each TV goes back to its assigned playlist. TVs without a schedule are
-- unaffected. Times are school wall-clock time (Asia/Jakarta), not UTC.
-- Runs on MySQL 8.0+ and MariaDB 10.6+.

SET NAMES utf8mb4;

CREATE TABLE signage_schedules (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(160) NOT NULL,
  playlist_id INT UNSIGNED NOT NULL,
  -- Weekdays it runs on: bit 1 = Monday ... bit 64 = Sunday (127 = every day).
  days       TINYINT UNSIGNED NOT NULL DEFAULT 127,
  start_time TIME NOT NULL,
  end_time   TIME NOT NULL,
  -- Optional date limits (inclusive). Both the same day = a one-off event.
  start_date DATE NULL,
  end_date   DATE NULL,
  enabled    TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_signage_schedules_playlist (playlist_id),
  CONSTRAINT fk_signage_schedule_playlist FOREIGN KEY (playlist_id)
    REFERENCES signage_playlists (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE signage_schedule_screens (
  schedule_id INT UNSIGNED NOT NULL,
  screen_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (schedule_id, screen_id),
  KEY ix_signage_schedule_screens_screen (screen_id),
  CONSTRAINT fk_signage_schedule_screens_schedule FOREIGN KEY (schedule_id)
    REFERENCES signage_schedules (id) ON DELETE CASCADE,
  CONSTRAINT fk_signage_schedule_screens_screen FOREIGN KEY (screen_id)
    REFERENCES signage_screens (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('015_signage_schedules');
