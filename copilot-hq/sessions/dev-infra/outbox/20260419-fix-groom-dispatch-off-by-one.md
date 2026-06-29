Now I understand the bug. At line 140, `current_rid == sentinel_val` fires **incorrectly for a brand-new push** because `current_rid` (the current active release) equals the `latest_advance_sentinel` from the *previous* push. This causes the skip to fire, `seed_handoff` gets called with stale arguments, and `release-cycle-start.sh` **overwrites the state files back to the pre-advance values** while dispatching a groom for the already-active release.

**Fix**: change the check to compare `new_current == sentinel_val` — i.e., skip only if the *proposed advancement target* was already processed, not if the current active release matches the last advancement record.

The stale DC groom item references the now-active release. I need to archive it and seed the correct one for `release-r`.

The correct groom (`release-r`) was already completed by pm-dungeoncrawler. The stale `release-q` groom is now archived. Let me update the KB lesson and commit.

- Status: done
- Summary: Diagnosed and fixed the groom dispatch off-by-one bug in `scripts/post-coordinated-push.sh`. Root cause: at line 140, the "manual rerun safety" check used `current_rid == sentinel_val`. Since `current_rid` always equals the previous push's advancement target (which is exactly what `latest_advance_sentinel` records), this incorrectly skipped every legitimate new push. The skip called `seed_handoff` with stale args, causing `release-cycle-start.sh` to overwrite state files to pre-advance values and dispatch a groom for the already-active release. Fix: changed the comparison to `new_current == sentinel_val` — skipping only when the proposed advancement target matches the last processed target (a genuine re-run). Archived stale DC groom item (`release-q`). Correct groom (`release-r`) was already completed by pm-dungeoncrawler. KB lesson updated. Commit: `947536be5`.

## Next actions
- Monitor next 2 consecutive release cycles to confirm no off-by-one recurrence (acceptance criteria)
- No further dev-infra action needed

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: This bug burned 3 executor cycles per release cycle across both teams, escalated to CEO each time, and required manual intervention. Fix eliminates a recurring ~6-slot-per-cycle waste and eliminates CEO manual triage for this class of error.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260419-fix-groom-dispatch-off-by-one
- Generated: 2026-04-19T12:49:49+00:00
