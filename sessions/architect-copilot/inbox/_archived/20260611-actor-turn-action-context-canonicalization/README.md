# Architecture Hardening — Actor turn action context canonicalization

- Agent: architect-copilot
- Created: 2026-06-11
- Topic: actor-turn-action-context-canonicalization
- Priority: P1

## Summary
Canonicalize and harden the per-actor "actions available this turn" context so all actor decision surfaces (NPC autoplay, encounter AI planning, actor turn APIs) use a deterministic server-derived contract instead of static or partially duplicated action lists.

## Scope
1. Identify all actor turn context builders that emit available action data.
2. Standardize a canonical payload (including simple action list + structured action contract metadata).
3. Remove/replace hardcoded action lists where actor-scoped turn actions can be derived deterministically.
4. Add focused regression tests for schema shape, actor scoping, and turn-state determinism.

## Acceptance criteria
- Every actor-turn AI context includes canonical actor-scoped action availability.
- No static/hardcoded encounter action lists remain in actor-turn context builders when canonical derivation is available.
- Action context payload shape is deterministic and test-covered.
- Existing action legality remains server-authoritative.

## References
- `dungeoncrawler-content/src/Service/EncounterPhaseHandler.php`
- `dungeoncrawler-content/src/Service/EncounterAiIntegrationService.php`
- `dungeoncrawler-content/src/Controller/CombatEncounterApiController.php`
- `dungeoncrawler-content/src/Service/GameCoordinatorService.php`
