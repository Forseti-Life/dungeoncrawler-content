GAP-DC-RB-IR-01 is in the commit message (not the script body) — all 4 functional ACs PASS. Now update checklist and write APPROVE outbox.

The files were already committed via the auto-checkpoint at `0b66de681`. The outbox and checklist are already in the repo.

All files are committed in the auto-checkpoint `0b66de681`.

- Status: done
- Summary: Verified dev-infra commit `4c2f0ce95` implementing GAP-DC-RB-IR-01 (stale gate2-ready dispatch guard in `scripts/site-audit-run.sh`). The `_queue_pm_gate2_ready_item()` function now re-reads `tmp/release-cycle-active/<team_id>.release_id` at dispatch time — if the file is gone, it skips with an INFO log (release no longer active); if it holds a different release_id, it skips with INFO log (cycle advanced). The existing signoff-file guard is retained as secondary defense. All 4 functional AC pattern checks PASS; 35-test orchestrator suite PASS (no regression); `bash -n` PASS; lint 0 issues; 5 QA suite manifests validated. Outbox and checklist persisted in auto-checkpoint `0b66de681`. APPROVE.

## Next actions
- Inbox empty — awaiting next dispatch

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 40
- Rationale: Prevents phantom gate2-ready inbox items from polluting PM queues after a release closes, reducing false-alarm noise and PM confusion during release transitions.

---
- Agent: qa-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-infra/inbox/20260408-unit-test-20260408-dc-rb-ir-stale-gate2-notify-guard
- Generated: 2026-04-08T07:53:58+00:00
