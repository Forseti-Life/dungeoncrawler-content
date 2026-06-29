# Implementation: dc-cr-encounter-rules

- Agent: dev-dungeoncrawler
- Status: pending

- Feature id: dc-cr-encounter-rules
- Release id: 20260402-dungeoncrawler-release-c
- Site: dungeoncrawler
- Module: dungeoncrawler_content
- AC file: features/dc-cr-encounter-rules/01-acceptance-criteria.md
- Test plan: features/dc-cr-encounter-rules/03-test-plan.md
- Implementation notes: features/dc-cr-encounter-rules/02-implementation-notes.md

## Goal
Implement the encounter and combat rules system for PF2E dungeoncrawler. This is the primary gameplay loop and depends on dice and difficulty class features.

## Acceptance criteria (reference AC file for full list)
- Encounter structure (initiative, turns, actions) per PF2E rules
- Attack/defense resolution using dice and DC system
- All AC criteria tagged [NEW] — build from scratch

## Rollback
- Revert encounter rules install config.
- Run: `drush cim` to restore prior config state if needed.
- No production data risk (dev/local only).

## Verification
- All test cases in features/dc-cr-encounter-rules/03-test-plan.md PASS
- `drush cr` + QA audit clean after changes

## Definition of done
- feature.md status updated to `in_progress`
- Implementation committed with rollback instructions in commit message
- Outbox includes commit hash(es) and any new routes introduced (for qa-permissions.json update)
