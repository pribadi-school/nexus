-- Nexus: sample data, so the UI has something to show.
--
-- For local development and a first look on the server. Every person and
-- device here is made up. StudyTouch boards and CCTV cameras are not: they
-- are the school's real ones, added by migrations 002 and 004, so this
-- only adds Chromebooks. Import AFTER the migrations. Remove it all again
-- with database/seeds/clear_sample_data.sql before entering real data.
-- Times are relative to the moment you import, so the feed looks recent.

SET NAMES utf8mb4;

-- Sample people (as if they had signed in with these names) -----------------
INSERT INTO users (name, last_login_at, created_at) VALUES
  ('Rina Wulandari', UTC_TIMESTAMP() - INTERVAL 1 HOUR, UTC_TIMESTAMP() - INTERVAL 40 DAY),
  ('Budi Santoso',   UTC_TIMESTAMP() - INTERVAL 2 HOUR, UTC_TIMESTAMP() - INTERVAL 40 DAY);
SET @rina = (SELECT id FROM users WHERE name = 'Rina Wulandari');
SET @budi = (SELECT id FROM users WHERE name = 'Budi Santoso');

-- Chromebooks ------------------------------------------------------------------
-- One batch per academic year; asset IDs are year-number. Helper pattern:
-- device row, chromebook row, open assignment row. The 2026 batch has no
-- serial numbers yet, like the real list.
INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'NXK6ESN00123401', 'Acer Chromebook 311', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 800 DAY, UTC_TIMESTAMP() - INTERVAL 800 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, academic_year, asset_id, student_name, student_email, assigned_at) VALUES (@d, 2024, '2024-01', 'Aditya Pratama', 'aditya.smp24@example.sch.id', UTC_TIMESTAMP() - INTERVAL 800 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, assigned_at, assigned_by) VALUES (@d, 'Aditya Pratama', UTC_TIMESTAMP() - INTERVAL 800 DAY, @rina);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'NXK6ESN00123402', 'Acer Chromebook 311', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 800 DAY, UTC_TIMESTAMP() - INTERVAL 800 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, academic_year, asset_id, student_name, student_email, assigned_at) VALUES (@d, 2024, '2024-02', 'Siti Rahmawati', 'siti.smp24@example.sch.id', UTC_TIMESTAMP() - INTERVAL 800 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, assigned_at, assigned_by) VALUES (@d, 'Siti Rahmawati', UTC_TIMESTAMP() - INTERVAL 800 DAY, @rina);

INSERT INTO devices (category, serial_number, model, status, status_changed_at, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'NXK6ESN00123403', 'Acer Chromebook 311', 'problem', UTC_TIMESTAMP() - INTERVAL 30 MINUTE, @rina, @budi, UTC_TIMESTAMP() - INTERVAL 800 DAY, UTC_TIMESTAMP() - INTERVAL 30 MINUTE);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, academic_year, asset_id, student_name, student_email, assigned_at) VALUES (@d, 2024, '2024-03', 'Muhammad Rizky', 'rizky.smp24@example.sch.id', UTC_TIMESTAMP() - INTERVAL 800 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, assigned_at, assigned_by) VALUES (@d, 'Muhammad Rizky', UTC_TIMESTAMP() - INTERVAL 800 DAY, @rina);
INSERT INTO device_notes (device_id, user_id, kind, body, meta, created_at) VALUES
  (@d, @budi, 'report', 'Student reports the screen is cracked in the bottom-right corner after a fall.', NULL, UTC_TIMESTAMP() - INTERVAL 31 MINUTE),
  (@d, @budi, 'status', 'Status changed to Has problem', '{"from":"ok","to":"problem"}', UTC_TIMESTAMP() - INTERVAL 30 MINUTE);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'NXJAESN0015199901', 'Acer Chromebook 311', 'ok', @rina, @budi, UTC_TIMESTAMP() - INTERVAL 450 DAY, UTC_TIMESTAMP() - INTERVAL 2 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, academic_year, asset_id, student_name, student_email, assigned_at) VALUES (@d, 2025, '2025-01', 'Nadia Putri', 'nadia.smp25@example.sch.id', UTC_TIMESTAMP() - INTERVAL 2 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, assigned_at, ended_at, assigned_by) VALUES
  (@d, 'Fajar Nugroho', UTC_TIMESTAMP() - INTERVAL 450 DAY, UTC_TIMESTAMP() - INTERVAL 2 DAY, @rina),
  (@d, 'Nadia Putri',   UTC_TIMESTAMP() - INTERVAL 2 DAY,   NULL, @budi);
INSERT INTO device_notes (device_id, user_id, kind, body, meta, created_at) VALUES
  (@d, @budi, 'assignment', 'Reassigned from Fajar Nugroho to Nadia Putri.', '{"from":{"student":"Fajar Nugroho"},"to":{"student":"Nadia Putri"}}', UTC_TIMESTAMP() - INTERVAL 2 DAY);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'NXJAESN0015199902', 'Acer Chromebook 311', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 450 DAY, UTC_TIMESTAMP() - INTERVAL 450 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, academic_year, asset_id, student_name, student_email, assigned_at) VALUES (@d, 2025, '2025-02', 'Kevin Wijaya', 'kevin.smp25@example.sch.id', UTC_TIMESTAMP() - INTERVAL 450 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, assigned_at, assigned_by) VALUES (@d, 'Kevin Wijaya', UTC_TIMESTAMP() - INTERVAL 450 DAY, @rina);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'NXJAESN0015199903', 'Acer Chromebook 311', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 450 DAY, UTC_TIMESTAMP() - INTERVAL 450 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, academic_year, asset_id, student_name, student_email, assigned_at) VALUES (@d, 2025, '2025-03', 'Putri Ayu Lestari', 'putri.sma25@example.sch.id', UTC_TIMESTAMP() - INTERVAL 450 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, assigned_at, assigned_by) VALUES (@d, 'Putri Ayu Lestari', UTC_TIMESTAMP() - INTERVAL 450 DAY, @rina);

-- A spare, not assigned to anyone.
INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', 'NXJAESN0015199904', 'Acer Chromebook 311', 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 450 DAY, UTC_TIMESTAMP() - INTERVAL 450 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, academic_year, asset_id, student_name, student_email, assigned_at) VALUES (@d, 2025, '2025-04', NULL, NULL, NULL);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', NULL, NULL, 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 90 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, academic_year, asset_id, student_name, student_email, assigned_at) VALUES (@d, 2026, '2026-01', 'Reza Firmansyah', NULL, UTC_TIMESTAMP() - INTERVAL 90 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, assigned_at, assigned_by) VALUES (@d, 'Reza Firmansyah', UTC_TIMESTAMP() - INTERVAL 90 DAY, @rina);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', NULL, NULL, 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 90 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, academic_year, asset_id, student_name, student_email, assigned_at) VALUES (@d, 2026, '2026-02', 'Anisa Maharani', NULL, UTC_TIMESTAMP() - INTERVAL 90 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, assigned_at, assigned_by) VALUES (@d, 'Anisa Maharani', UTC_TIMESTAMP() - INTERVAL 90 DAY, @rina);

INSERT INTO devices (category, serial_number, model, status, created_by, updated_by, created_at, updated_at)
VALUES ('chromebook', NULL, NULL, 'ok', @rina, @rina, UTC_TIMESTAMP() - INTERVAL 90 DAY, UTC_TIMESTAMP() - INTERVAL 90 DAY);
SET @d = LAST_INSERT_ID();
INSERT INTO chromebooks (device_id, academic_year, asset_id, student_name, student_email, assigned_at) VALUES (@d, 2026, '2026-03', 'Dimas Saputra', NULL, UTC_TIMESTAMP() - INTERVAL 90 DAY);
INSERT INTO chromebook_assignments (device_id, student_name, assigned_at, assigned_by) VALUES (@d, 'Dimas Saputra', UTC_TIMESTAMP() - INTERVAL 90 DAY, @rina);
