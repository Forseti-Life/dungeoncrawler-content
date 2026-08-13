# Unified combat runtime Slice A authority matrix

- Slice: `A - contract freeze`
- Scope: code-anchored producer/consumer + authority-state mapping
- Date: 2026-08-13

## Canonical schema package delivered

1. `config/schemas/combat_execution_request.schema.json`
2. `config/schemas/damage_application_request.schema.json`
3. `config/schemas/combat_event.schema.json`

## Lane matrix (producer -> consumer -> authority state)

| Lane | Primary producer (code seam) | Primary consumer (code seam) | Authority state | Evidence anchors |
|---|---|---|---|---|
| Strike execution + damage | `src/Service/EncounterActionExecutor.php::processStrike()` routes packet/mutation emission through `src/Service/UnifiedDamageEngine.php::resolveStrikeDamage()` | `src/Service/EncounterPhaseHandler.php` strike `GameEventLogger::buildEvent('strike', ...)`; UI fallback read in `js/v2/panels/ChatPanel.js` | authoritative | `EncounterActionExecutor.php` L267+, `UnifiedDamageEngine.php::resolveStrikeDamage()`, `CombatEngine.php::resolveAttack()` L572+, `EncounterPhaseHandler.php` L7367+, `ChatPanel.js` L3046+ |
| Spell execution + damage | `src/Service/EncounterActionExecutor.php::processCastSpell()` routes supported deterministic spell damage through `src/Service/UnifiedDamageEngine.php::applySupportedSpellDamageToEncounterTarget()` | `src/Service/EncounterPhaseHandler.php` cast-spell event payload + UI fallback packet read | hybrid (Magic Missile onboarded) | `EncounterActionExecutor.php` L681+, `UnifiedDamageEngine.php::applySupportedSpellDamageToEncounterTarget()`, `EncounterPhaseHandler.php` L7575+, `ChatPanel.js` L3046+ |
| Hazard damage during forced movement/terrain | `src/Service/EncounterPhaseHandler.php::resolveEncounterTerrainHazardForMovement()` path emits hazard packet/events | Same handler emits `hazard_triggered` + movement event payloads; projected via event stream consumers | bypass | `EncounterPhaseHandler.php` L4222+, L4256+, L4287+ |
| Movement resolution (stride/forced) | `src/Service/EncounterActionExecutor.php::processStride()` emits `movement_packet` + envelope | `src/Service/EncounterPhaseHandler.php` stride/shove event payloads + UI movement packet render | hybrid | `EncounterActionExecutor.php` L409+, L524+, `EncounterPhaseHandler.php` L4272+, L7490+, `ChatPanel.js` L3047+ |
| State/effect lifecycle packetization | `src/Service/EncounterPhaseHandler.php` builds `state_effect_packet(s)` through `CombatResolutionContractService`; underlying writes in `ConditionManager` and `EffectInstanceService` paths | Event payloads emitted by handler; consumed by projection/UI packet readers | hybrid | `EncounterPhaseHandler.php` L3987+, L4592+, `src/Service/ConditionManager.php`, `src/Service/EffectInstanceService.php`, `ChatPanel.js` L3048+ |
| Reactions/interrupts | `src/Service/ReactionHandler.php` owns trigger/availability; handler emits `reaction_packet` when present | `src/Service/EncounterPhaseHandler.php` strike event includes reaction metadata for downstream projection | hybrid | `ReactionHandler.php` class + check methods, `EncounterPhaseHandler.php` L7336+, L7370+ |
| Event envelope + projection | `src/Service/GameEventLogger.php::buildEvent()` is canonical event builder for encounter lane | `src/Service/CanonicalProjectionService.php`, `src/Service/MapVisualStateProjector.php`, and client panels consume projected state/events | hybrid | `GameEventLogger.php` L361+, `CanonicalProjectionService.php` class, `MapVisualStateProjector.php::project()` L36+ |

## Mixed-shape contract risks explicitly frozen for follow-on slices

1. Spell and hazard damage still enter packet emission through different seams.
2. State/effect writes remain split across condition vs persistent effect flows.
3. Reactions are not yet a universal middleware pass for all mutation lanes.
4. Projection consumers still read partially lane-specific packet fields.

These are intentionally preserved in Slice A; they are migration targets for
resolver extraction and unified-damage onboarding slices.
