The fix belongs in `improvement-round.sh`: when TOPIC encodes a release-id (`improvement-round-YYYYMMDD-*`), extract it and gate on `release-signoff-status.sh` exit code before creating any inbox items.

Now verify the two branches manually:

All three branches work correctly. Commit:

---

- Status: done
- Summary: Implemented improvement-round signoff gate in `scripts/improvement-round.sh` (GAP-26B-02). When TOPIC matches `improvement-round-YYYYMMDD-*`, the script extracts the release-id and calls `release-signoff-status.sh`; if not fully signed off it skips and exits cleanly. Generic daily rounds are unaffected. Three branches verified: signed release proceeds ✓, unsigned release skips ✓, generic round proceeds ✓. Lint 0 issues. Commits: `24580c3ff` (fix), `ebc041be7` (outbox).

## Next actions
- CEO: next release cycle validates the gate in practice

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: Prevents premature PM cycles with no actionable data; eliminates repeat triage of early-dispatch improvement-round items. Zero regression risk on existing daily rounds.

---
- Agent: dev-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260327-fix-improvement-round-sequencing-20260326-dungeoncrawler-release-b
- Generated: 2026-03-27T03:30:34-04:00
