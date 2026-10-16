-- Nexus: sample data, so the UI has something to show.
--
-- For local development and a first look on the server. Every person and
-- device here is made up. StudyTouch boards are not: they are the school's
-- real ones, added by migration 002. Import AFTER the migrations. Remove it all again
-- with database/seeds/clear_sample_data.sql before entering real data.
-- Times are relative to the moment you import, so the feed looks recent.

SET NAMES utf8mb4;

-- Sample people (as if they had signed in with these names) -----------------
INSERT INTO users (name, last_login_at, created_at) VALUES
  ('Rina Wulandari', UTC_TIMESTAMP() - INTERVAL 1 HOUR, UTC_TIMESTAMP() - INTERVAL 40 DAY),
  ('Budi Santoso',   UTC_TIMESTAMP() - INTERVAL 2 HOUR, UTC_TIMESTAMP() - INTERVAL 40 DAY);
SET @rina = (SELECT id FROM users WHERE name = 'Rina Wulandari');
SET @budi = (SELECT id FROM users WHERE name = 'Budi Santoso');

-- Lists ------------------------------------------------------------------------
INSERT INTO cctv_areas (name, sort_order) VALUES
  ('Lobby', 10), ('Parking', 20), ('Floor 1', 30), ('Floor 2', 40), ('Floor 3', 50);

-- CCTV cameras ------------------------------------------------------------------
INSERT INTO devices (category, serial_number, model, status, status_changed_at, last_checked_at, created_by, updated_by, created_at, updated_at)
VALUES ('cctv', NULL, 'Hikvision DS-2CD1043G2-I', 'ok', UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY, @budi, @budi, UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO cctv_cameras (device_id, area_id, label, location, ip_address, sd_card_gb) VALUES (@d, (SELECT id FROM cctv_areas WHERE name = 'Lobby'), 'CAM-01 Main entrance', 'Above the main door, facing reception', '192.168.10.21', 128);

INSERT INTO devices (category, serial_number, model, status, status_changed_at, last_checked_at, created_by, updated_by, created_at, updated_at)
VALUES ('cctv', NULL, 'Hikvision DS-2CD1043G2-I', 'ok', UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY, @budi, @budi, UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO cctv_cameras (device_id, area_id, label, location, ip_address, sd_card_gb) VALUES (@d, (SELECT id FROM cctv_areas WHERE name = 'Lobby'), 'CAM-02 Reception', 'Corner behind the reception desk', '192.168.10.22', 128);

INSERT INTO devices (category, serial_number, model, status, status_changed_at, last_checked_at, created_by, updated_by, created_at, updated_at)
VALUES ('cctv', NULL, 'Dahua IPC-HFW2441S-S', 'problem', UTC_TIMESTAMP() - INTERVAL 1 DAY, UTC_TIMESTAMP() - INTERVAL 1 DAY, @budi, @budi, UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 1 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO cctv_cameras (device_id, area_id, label, location, ip_address, sd_card_gb) VALUES (@d, (SELECT id FROM cctv_areas WHERE name = 'Parking'), 'CAM-05 Gate', 'Pole at the parking gate, facing the street', '192.168.10.25', 256);
INSERT INTO device_notes (device_id, user_id, kind, body, meta, created_at) VALUES
  (@d, @budi, 'problem', 'Image is blurry at night. Lens looks fogged up after the rain.', NULL, UTC_TIMESTAMP() - INTERVAL 1 DAY - INTERVAL 1 MINUTE),
  (@d, @budi, 'status', 'Status changed to Has problem', '{"from":"ok","to":"problem"}', UTC_TIMESTAMP() - INTERVAL 1 DAY);

INSERT INTO devices (category, serial_number, model, status, status_changed_at, last_checked_at, created_by, updated_by, created_at, updated_at)
VALUES ('cctv', NULL, 'Dahua IPC-HFW2441S-S', 'ok', UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY, @budi, @budi, UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO cctv_cameras (device_id, area_id, label, location, ip_address, sd_card_gb) VALUES (@d, (SELECT id FROM cctv_areas WHERE name = 'Parking'), 'CAM-06 Drop-off', 'Canopy over the drop-off lane', NULL, 256);

INSERT INTO devices (category, serial_number, model, status, status_changed_at, last_checked_at, created_by, updated_by, created_at, updated_at)
VALUES ('cctv', NULL, 'Hikvision DS-2CD1143G2-I', 'ok', UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY, @budi, @budi, UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO cctv_cameras (device_id, area_id, label, location, ip_address, sd_card_gb) VALUES (@d, (SELECT id FROM cctv_areas WHERE name = 'Floor 1'), 'CAM-11 Corridor east', 'East corridor ceiling, facing the stairs', '192.168.10.31', 64);

INSERT INTO devices (category, serial_number, model, status, status_changed_at, last_checked_at, created_by, updated_by, created_at, updated_at)
VALUES ('cctv', NULL, 'Hikvision DS-2CD1143G2-I', 'problem', UTC_TIMESTAMP() - INTERVAL 6 HOUR, UTC_TIMESTAMP() - INTERVAL 6 HOUR, @rina, @rina, UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 6 HOUR);
SET @d = LAST_INSERT_ID();
INSERT INTO cctv_cameras (device_id, area_id, label, location, ip_address, sd_card_gb) VALUES (@d, (SELECT id FROM cctv_areas WHERE name = 'Floor 2'), 'CAM-21 Corridor west', 'West corridor ceiling, outside Classroom 8A', '192.168.10.41', 64);
INSERT INTO device_notes (device_id, user_id, kind, body, meta, created_at) VALUES
  (@d, @rina, 'problem', 'Recorder shows "SD card error". Footage stops two days back.', NULL, UTC_TIMESTAMP() - INTERVAL 6 HOUR - INTERVAL 1 MINUTE),
  (@d, @rina, 'status', 'Status changed to Has problem', '{"from":"ok","to":"problem"}', UTC_TIMESTAMP() - INTERVAL 6 HOUR);

INSERT INTO devices (category, serial_number, model, status, status_changed_at, last_checked_at, created_by, updated_by, created_at, updated_at)
VALUES ('cctv', NULL, 'Hikvision DS-2CD1143G2-I', 'ok', UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY, @budi, @budi, UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO cctv_cameras (device_id, area_id, label, location, ip_address, sd_card_gb) VALUES (@d, (SELECT id FROM cctv_areas WHERE name = 'Floor 2'), 'CAM-22 Stairwell', 'Top of the main stairs', '192.168.10.42', 64);

INSERT INTO devices (category, serial_number, model, status, status_changed_at, last_checked_at, created_by, updated_by, created_at, updated_at)
VALUES ('cctv', NULL, 'Hikvision DS-2CD1143G2-I', 'ok', UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY, @budi, @budi, UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 5 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO cctv_cameras (device_id, area_id, label, location, ip_address, sd_card_gb) VALUES (@d, (SELECT id FROM cctv_areas WHERE name = 'Floor 3'), 'CAM-31 Corridor', 'Middle of the corridor, facing the labs', NULL, 64);

-- Chromebooks ------------------------------------------------------------------
-- Helper pattern: device row, chromebook row, open assignment row.
INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'NXK6ESN00123401', 'Acer Chromebook 311', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 80 DAY, UTC_TIMESTAMP() - INTERVAL 80 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, student_name, class_id, assigned_at) VALUES (@d, 'Aditya Pratama', (SELECT id FROM classes WHERE name = '7A'), UTC_TIMESTAMP() - INTERVAL 80 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, class_name, assigned_at, assigned_by) VALUES (@d, 'Aditya Pratama', '7A', UTC_TIMESTAMP() - INTERVAL 80 DAY, @rina);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'NXK6ESN00123402', 'Acer Chromebook 311', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 80 DAY, UTC_TIMESTAMP() - INTERVAL 80 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, student_name, class_id, assigned_at) VALUES (@d, 'Siti Rahmawati', (SELECT id FROM classes WHERE name = '7A'), UTC_TIMESTAMP() - INTERVAL 80 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, class_name, assigned_at, assigned_by) VALUES (@d, 'Siti Rahmawati', '7A', UTC_TIMESTAMP() - INTERVAL 80 DAY, @rina);

INSERT INTO devices (category, serial_number, model, status, status_changed_at, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'NXK6ESN00123403', 'Acer Chromebook 311', 'problem', UTC_TIMESTAMP() - INTERVAL 30 MINUTE, @rina, @budi, UTC_TIMESTAMP() - INTERVAL 80 DAY, UTC_TIMESTAMP() - INTERVAL 30 MINUTE);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, student_name, class_id, assigned_at) VALUES (@d, 'Muhammad Rizky', (SELECT id FROM classes WHERE name = '7B'), UTC_TIMESTAMP() - INTERVAL 80 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, class_name, assigned_at, assigned_by) VALUES (@d, 'Muhammad Rizky', '7B', UTC_TIMESTAMP() - INTERVAL 80 DAY, @rina);
INSERT INTO device_notes (device_id, user_id, kind, body, meta, created_at) VALUES
  (@d, @budi, 'report', 'Student reports the screen is cracked in the bottom-right corner after a fall.', NULL, UTC_TIMESTAMP() - INTERVAL 31 MINUTE),
  (@d, @budi, 'status', 'Status changed to Has problem', '{"from":"ok","to":"problem"}', UTC_TIMESTAMP() - INTERVAL 30 MINUTE);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'NXK6ESN00123404', 'Acer Chromebook 311', 'ok', @rina, @budi, UTC_TIMESTAMP() - INTERVAL 80 DAY, UTC_TIMESTAMP() - INTERVAL 2 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, student_name, class_id, assigned_at) VALUES (@d, 'Nadia Putri', (SELECT id FROM classes WHERE name = '8A'), UTC_TIMESTAMP() - INTERVAL 2 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, class_name, assigned_at, ended_at, assigned_by) VALUES
  (@d, 'Fajar Nugroho', '8A', UTC_TIMESTAMP() - INTERVAL 80 DAY, UTC_TIMESTAMP() - INTERVAL 2 DAY, @rina),
  (@d, 'Nadia Putri',   '8A', UTC_TIMESTAMP() - INTERVAL 2 DAY,  NULL, @budi);
INSERT INTO device_notes (device_id, user_id, kind, body, meta, created_at) VALUES
  (@d, @budi, 'assignment', 'Reassigned from Fajar Nugroho (8A) to Nadia Putri (8A)', '{"from":{"student":"Fajar Nugroho","class":"8A"},"to":{"student":"Nadia Putri","class":"8A"}}', UTC_TIMESTAMP() - INTERVAL 2 DAY);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'NXK6ESN00123405', 'Acer Chromebook 311', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 80 DAY, UTC_TIMESTAMP() - INTERVAL 80 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, student_name, class_id, assigned_at) VALUES (@d, 'Kevin Wijaya', (SELECT id FROM classes WHERE name = '8B'), UTC_TIMESTAMP() - INTERVAL 80 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, class_name, assigned_at, assigned_by) VALUES (@d, 'Kevin Wijaya', '8B', UTC_TIMESTAMP() - INTERVAL 80 DAY, @rina);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'PF3XK2LM', 'Lenovo 100e Chromebook Gen 4', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 70 DAY, UTC_TIMESTAMP() - INTERVAL 70 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, student_name, class_id, assigned_at) VALUES (@d, 'Putri Ayu Lestari', (SELECT id FROM classes WHERE name = '9A'), UTC_TIMESTAMP() - INTERVAL 70 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, class_name, assigned_at, assigned_by) VALUES (@d, 'Putri Ayu Lestari', '9A', UTC_TIMESTAMP() - INTERVAL 70 DAY, @rina);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'PF3XK2LN', 'Lenovo 100e Chromebook Gen 4', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 70 DAY, UTC_TIMESTAMP() - INTERVAL 70 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, student_name, class_id, assigned_at) VALUES (@d, 'Reza Firmansyah', (SELECT id FROM classes WHERE name = '10A'), UTC_TIMESTAMP() - INTERVAL 70 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, class_name, assigned_at, assigned_by) VALUES (@d, 'Reza Firmansyah', '10A', UTC_TIMESTAMP() - INTERVAL 70 DAY, @rina);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'PF3XK2LP', 'Lenovo 100e Chromebook Gen 4', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 70 DAY, UTC_TIMESTAMP() - INTERVAL 70 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, student_name, class_id, assigned_at) VALUES (@d, 'Anisa Maharani', (SELECT id FROM classes WHERE name = '11B'), UTC_TIMESTAMP() - INTERVAL 70 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, class_name, assigned_at, assigned_by) VALUES (@d, 'Anisa Maharani', '11B', UTC_TIMESTAMP() - INTERVAL 70 DAY, @rina);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'PF3XK2LQ', 'Lenovo 100e Chromebook Gen 4', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 70 DAY, UTC_TIMESTAMP() - INTERVAL 70 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, student_name, class_id, assigned_at) VALUES (@d, 'Dimas Saputra', (SELECT id FROM classes WHERE name = '12A'), UTC_TIMESTAMP() - INTERVAL 70 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, class_name, assigned_at, assigned_by) VALUES (@d, 'Dimas Saputra', '12A', UTC_TIMESTAMP() - INTERVAL 70 DAY, @rina);

-- A spare, not assigned to anyone.
INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'PF3XK2LR', 'Lenovo 100e Chromebook Gen 4', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 70 DAY, UTC_TIMESTAMP() - INTERVAL 70 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, student_name, class_id, assigned_at) VALUES (@d, NULL, NULL, NULL);
