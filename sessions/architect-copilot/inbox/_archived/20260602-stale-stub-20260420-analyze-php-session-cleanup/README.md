# Stale stub — analyze-php-session-cleanup

- Archived: 2026-06-02
- Reason: This inbox item existed in the active architect inbox with only `roi.txt` present (no problem statement, command, acceptance criteria, or links). A repo-wide search found no other brief for this item.

## What to do to re-issue
Create a new architect inbox item with a full README including:
- Which PHP app(s) (Drupal? other) and session backend (files/DB/Redis)
- Current cleanup mechanism (cron/systemd-tmpfiles/etc.) and pain (disk, stale auth, performance)
- Target architecture (TTL policy, storage backend, cleanup responsibility)
- Acceptance criteria + verification steps
