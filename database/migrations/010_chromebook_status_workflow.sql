-- Preserve the conversion plan so every old status can be recovered.
SET NAMES utf8mb4;
CREATE TABLE chromebook_status_conversion (
  device_id INT UNSIGNED NOT NULL PRIMARY KEY,
  old_status VARCHAR(20) NOT NULL,
  old_status_changed_at DATETIME NULL,
  new_status VARCHAR(20) NOT NULL,
  new_status_changed_at DATETIME NULL,
  source_note_id INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO chromebook_status_conversion
SELECT d.id, d.status, d.status_changed_at,
       CASE WHEN n.kind IN ('problem','fixed','fix','borrowed','replaced','broken')
                 AND (d.status_changed_at IS NULL OR n.created_at >= d.status_changed_at)
            THEN CASE WHEN n.kind = 'fix' THEN 'fixed' ELSE n.kind END
            ELSE CASE d.status WHEN 'reported' THEN 'problem' WHEN 'repaired' THEN 'fixed'
                               WHEN 'ok' THEN 'unmarked' ELSE d.status END END,
       CASE WHEN n.kind IN ('problem','fixed','fix','borrowed','replaced','broken')
                 AND (d.status_changed_at IS NULL OR n.created_at >= d.status_changed_at)
            THEN n.created_at ELSE d.status_changed_at END,
       n.id
FROM devices d
LEFT JOIN device_notes n ON n.id = (
  SELECT n2.id FROM device_notes n2
  WHERE n2.device_id = d.id AND n2.kind NOT IN ('status','assignment')
  ORDER BY n2.created_at DESC, n2.id DESC LIMIT 1
)
WHERE d.category = 'chromebook';

UPDATE devices d JOIN chromebook_status_conversion c ON c.device_id = d.id
SET d.status = c.new_status, d.status_changed_at = c.new_status_changed_at;

-- Conversion entries preserve the original status and source note in the audit trail.
INSERT INTO device_notes (device_id, user_id, kind, body, meta, created_at)
SELECT device_id, NULL, 'status', CONCAT('Status converted to ', new_status),
       JSON_OBJECT('from', old_status, 'to', new_status, 'source_note_id', source_note_id, 'conversion', TRUE),
       COALESCE(new_status_changed_at, UTC_TIMESTAMP())
FROM chromebook_status_conversion
WHERE old_status <> new_status AND new_status <> 'unmarked';

INSERT INTO schema_migrations (version) VALUES ('010_chromebook_status_workflow');
