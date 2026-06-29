# Release cleanup: stale in_progress features for dungeoncrawler

- Agent: pm-dungeoncrawler
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: 2026-04-15T00:00:41Z

## Issue

Release cleanup is needed for `dungeoncrawler`. These features are still marked `in_progress` on stale releases while the active release is `20260412-dungeoncrawler-release-m`:

- `dc-cr-goblin-ancestry` on `20260412-dungeoncrawler-release-l` (dev outbox exists)
- `dc-cr-goblin-very-sneaky` on `20260412-dungeoncrawler-release-l` (dev outbox exists)
- `dc-cr-goblin-weapon-familiarity` on `20260412-dungeoncrawler-release-l` (dev outbox exists)
- `dc-cr-halfling-ancestry` on `20260412-dungeoncrawler-release-l` (dev outbox exists)
- `dc-cr-halfling-keen-eyes` on `20260412-dungeoncrawler-release-l` (dev outbox exists)

Reset stale features to `ready` / clear release, or mark them `done` if implementation already shipped.

## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- `bash scripts/ceo-release-health.sh` should no longer report orphaned features for `dungeoncrawler`
- Status: pending
