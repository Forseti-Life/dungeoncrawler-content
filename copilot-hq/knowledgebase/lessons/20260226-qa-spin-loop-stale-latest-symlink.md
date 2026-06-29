# Lesson Learned: QA spin loop from stale `latest` symlink

## Date
2026-02-26

## Symptom
Dev received 33+ identical inbox items over ~16 hours for the same QA run ID, all pointing to pre-fix findings. Each cycle: dev confirmed fix already committed, wrote `Status: blocked`, escalated to supervisor/CEO. No resolution came from the escalation chain.

## Root cause

### Primary: `auto_requeue_findings_item()` retry loop
When `outbox_file` exists for the base item ID, the function creates a `{item_id}-retry-{timestamp}` inbox item. Dev handles it and writes a new outbox file for the retry. But the NEXT cycle re-checks the original outbox (still exists) and fires another retry with a new timestamp. Infinite loop.

### Secondary: `latest` symlink not updated after rule-only fixes
After a QA permissions rule change (no code change needed on the site), no one triggered a fresh audit. `latest` continued pointing to the pre-fix run showing violations.

### Tertiary: Escalation chain all blocked
CEO inbox items themselves caused recursive escalations that were suppressed. No human-visible notification surfaced with the actual fix recommendation until outbox content was added to escalation messages.

## Fix applied
Commit `357230a`: added `no-destructive` first-match rule to `org-chart/sites/dungeoncrawler/qa-permissions.json` suppressing all `delete`/`delete-all` paths from permission probing. QA re-run confirmed 0 violations.

## Prevention

1. **After any `qa-permissions.json` change**: immediately trigger a QA re-run and update the `latest` symlink BEFORE the next automation cycle fires.

2. **`auto_requeue_findings_item()` guard**: before creating a retry, scan outbox files for this run_id. If any contain `Status: blocked`, suppress re-queue and trigger a QA re-run (via `auto_queue_qa_rerun_item`) instead. Track suppressed run IDs in monitor state to prevent re-firing.

3. **Supervisors must act on `Status: blocked` escalations within one cycle** for release-gate items. The 3-cycle mandatory CEO escalation trigger is the backstop, not the first line of defense.

4. **QA re-run as auto-remediation**: when `execution_stalled AND dev_status == "blocked"` AND dev outbox contains `QA handoff` or `QA re-run` marker, the monitor should call `auto_queue_qa_rerun_item()` rather than `try_auto_remediate()`.

## Affected files
- `org-chart/sites/dungeoncrawler/qa-permissions.json` — rule fix
- `scripts/release-kpi-monitor.py` — retry loop fix (see fix below)
- `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/` — symlink (not updated by dev automation)
