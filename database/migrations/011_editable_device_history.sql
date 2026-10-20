SET NAMES utf8mb4;
ALTER TABLE device_notes
  ADD edited_at DATETIME NULL,
  ADD edited_by INT UNSIGNED NULL,
  ADD CONSTRAINT fk_note_edited_by FOREIGN KEY (edited_by) REFERENCES users(id);

CREATE TABLE device_note_revisions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  note_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  meta LONGTEXT NULL,
  edited_by INT UNSIGNED NULL,
  edited_at DATETIME NOT NULL,
  KEY ix_note_revisions (note_id, id),
  CONSTRAINT fk_revision_note FOREIGN KEY (note_id) REFERENCES device_notes(id),
  CONSTRAINT fk_revision_editor FOREIGN KEY (edited_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('011_editable_device_history');
