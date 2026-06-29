# Release cleanup: stale in_progress features for dungeoncrawler

- Agent: pm-dungeoncrawler
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: 2026-04-24T18:00:07Z


## Issue

Release cleanup is needed for `dungeoncrawler`. These features are still marked `in_progress` on stale releases while the active release is `20260412-dungeoncrawler-release-t`:

- `dc-cr-ceaseless-shadows` on `20260412-dungeoncrawler-release-s` (dev outbox exists)
- `dc-cr-halfling-resolve` on `20260412-dungeoncrawler-release-s` (dev outbox exists)
- `dc-cr-halfling-weapon-expertise` on `20260412-dungeoncrawler-release-s` (dev outbox exists)

Reset stale features to `ready` / clear release, or mark them `done` if implementation already shipped.

## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- `bash scripts/ceo-release-health.sh` should no longer report orphaned features for `dungeoncrawler`
