# Clear activity history while keeping StudyTouch 12B

This is a one-off CLI operation, not a database migration. It removes the
existing `device_notes` entries for other devices. Device records, current
statuses, users, Chromebook assignments, Google data and Signage are unchanged.
New activity continues to be recorded normally.

Run in cPanel Terminal after updating Nexus:

```bash
bash ~/repositories/nexus/update.sh
/opt/alt/php82/usr/bin/php ~/repositories/nexus/bin/clear-activity-history.php
```

The second command only previews counts. Apply the cleanup with:

```bash
/opt/alt/php82/usr/bin/php ~/repositories/nexus/bin/clear-activity-history.php --apply
```

The command must find exactly one active StudyTouch in room `12B` (also
recognizes `12 B`, `XIIB` or `XII B`). Missing or ambiguous matches abort
without deleting anything. Before deletion, it writes a private backup of the
removed history to `storage/private/activity-history-*.json`. The operation is
transactional and verifies that the retained notes are unchanged.

The output contains the actual backup filename. To restore, use that file:

```bash
/opt/alt/php82/usr/bin/php ~/repositories/nexus/bin/clear-activity-history.php --restore=/home/pribadisch/repositories/nexus/storage/private/ACTUAL-BACKUP-FILENAME.json --apply
```

Restore accepts only this database's own private history backup and preserves
note IDs, dates, authors and metadata. Conflicting IDs abort the restore.
Keep backups private; they contain the original note text.
