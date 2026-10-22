-- Nexus: named playlists that several TVs can share.
-- Until now every screen owned a private playlist. A shared playlist is edited
-- once (Signage > Playlists) and plays on every TV assigned to it; private
-- playlists keep working exactly as before. No existing row changes.
-- Runs on MySQL 8.0+ and MariaDB 10.6+.

SET NAMES utf8mb4;

ALTER TABLE signage_playlists
  ADD COLUMN is_shared TINYINT(1) NOT NULL DEFAULT 0 AFTER description,
  ADD KEY ix_signage_playlists_shared (is_shared, name);

INSERT INTO schema_migrations (version) VALUES ('014_signage_shared_playlists');
