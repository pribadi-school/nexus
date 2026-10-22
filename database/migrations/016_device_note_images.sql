SET NAMES utf8mb4;
ALTER TABLE device_notes
  ADD image_filename VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER meta;

INSERT INTO schema_migrations (version) VALUES ('016_device_note_images');
