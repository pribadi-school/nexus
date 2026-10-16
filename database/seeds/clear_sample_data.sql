-- Removes everything database/seeds/sample_data.sql added, so you can start
-- entering real data. Run it in phpMyAdmin (SQL tab) BEFORE importing the
-- real Chromebook lists: it deletes every Chromebook with its notes and
-- history, and the two sample people. The StudyTouch boards, rooms and CCTV
-- cameras stay (they are the school's real ones).

SET NAMES utf8mb4;

DELETE FROM device_notes WHERE device_id IN (SELECT id FROM devices WHERE category = 'chromebook');
DELETE FROM chromebook_assignments;
DELETE FROM chromebooks;
DELETE FROM devices WHERE category = 'chromebook';
DELETE FROM users WHERE name IN ('Rina Wulandari', 'Budi Santoso');
