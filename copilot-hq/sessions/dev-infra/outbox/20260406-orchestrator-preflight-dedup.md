Now I understand the full picture. The dispatch happens in `release-cycle-start.sh` (called from `_release_cycle_step` in `run.py`). I'll add the dedup guard there and create a unit test.

Now create the unit test:

All 4 tests pass. Now commit and write outbox.

- Status: done
- Summary: Implemented GAP-QA-PREFLIGHT-DEDUP-01 in `scripts/release-cycle-start.sh`. Added a time-based dedup guard before dispatch: scans `sessions/<qa_agent>/outbox/` for any preflight outbox written within 4 hours; if found, checks `git log --since=<mtime>` for QA-scoped commits (`qa-suites/`, `qa-permissions.json`, `03-test-plan.md`); if none, exits with `PREFLIGHT-SUPPRESSED`. If QA commits exist, dispatch proceeds. Created `orchestrator/tests/test_preflight_dedup.py` (4 unittest cases) covering all AC scenarios. All 4 tests pass. Committed `0c0c9b9e`.

## Next actions
- None.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 40
- Rationale: Reclaims ~7 QA execution slots per release cycle. High recurrence prevention; no QA signal lost since the guard allows dispatch whenever QA config changes.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260406-orchestrator-preflight-dedup
- Generated: 2026-04-06T10:35:28+00:00
