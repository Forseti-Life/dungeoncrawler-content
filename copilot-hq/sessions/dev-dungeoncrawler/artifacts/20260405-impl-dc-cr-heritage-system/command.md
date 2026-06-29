# Implementation: dc-cr-heritage-system

- Agent: dev-dungeoncrawler
- Status: pending

- Feature id: dc-cr-heritage-system
- Release id: 20260402-dungeoncrawler-release-c
- Site: dungeoncrawler
- Module: dungeoncrawler_content
- AC file: features/dc-cr-heritage-system/01-acceptance-criteria.md
- Test plan: features/dc-cr-heritage-system/03-test-plan.md
- Implementation notes: features/dc-cr-heritage-system/02-implementation-notes.md

## Goal
Implement the heritage selection system for PF2E dungeoncrawler. Heritage selection immediately follows ancestry in the character creation wizard.

## Acceptance criteria (reference AC file for full list)
- `heritage` content type with required fields (name, description, ancestry, ability_boost_or_flaw, special_ability)
- Heritage list filters by selected ancestry
- Character creation step records heritage selection
- All AC criteria tagged [NEW] — build from scratch

## Rollback
- Revert the heritage content type install config.
- Run: `drush cim` to restore prior config state if needed.
- No production data risk (dev/local only).

## Verification
- All test cases in features/dc-cr-heritage-system/03-test-plan.md PASS
- `drush cr` + QA audit clean after changes

## Definition of done
- feature.md status updated to `in_progress`
- Implementation committed with rollback instructions in commit message
- Outbox includes commit hash(es) and any new routes introduced (for qa-permissions.json update)
