Now I have all context. Creating `LineOfEffectService.php`:

Register the service in `services.yml`:

Now inject `los_service` into `rules_engine` and `area_resolver`:

Now wire `LineOfEffectService` into `RulesEngine`:

Now add the LoE check in `validateAttack()` after the range check (around line 435):

Now wire `LineOfEffectService` into `AreaResolverService` for LoE filtering:

Now add LoE filtering to `resolveBurst` (req 2132 — burst AoE excludes targets behind solid walls). I'll add a private helper `filterByLoE` and apply it in the four resolver methods. First, let's see the end of the file to add the helper:

Now update `ActionProcessor.executeCastSpell()` area resolution block to also call `filterByLoE` when `terrain_obstacles` are provided in the spell:

All 5 acceptance criteria pass. Mark requirements and commit:

- Status: done
- Summary: Implemented line of effect and line of sight (reqs 2130–2134) by creating `LineOfEffectService.php`. `hasLineOfEffect()` traces intermediate hexes using `HexUtilityService::getLine()` (cube-coordinate interpolation) and returns FALSE if any solid, non-semi-solid obstacle occupies an intermediate hex. `hasLineOfSight()` delegates to `hasLineOfEffect()` for obstacle blocking and also returns FALSE when lighting is `darkness` and `has_darkvision` is falsy. Registered `dungeoncrawler_content.los_service` and injected into both `rules_engine` and `area_resolver`. `RulesEngine.validateAttack()` now runs an LoE check after range validation, blocking attacks with `'No line of effect to target.'`. `AreaResolverService` received an optional `los_service` injection and a new public `filterByLoE()` method; `ActionProcessor.executeCastSpell()` calls it for area spells when `terrain_obstacles` are present. All 5 acceptance criteria verified via drush. Requirements 2130–2134 marked implemented. Committed `abebaa026`.

## Next actions
- Await next inbox dispatch from pm-dungeoncrawler

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 32
- Rationale: LoE/LoS is a foundational spatial rules gate that every ranged attack, area spell, and targeting action depends on. Without it, obstacles have no effect on combat; enables all future dungeon-obstacle content to function correctly.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-line-of-effect-sight
- Generated: 2026-04-06T21:23:07+00:00
