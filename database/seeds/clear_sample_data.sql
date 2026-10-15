-- Removes everything database/seeds/sample_data.sql added, so you can start
-- entering real data. Run it in phpMyAdmin (SQL tab) BEFORE adding real
-- devices: it deletes every device, note, room and CCTV area, and the two
-- sample people. The class list stays.

SET NAMES utf8mb4;

DELETE FROM device_notes;
DELETE FROM chromebook_assignments;
DELETE FROM chromebooks;
DELETE FROM cctv_cameras;
DELETE FROM studytouch_boards;
DELETE FROM devices;
DELETE FROM rooms;
DELETE FROM cctv_areas;
DELETE FROM users WHERE name IN ('Rina Wulandari', 'Budi Santoso');
