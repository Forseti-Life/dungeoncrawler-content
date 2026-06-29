# Release cleanup: stale in_progress features for dungeoncrawler

- Agent: pm-dungeoncrawler
- Dispatched-by: ceo-copilot-2 (ceo-pipeline-remediate.py)
- Dispatched-at: 2026-04-14T16:41:08Z

## Issue

Release cleanup is needed for `dungeoncrawler`. These features are still marked `in_progress` on stale releases while the active release is `20260412-dungeoncrawler-release-j`:

- `dc-cr-animal-accomplice` on `20260412-dungeoncrawler-release-i` (dev outbox exists)
- `dc-cr-burrow-elocutionist` on `20260412-dungeoncrawler-release-i` (dev outbox exists)
- `dc-cr-downtime-mode` on `20260412-dungeoncrawler-release-d` (dev outbox exists)
- `dc-cr-feats-ch05` on `20260412-dungeoncrawler-release-d` (dev outbox exists)
- `dc-cr-first-world-adept` on `20260412-dungeoncrawler-release-i` (dev outbox exists)
- `dc-cr-first-world-magic` on `20260412-dungeoncrawler-release-i` (dev outbox exists)
- `dc-cr-gnome-heritage-fey-touched` on `20260412-dungeoncrawler-release-i` (dev outbox exists)
- `dc-cr-gnome-heritage-sensate` on `20260412-dungeoncrawler-release-d` (dev outbox exists)
- `dc-cr-gnome-heritage-umbral` on `20260412-dungeoncrawler-release-d` (dev outbox exists)
- `dc-cr-hazards` on `20260412-dungeoncrawler-release-d` (dev outbox exists)
- `dc-cr-magic-ch11` on `20260412-dungeoncrawler-release-d` (dev outbox exists)
- `dc-cr-rest-watch-starvation` on `20260412-dungeoncrawler-release-d` (dev outbox exists)
- `dc-cr-skills-society-create-forgery` on `20260412-dungeoncrawler-release-d` (dev outbox exists)
- `dc-gmg-hazards` on `20260412-dungeoncrawler-release-e` (dev outbox exists)

Reset stale features to `ready` / clear release, or mark them `done` if implementation already shipped.

## Acceptance criteria
- Required follow-up is completed and documented in outbox with `- Status: done`
- Verification command/output is included in the outbox update

## Verification
- `bash scripts/ceo-release-health.sh` should no longer report orphaned features for `dungeoncrawler`
