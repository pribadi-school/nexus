-- Nexus: the school's rooms and the StudyTouch board in each one.
--
-- Rooms: Grade 1 to 6 have one class each; from grade 7 up there are
-- classes A and B, and grade 7 also has C; plus the ICT Lab.
-- Boards: one per room, all on firmware v2.0.290925 (29 September 2025).
-- Every board is a StudyTouch except Grade 2, Grade 8A and the ICT Lab,
-- which have a Merah Putih board.

SET NAMES utf8mb4;

INSERT IGNORE INTO rooms (name, sort_order) VALUES
  ('Grade 1', 10), ('Grade 2', 20), ('Grade 3', 30),
  ('Grade 4', 40), ('Grade 5', 50), ('Grade 6', 60),
  ('Grade 7A', 70), ('Grade 7B', 80), ('Grade 7C', 90),
  ('Grade 8A', 100), ('Grade 8B', 110),
  ('Grade 9A', 120), ('Grade 9B', 130),
  ('Grade 10A', 140), ('Grade 10B', 150),
  ('Grade 11A', 160), ('Grade 11B', 170),
  ('Grade 12A', 180), ('Grade 12B', 190),
  ('ICT Lab', 200);

-- One board per room: the device row, then its room and firmware.
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 1';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'Merah Putih', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 2';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 3';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 4';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 5';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 6';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 7A';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 7B';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 7C';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'Merah Putih', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 8A';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 8B';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 9A';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 9B';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 10A';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 10B';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 11A';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 11B';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 12A';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'StudyTouch', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'Grade 12B';
INSERT INTO devices (category, model, status, created_at, updated_at) VALUES ('studytouch', 'Merah Putih', 'ok', UTC_TIMESTAMP(), UTC_TIMESTAMP());
INSERT INTO studytouch_boards (device_id, room_id, firmware_version) SELECT LAST_INSERT_ID(), id, 'v2.0.290925' FROM rooms WHERE name = 'ICT Lab';

INSERT INTO schema_migrations (version) VALUES ('002_studytouch_boards');
