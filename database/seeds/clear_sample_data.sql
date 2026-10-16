-- Removes everything database/seeds/sample_data.sql added, so you can start
-- entering real data. Run it in phpMyAdmin (SQL tab) BEFORE adding real
-- cameras or Chromebooks: it deletes every CCTV camera and Chromebook with
-- their notes, the CCTV areas, and the two sample people. The StudyTouch
-- boards and rooms stay (they are the school's real ones).

SET NAMES utf8mb4;

DELETE FROM device_notes WHERE device_id IN (SELECT id FROM devices WHERE category <> 'studytouch');
DELETE FROM chromebook_assignments;
DELETE FROM chromebooks;
DELETE FROM cctv_cameras;
DELETE FROM devices WHERE category <> 'studytouch';
DELETE FROM cctv_areas;
DELETE FROM users WHERE name IN ('Rina Wulandari', 'Budi Santoso');
