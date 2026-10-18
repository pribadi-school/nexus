-- Nexus Signage keeps its media on the hosting account, outside the web root.
-- Player access is scoped to one screen through a random, revocable token.
SET NAMES utf8mb4;

CREATE TABLE signage_content (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(160) NOT NULL,
  type              VARCHAR(20) NOT NULL,
  source_url        VARCHAR(2048) NULL,
  stored_filename   VARCHAR(100) NULL,
  original_filename VARCHAR(255) NULL,
  mime_type         VARCHAR(100) NULL,
  size_bytes        BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_signage_content_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE signage_playlists (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(160) NOT NULL,
  description VARCHAR(500) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_signage_playlists_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE signage_playlist_items (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  playlist_id      INT UNSIGNED NOT NULL,
  content_id       INT UNSIGNED NOT NULL,
  sort_order       INT NOT NULL DEFAULT 0,
  duration_seconds INT UNSIGNED NOT NULL DEFAULT 30,
  PRIMARY KEY (id),
  KEY ix_signage_items_order (playlist_id, sort_order, id),
  KEY ix_signage_items_content (content_id),
  CONSTRAINT fk_signage_item_playlist FOREIGN KEY (playlist_id)
    REFERENCES signage_playlists (id) ON DELETE CASCADE,
  CONSTRAINT fk_signage_item_content FOREIGN KEY (content_id)
    REFERENCES signage_content (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE signage_screens (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(160) NOT NULL,
  location        VARCHAR(160) NULL,
  playlist_id     INT UNSIGNED NULL,
  token           CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  orientation     VARCHAR(20) NOT NULL DEFAULT 'landscape',
  fit             VARCHAR(20) NOT NULL DEFAULT 'contain',
  muted           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  enabled         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  last_seen_at    DATETIME NULL,
  last_content_id INT UNSIGNED NULL,
  last_error      VARCHAR(500) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_signage_screen_token (token),
  KEY ix_signage_screen_playlist (playlist_id),
  CONSTRAINT fk_signage_screen_playlist FOREIGN KEY (playlist_id)
    REFERENCES signage_playlists (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('006_signage');
