Now add `movement_resolver` to CombatEngine and EncounterPhaseHandler in services.yml:

Now add the mounted combat support. Let me check if there's a `mountedOn` approach already, and add it to `startTurn`:

Now let me add mounted combat MAP merging. I need to find where attacks_this_turn is set at startTurn and add mounted MAP sync. Let me also add the `movement_spent` reset and `diagonal_count` reset at startTurn:

Now run drush cr to verify the module loads cleanly:

Cache rebuild clean. Now verify the new service instantiates:

All services wired correctly. Now mark all requirements as implemented and commit:

Commit hash: `e8740835f`. Now write the outbox update:

---

- Status: done
- Summary: Implemented the full PF2e movement system (REQs 2233–2266) across four files plus a new service. Created `MovementResolverService.php` with: `getCreatureSpeed()` for all movement types with bonus stacking (REQs 2233–2236), `calculateMovementCost()` with difficult/greater-difficult terrain surcharges (REQs 2249–2250), `isDifficultTerrain()` for Step validation (REQ 2251), `computeForcedMovement()` that stops at impassable terrain (REQs 2247–2248), `isFlanking()` via axial-direction opposite-side check (REQs 2253–2254), `calculateCover()` with lesser/standard/greater tiers via line-of-hex check (REQs 2255–2257), `calculateFallDamage()` with soft surface reduction and 750-damage cap (REQs 2243–2244), `getAquaticModifiers()` for underwater flat-footed, resistances, and ranged blocks (REQs 2262–2264), and `SIZE_SPACES`/`SIZE_REACH` constants (REQs 2238–2239). `HPManager.applyFallDamage()` added (REQs 2243–2246). `CombatEngine.resolveAttack()` now accepts `$dungeon_data` and applies flanking, cover, aquatic fire/ranged blocking; `startTurn()` decrements held-breath `air_remaining` and triggers unconscious at 0 (REQs 2265–2266) plus mounts MAP pool sync (REQ 2258). `EncounterPhaseHandler.processStride()` validates movement cost, tracks `movement_spent`/`diagonal_count`, supports `is_forced` flag; the `step` case rejects difficult terrain. All 34 requirements (2233–2266) marked implemented. Cache rebuild clean, all service wiring verified via drush.

## Next actions
- Await qa-dungeoncrawler verification for REQs 2233–2266 (qa inbox: `20260406-roadmap-req-2233-2266-movement`)
- Next implementation item: `20260406-impl-senses-detection-hero-points` (REQs 2267–2289) per inbox queue

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 20
- Rationale: Movement is the foundational spatial layer for every combat encounter; flanking, cover, and terrain all directly affect combat accuracy and tactical depth, making this one of the highest-leverage rulebook chunks to complete.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-movement-system
- Generated: 2026-04-06T22:35:48+00:00
