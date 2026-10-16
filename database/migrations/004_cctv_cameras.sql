-- Nexus: the school's CCTV cameras (from "Data CCTV Sekolah 2025").
--
-- A camera is now just its spot, its type and its SD card: areas, exact
-- location and IP address are dropped. The type is kept in devices.model:
-- "C200" (the newer cameras) or "Old version". Cameras are listed in the
-- order they are added here, which follows the school's sheet.

SET NAMES utf8mb4;

ALTER TABLE cctv_cameras DROP FOREIGN KEY fk_cctv_area;
ALTER TABLE cctv_cameras
  DROP KEY ix_cctv_area,
  DROP COLUMN area_id,
  DROP COLUMN location,
  DROP COLUMN ip_address;
DROP TABLE cctv_areas;

-- One camera per spot: the device row, then its spot and SD card size.

INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SD Grade 1', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SD Grade 2', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SD Grade 3', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SD Grade 4', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SD Grade 5', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SD Grade 6', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SD Science & Art Room', 64);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SD ICT Room', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMP Grade 7A', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMP Grade 7B', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMP Grade 8A', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMP Grade 8B', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMP Grade 9A', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMP Grade 9B', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMA Grade 10A', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMA Grade 10B', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMA Grade 11A', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMA Grade 11B', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMA Grade 12A', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMA Grade 12B', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'SMA Grade 12C', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Lobby', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Dapur', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Gerbang Belakang', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Depan Kelas 1', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Resepsionis', 64);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Ruang Makan', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Lapangan Voli', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Koridor Gedung B', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Gedung A - Tangga', 64);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Gedung B - Depan Lab Fisika', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Gedung A - Koridor SD', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Gedung B - Tangga', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Lab Kimia', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Lab Fisika', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Koridor Gedung B', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Gedung A - Lantai 3', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Gedung B - Tangga Lantai 3', 64);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Gedung B - Depan 10A', 64);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Kepsek SMP', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Lantai 3 - Koridor SMP', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Lab Komputer', 256);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'IT Room', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Gedung A - Tangga Lantai 4', 64);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'AVR 1', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'AVR 2', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Parkiran 1', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Parkiran 2', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Lapangan Outdoor 1', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Lapangan Outdoor 2', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Lapangan Outdoor 3', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Parkiran Musholla Meunasah', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Lapangan Badminton', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'Old version', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Kantin Luar', 32);
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('cctv', 'C200', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO cctv_cameras (device_id, label, sd_card_gb) VALUES (LAST_INSERT_ID(), 'Depan AVR', 32);

INSERT INTO schema_migrations (version) VALUES ('004_cctv_cameras');
