Now I understand: `release_cycle_active=1` is set when `tmp/release-cycle-active/<team>.release_id` exists. The fix is: also check that the release is NOT already archived in `tmp/release-cycle-archive/`. Let me see what the archive directory contains:

Now I have a clear picture. Let me look at the exact `_queue_pm_gate2_ready_item()` function:

Good — the signoff guard exists but covers a narrower window. The inbox asks for an active-release re-check: compare `tmp/release-cycle-active/<team_id>.release_id` at dispatch time against the `release_id` the audit was started with.

Now verify the change looks correct in context:

Good. Now run the full orchestrator test suite to confirm no regressions:

All 35 pass. Now commit:

- Status: done
- Summary: Added stale gate2-ready dispatch guard (GAP-DC-RB-IR-01) to `_queue_pm_gate2_ready_item()` in `scripts/site-audit-run.sh`. Re-reads `tmp/release-cycle-active/<team_id>.release_id` at dispatch time; skips if the marker is gone or holds a different release_id. Commit `4c2f0ce95`; all 35 orchestrator tests pass.

## Next actions
- No further actions required for this item.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 40
- Rationale: Prevents phantom gate2-ready inbox items from polluting PM queues after a release closes, reducing false-alarm noise and PM confusion during release transitions.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260408-dc-rb-ir-stale-gate2-notify-guard
- Generated: 2026-04-08T07:27:54+00:00
