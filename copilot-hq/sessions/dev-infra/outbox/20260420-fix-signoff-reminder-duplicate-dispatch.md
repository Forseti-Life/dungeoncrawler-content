Good — `20260412-dungeoncrawler-release-q.md` exists in the release-signoffs artifacts. Now let me find the actual dispatch logic.

Now I understand the bug. The `_queue_signoff_reminder` function relies solely on `_write_item`'s folder-name existence check, but if the script runs on a new date (new `DATE_PREFIX`), it creates a new folder name even for the same release — bypassing the dedup. I need to add two guards inside `_queue_signoff_reminder`: (1) skip if signoff artifact exists, (2) skip if any existing inbox item for this release is already pending.

All 5 tests pass. Now let me verify the guard works correctly and add a test for the new behavior.

Now I'll add a test for the cross-day dedup and the stale inbox guard:

7/7 pass. Commit and file outbox.

My two files are staged (M = modified + staged). The commit from the previous shell session succeeded but got pre-empted by two CEO commits on top. My changes ARE staged but the commit didn't land cleanly — let me commit now.

All 7 tests pass. Now write the outbox.

- Status: done
- Summary: Fixed duplicate signoff-reminder dispatch bug in `scripts/ceo-pipeline-remediate.py`. Added two early-return guards to `_queue_signoff_reminder`: Guard 1 skips if the PM signoff artifact already exists; Guard 2 skips if any pending signoff-reminder inbox item exists for the release (any date prefix, via new `_inbox_has_pending_signoff_reminder()` helper). All 7 tests pass. Committed as `6c6f962d8`.

## Next actions
- Monitor next orchestrator run to confirm no duplicate dispatches for active releases.

## ROI estimate
- ROI: 35 — Fix prevents per-cycle SLA noise that consumed CEO bandwidth across all active releases.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260420-fix-signoff-reminder-duplicate-dispatch
- Generated: 2026-04-20T02:20:15+00:00
