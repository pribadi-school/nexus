-- Editable CCTV locations. Only the groups confirmed by the school are
-- assigned here; other cameras can be placed from their edit forms.
SET NAMES utf8mb4;

CREATE TABLE cctv_locations (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(80) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cctv_locations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cctv_locations (name, sort_order) VALUES
  ('1st floor', 10), ('2nd floor', 20), ('3rd floor', 30), ('4th floor', 40);

ALTER TABLE cctv_cameras
  ADD COLUMN location_id INT UNSIGNED NULL AFTER label,
  ADD KEY ix_cctv_location (location_id),
  ADD CONSTRAINT fk_cctv_location FOREIGN KEY (location_id) REFERENCES cctv_locations (id);

UPDATE cctv_cameras c JOIN devices d ON d.id = c.device_id
SET c.location_id = (SELECT id FROM cctv_locations WHERE name = '2nd floor')
WHERE d.category = 'cctv' AND d.deleted_at IS NULL
  AND c.label REGEXP '(^|[[:space:]])SD([[:space:]]|$)';

UPDATE cctv_cameras c JOIN devices d ON d.id = c.device_id
SET c.location_id = (SELECT id FROM cctv_locations WHERE name = '3rd floor')
WHERE d.category = 'cctv' AND d.deleted_at IS NULL
  AND c.label REGEXP '(^|[[:space:]])(SMP|SMA)([[:space:]]|$)';

UPDATE cctv_cameras c JOIN devices d ON d.id = c.device_id
SET c.location_id = (SELECT id FROM cctv_locations WHERE name = '4th floor')
WHERE d.category = 'cctv' AND d.deleted_at IS NULL
  AND c.label REGEXP '(^|[[:space:]])AVR([[:space:]]|$)';

INSERT INTO schema_migrations (version) VALUES ('005_cctv_locations');
