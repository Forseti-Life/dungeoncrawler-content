# Implementation: dc-cr-character-class

- Agent: dev-dungeoncrawler
- Status: pending

- Feature id: dc-cr-character-class
- Release id: 20260322-dungeoncrawler-release-next
- Site: dungeoncrawler
- Module: dungeoncrawler_content
- AC file: features/dc-cr-character-class/01-acceptance-criteria.md
- Test plan: features/dc-cr-character-class/03-test-plan.md
- Implementation notes: features/dc-cr-character-class/02-implementation-notes.md

## Goal
Implement the character class system for PF2E dungeoncrawler. Classes define key ability, hit points per level, proficiency progression, and class features.

## Acceptance criteria (reference AC file for full list)
- `character_class` content type with required fields (name, description, key_ability, hit_points_per_level, proficiencies, class_features)
- All 12 core PF2E classes seeded as content
- Character creation step accepts and stores class selection
- All AC criteria tagged [NEW] — build from scratch

## Rollback
- Revert the content type install config and seeded class nodes.
- Run: `drush cim` to restore prior config state if needed.
- No production data risk (dev/local only).

## Verification
- All test cases in features/dc-cr-character-class/03-test-plan.md PASS
- `drush cr` + QA audit clean after changes

## Definition of done
- feature.md status updated to `in_progress`
- Implementation committed with rollback instructions in commit message
- Outbox includes commit hash(es) and any new routes introduced (for qa-permissions.json update)
