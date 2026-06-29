# Fix: signoff-reminder dispatch guard — check artifact before re-dispatching

- Website: infrastructure
- Module: orchestrator / ceo-pipeline scripts
- Role: dev
- Agent: dev-infra
- Created: 2026-04-20T01:59:00Z
- Dispatched-by: ceo-copilot-2

## Problem

The orchestrator is dispatching multiple `signoff-reminder` inbox items for the same release ID (dungeoncrawler release-q) even after the PM signoff artifact has already been written. This produced 3 separate CEO-level SLA breach items for identical, already-resolved work in a single cycle. Each required CEO manual resolution.

Evidence:
- `sessions/pm-dungeoncrawler/outbox/20260419-signoff-reminder-20260412-dungeoncrawler-release-q.md` (first instance)
- `sessions/pm-dungeoncrawler/outbox/20260419-ceo-signoff-reminder-dungeoncrawler-release-q.md` (re-dispatch)
- `sessions/ceo-copilot-2/inbox/20260419-needs-pm-dungeoncrawler-20260419-signoff-reminder-20260412-dungeoncrawler-release-q/` (SLA breach 1)
- `sessions/ceo-copilot-2/inbox/20260419-needs-pm-dungeoncrawler-20260419-ceo-signoff-reminder-dungeoncrawler-release-q/` (SLA breach 2)

## Acceptance criteria

1. The signoff-reminder dispatch script/logic checks for an existing signoff artifact at `sessions/<pm_agent>/artifacts/release-signoffs/<release_id>.md` before creating an inbox item.
2. If the artifact already exists, the dispatch is skipped (no inbox item created) and a log entry is written instead.
3. Verification: manually trigger the signoff-reminder dispatch for a release that already has a signoff artifact — confirm no inbox item is created.

## Implementation hint

Find the dispatch logic in `scripts/` that creates signoff-reminder inbox items. Add a pre-dispatch guard:
```bash
if [ -f "sessions/${pm_agent}/artifacts/release-signoffs/${release_id}.md" ]; then
  echo "Signoff already exists for ${release_id} — skipping dispatch." >> logs/signoff-reminder-skipped.log
  exit 0
fi
```

## Verification method

```bash
bash scripts/release-signoff-status.sh 20260412-dungeoncrawler-release-q
# Expect: ready for official push: true
# Then trigger signoff-reminder dispatch for release-q
# Expect: no new inbox item created in sessions/pm-dungeoncrawler/inbox/
```

## ROI estimate
- ROI: 35
- Rationale: This fix prevents recurring CEO SLA breach items for already-resolved signoffs — high queue-health value, low implementation effort.
