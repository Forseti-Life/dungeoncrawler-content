# Implementation: dc-cr-conditions

- Agent: dev-dungeoncrawler
- Status: pending

- Feature id: dc-cr-conditions
- Release id: 20260322-dungeoncrawler-release-next
- Site: dungeoncrawler
- Module: dungeoncrawler_content
- AC file: features/dc-cr-conditions/01-acceptance-criteria.md
- Test plan: features/dc-cr-conditions/03-test-plan.md
- Implementation notes: features/dc-cr-conditions/02-implementation-notes.md

## Goal
Implement the PF2E conditions system: condition catalog, apply/remove, tick/decrement, dying/recovery rules, valued-condition enforcement, and restriction enforcement via RulesEngine.

## Acceptance criteria (reference AC file for full list)
- DB layer (applyCondition, removeCondition, getCurrentRound) verified — existing behavior holds
- Valued-condition enforcement on stat calculations (gap: partial)
- End-of-turn tick (tickConditions) — new implementation
- Dying/recovery rules (processDying) — new implementation
- RulesEngine::checkConditionRestrictions() — new (currently stub)

## Rollback
- Revert new service methods and any schema changes.
- If new DB columns added: provide migration rollback script in commit message.
- No production data risk (dev/local only).

## Verification
- All test cases in features/dc-cr-conditions/03-test-plan.md PASS
- `drush cr` + QA audit clean after changes

## Definition of done
- feature.md status updated to `in_progress`
- Implementation committed with rollback instructions in commit message
- Outbox includes commit hash(es) and any new routes introduced (for qa-permissions.json update)
