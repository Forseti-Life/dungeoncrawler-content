Now I understand the full picture. The `release-signoff-status.sh` checks for file existence only — it doesn't check for "Signed by: orchestrator". The fix needs to go in `improvement-round.sh` to add the stale-artifact check. Also, the bare-topic (daily loop) path needs a guard too.

Now test the three cases:

All 3 tests pass. Now commit and write outbox:

---

- Status: done
- Summary: Fixed `scripts/improvement-round.sh` to guard against stale orchestrator-generated signoff artifacts (GAP-26B-02). The release-id gate now adds a second check after `release-signoff-status.sh`: it scans `sessions/*/artifacts/release-signoffs/<slug>.md` for `Signed by: orchestrator` and skips dispatch if any stale artifact is found. All 3 test cases verified: no signoff → SKIP, orchestrator signoff → SKIP, real PM signoffs → inbox items created. Committed `977372dc`.

## ROI estimate
- ROI: 8
- Rationale: Eliminates the class of premature improvement-round dispatch caused by stale orchestrator signoff artifacts, ending 4-agent fast-exit waste per occurrence.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix
- Generated: 2026-04-05T22:27:22+00:00
