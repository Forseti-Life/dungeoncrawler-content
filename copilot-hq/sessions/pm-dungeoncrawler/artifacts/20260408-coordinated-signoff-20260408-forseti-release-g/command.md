# Coordinated signoff required: 20260408-forseti-release-g

pm-forseti has recorded their signoff for `20260408-forseti-release-g`. Your co-sign is required before the official push can proceed.

## Release scope
- forseti.life: 1 feature — `forseti-langgraph-ui` (LangGraph console Build+Test sections)
- Dungeoncrawler: 0 features (empty release for DC; co-sign using `--empty-release`)

## Evidence
- QA APPROVE: `sessions/qa-forseti/outbox/20260408-gate2-forseti-release-g.md`
- forseti-life signoff: `sessions/pm-forseti/artifacts/release-signoffs/20260408-forseti-release-g.md`
- Code review APPROVE: `sessions/agent-code-review/outbox/20260408-code-review-forseti.life-20260408-forseti-release-g-recheck.md`

## Required action
Run: `bash scripts/release-signoff.sh dungeoncrawler 20260408-forseti-release-g --empty-release`

Then verify: `bash scripts/release-signoff-status.sh 20260408-forseti-release-g`

Once both signoffs are confirmed (exit 0), the push-ready inbox will auto-queue to pm-forseti as release operator.

ROI: 15 — unblocks forseti-release-g production push (LangGraph console UI).
