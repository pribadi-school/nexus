-- Nexus: a photo of each TV, shown on the Screens page.
-- The image file lives outside the website root in storage/signage/screens/;
-- this column holds only its generated file name (NULL = no photo yet).
-- Runs on MySQL 8.0+ and MariaDB 10.6+.

SET NAMES utf8mb4;

ALTER TABLE signage_screens
  ADD COLUMN photo_filename VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER enabled;

INSERT INTO schema_migrations (version) VALUES ('008_signage_screen_photo');
