-- Nexus: control Android TVs over ADB through a helper PC on the school network.
-- The website cannot reach TVs (private addresses), so signed-in people queue a
-- fixed action here and the helper (bin/tv-agent.ps1) collects and runs it.
-- TV addresses and serials live only in the helper's own config file.
-- Runs on MySQL 8.0+ and MariaDB 10.6+.

SET NAMES utf8mb4;

CREATE TABLE signage_tv_commands (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  screen_id           INT UNSIGNED NOT NULL,
  action              VARCHAR(20) NOT NULL,
  status              VARCHAR(20) NOT NULL DEFAULT 'queued',
  result              VARCHAR(500) NULL,
  screenshot_filename VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
  requested_by        INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL,
  started_at          DATETIME NULL,
  finished_at         DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_signage_tv_commands_queue (status, id),
  KEY ix_signage_tv_commands_screen (screen_id, id),
  KEY ix_signage_tv_commands_user (requested_by),
  CONSTRAINT fk_signage_tv_command_screen FOREIGN KEY (screen_id)
    REFERENCES signage_screens (id) ON DELETE CASCADE,
  CONSTRAINT fk_signage_tv_command_user FOREIGN KEY (requested_by)
    REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per helper PC: when it last checked in and which screens it controls.
CREATE TABLE signage_tv_agents (
  name         VARCHAR(60) NOT NULL,
  screen_ids   VARCHAR(500) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  version      VARCHAR(20) NOT NULL DEFAULT '',
  last_seen_at DATETIME NOT NULL,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('013_signage_tv_control');
