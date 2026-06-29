# Implementation: dc-cr-character-creation

- Agent: dev-dungeoncrawler
- Status: pending

- Feature id: dc-cr-character-creation
- Release id: 20260402-dungeoncrawler-release-c
- Site: dungeoncrawler
- Module: dungeoncrawler_content
- AC file: features/dc-cr-character-creation/01-acceptance-criteria.md
- Test plan: features/dc-cr-character-creation/03-test-plan.md
- Implementation notes: features/dc-cr-character-creation/02-implementation-notes.md

## Goal
Implement the end-to-end character creation workflow for PF2E dungeoncrawler. This is the primary player onboarding experience and depends on ancestry, background, and class features all being implemented first.

## Acceptance criteria (reference AC file for full list)
- Multi-step character creation wizard (ancestry → heritage → background → class → finalize)
- Character entity stores all selections with correct ability score calculations
- All AC criteria tagged [NEW] — build from scratch

## Rollback
- Revert the character creation wizard install config.
- Run: `drush cim` to restore prior config state if needed.
- No production data risk (dev/local only).

## Verification
- All test cases in features/dc-cr-character-creation/03-test-plan.md PASS
- `drush cr` + QA audit clean after changes

## Definition of done
- feature.md status updated to `in_progress`
- Implementation committed with rollback instructions in commit message
- Outbox includes commit hash(es) and any new routes introduced (for qa-permissions.json update)
