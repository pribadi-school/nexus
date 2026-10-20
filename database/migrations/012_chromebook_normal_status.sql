-- Normal replaces the previous empty condition; recorded workflow statuses are preserved.
UPDATE devices SET status = 'normal'
WHERE category = 'chromebook' AND status IN ('ok', 'unmarked');

INSERT INTO schema_migrations (version) VALUES ('012_chromebook_normal_status');
