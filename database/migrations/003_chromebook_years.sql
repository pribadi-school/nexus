-- Nexus: Chromebooks by academic year.
--
-- Chromebooks come in one batch per academic year (2024, 2025, 2026, ...)
-- and carry an asset ID such as 2026-05. Each one has a student with a
-- school email; classes are not tracked any more, so the class list goes.
-- The history keeps its class_name column for rows written before this.

SET NAMES utf8mb4;

ALTER TABLE chromebooks
  ADD COLUMN academic_year SMALLINT UNSIGNED NULL AFTER device_id,
  ADD COLUMN asset_id      VARCHAR(20)       NULL AFTER academic_year,
  ADD COLUMN student_email VARCHAR(160)      NULL AFTER student_name;

-- Chromebooks added before this get the year they were added in.
UPDATE chromebooks c JOIN devices d ON d.id = c.device_id
SET c.academic_year = YEAR(d.created_at);

ALTER TABLE chromebooks
  MODIFY academic_year SMALLINT UNSIGNED NOT NULL,
  ADD KEY ix_chromebooks_year (academic_year, asset_id),
  DROP FOREIGN KEY fk_chromebooks_class;

ALTER TABLE chromebooks
  DROP KEY ix_chromebooks_class,
  DROP COLUMN class_id;

DROP TABLE classes;

INSERT INTO schema_migrations (version) VALUES ('003_chromebook_years');
