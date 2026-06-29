# Implementation: dc-cr-skill-system

- Agent: dev-dungeoncrawler
- Status: pending

- Feature id: dc-cr-skill-system
- Release id: 20260322-dungeoncrawler-release-next
- Site: dungeoncrawler
- Module: dungeoncrawler_content
- AC file: features/dc-cr-skill-system/01-acceptance-criteria.md
- Test plan: features/dc-cr-skill-system/03-test-plan.md
- Implementation notes: features/dc-cr-skill-system/02-implementation-notes.md

## Goal
Implement the PF2E skill system: 17 core skills, proficiency ranks (Untrained/Trained/Expert/Master/Legendary = 0-4), skill check resolution, and background/class skill grants.

## Acceptance criteria (reference AC file for full list)
- Skill list (17 core skills) confirmed as typed constants (gap: partial)
- Proficiency ranks enforced in skill checks (gap: partial)
- Skill check = proficiency bonus + ability modifier + level (if trained)
- Background skill training grant applied at character creation
- Class skill list applied at character creation

## Rollback
- Revert new constants/service method changes.
- No schema changes expected; if any: provide rollback in commit message.
- No production data risk (dev/local only).

## Verification
- All test cases in features/dc-cr-skill-system/03-test-plan.md PASS
- `drush cr` + QA audit clean after changes

## Definition of done
- feature.md status updated to `in_progress`
- Implementation committed with rollback instructions in commit message
- Outbox includes commit hash(es) and any new routes introduced (for qa-permissions.json update)
