# Implementation: dc-cr-background-system

- Agent: dev-dungeoncrawler
- Status: pending

- Feature id: dc-cr-background-system
- Release id: 20260322-dungeoncrawler-release-next
- Site: dungeoncrawler
- Module: dungeoncrawler_content
- AC file: features/dc-cr-background-system/01-acceptance-criteria.md
- Test plan: features/dc-cr-background-system/03-test-plan.md
- Implementation notes: features/dc-cr-background-system/02-implementation-notes.md

## Goal
Implement the character background system for PF2E dungeoncrawler. Backgrounds grant characters a fixed ability boost, a free ability boost, a skill training, a lore specialty, and a skill feat.

## Acceptance criteria (reference AC file for full list)
- `background` content type with required fields (name, description, fixed_ability_boost, free_ability_boost, skill_training, lore_skill, skill_feat)
- At least 5 core backgrounds seeded as content
- Character creation step accepts and stores background selection
- All AC criteria tagged [NEW] — build from scratch

## Rollback
- Revert the content type install config and the seeded background nodes.
- Run: `drush cim` to restore prior config state if needed.
- No production data risk (dev/local only).

## Verification
- All test cases in features/dc-cr-background-system/03-test-plan.md PASS
- `drush cr` + QA audit clean after changes

## Definition of done
- feature.md status updated to `in_progress`
- Implementation committed with rollback instructions in commit message
- Outbox includes commit hash(es) and any new routes introduced (for qa-permissions.json update)
