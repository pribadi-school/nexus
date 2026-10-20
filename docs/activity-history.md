# Dashboard activity visibility and history maintenance

## Hide older dashboard activity without changing history

To hide dashboard entries older than the latest Grade 12B StudyTouch problem:

```bash
bash ~/repositories/nexus/update.sh
/opt/alt/php82/usr/bin/php ~/repositories/nexus/bin/set-dashboard-activity.php --apply
```

Omit `--apply` to preview the cutoff and hidden count first. The command must
find one active board in 12B / XII B / Grade 12B and an existing problem note or
status change to problem. Otherwise nothing changes. It preserves the problem
entry, entries at the same time, and all newer activity. The cutoff is saved once
in `storage/private/dashboard-activity.json`; rerunning does not move it forward
when a new problem is reported. No database rows or device records are changed.
Device history and **All activity** remain complete. No SQL migration is needed.

To show all dashboard activity again:

```bash
/opt/alt/php82/usr/bin/php ~/repositories/nexus/bin/set-dashboard-activity.php --reset --apply
```

## Clear history while keeping StudyTouch 12B

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
