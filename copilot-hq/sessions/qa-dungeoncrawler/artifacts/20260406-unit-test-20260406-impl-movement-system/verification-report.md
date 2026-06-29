# Verification Report: Unit Test — 20260406-impl-movement-system (Reqs 2233–2266)
- Date: 2026-04-07
- Verifier: qa-dungeoncrawler
- Dev commit: e8740835f
- Verdict: BLOCK

## Scope
Targeted unit-test for completed dev item `20260406-impl-movement-system`. Reqs 2233–2266 (PF2e movement system). Primary source: `MovementResolverService.php`, `CombatEngine.php`, `HPManager.php`, `EncounterPhaseHandler.php`.

## Prior report reference
Roadmap verification `20260406-roadmap-req-2233-2266-movement` (QA BLOCK committed `9c0226579`) was performed AFTER dev commit `e8740835f` (22:35 UTC). No fix commits have landed since. This unit-test confirms those findings remain current.

Full test evidence: `sessions/qa-dungeoncrawler/artifacts/verification-reports/20260406-roadmap-req-2233-2266-movement.md`

## KB reference
None found directly relevant in `knowledgebase/`.

## Confirmed defect status (verified 2026-04-07 by source inspection)

All medium gaps from the roadmap BLOCK remain unpatched:

### GAP-2234/2235 (MEDIUM) — Climb/swim require Athletics rolls and conditions
- `getCreatureSpeed()` returns climb/swim speed as numeric values — treated same as land speed
- No Athletics auto-success roll (with +4 bonus for creatures with climb/swim speed)
- No flat-footed penalty while climbing without climb_speed
- **Still open:** No Athletics roll path in MovementResolverService

### GAP-2239 (MEDIUM) — SIZE_REACH has no tall/long distinction
- `SIZE_REACH` constant is a flat lookup by size name
- PF2e reach depends on whether a creature is Tall or Long (upright vs. prone silhouette)
- **Still open:** Single reach value per size, no tall/long distinction

### GAP-2240/2241/2242 (MEDIUM) — Occupancy rules absent
- `processStride()` moves entity to destination hex with no occupancy check
- Cannot detect if a larger creature can pass through vs. must stop
- No Tiny cohabitation (multiple Tiny creatures can share a space)
- **Still open:** No occupancy check in EncounterPhaseHandler::processStride()

### GAP-2247 (MEDIUM) — Voluntary stride has no AoO auto-trigger
- `processStride()` sets `last_move_type='stride'` but no AoO trigger emitted
- Reactions from adjacent enemies with AoO must be manually fired by client
- **Still open:** No reaction-trigger event in stride case

### GAP-2255/2256 (MEDIUM) — Lesser cover not implemented; creature-in-line provides no cover
- `calculateCover()` returns: none, standard, greater — no `lesser` tier
- PF2e REQ 2256: a creature between attacker and target grants lesser cover to target
- **Still open:** No lesser cover branch in calculateCover()

### GAP-2259/2260 (MEDIUM) — Mount initiative / command_animal / Ride feat
- `shiftInitiativeAfterAttacker()` does not sync mount initiative to rider
- No `command_animal` action in getLegalIntents() for mounted combat
- No Ride feat auto-success for trained mounts
- **Still open:** No mount-initiative sync in CombatEngine

### GAP-2261 (MEDIUM) — Mounted rider -2 Reflex saves not applied
- No penalty applied to rider's Reflex saves in `resolveAttack` or save handlers
- **Still open:** No mounted reflex modifier in save resolution

### GAP-2265 (MEDIUM) — air_decrement_this_turn not set >1 by action handlers
- `startTurn()` decrements `air_remaining` by `air_decrement_this_turn` (which defaults to 1)
- Attacks and spells should set `air_decrement_this_turn=3` for that turn
- **Still open:** No action handler in EncounterPhaseHandler sets this field

### GAP-2266 (MEDIUM) — Suffocation missing Fort DC save and 1d10 damage
- `air_remaining <= 0` triggers unconscious only
- REQ 2266: suffocation requires Fort DC 20 save each round; fail → dying; 1d10 piercing if already drowning
- No Fort save or 1d10 damage in startTurn suffocation path
- **Still open:** No save/damage in CombatEngine::startTurn suffocation block

## PASS summary (19/34 unchanged)
2233✓ 2236✓ 2243✓ 2244✓ 2246✓ 2247(stored)✓ 2248✓ 2249✓ 2250✓ 2251✓ 2252✓ 2253✓ 2254✓ 2255(standard+greater)✓ 2257✓ 2258✓ 2262✓ 2263✓ 2264✓

## Site audit
Not re-run (run 20260407-014054 already clean — 0 errors, 0 permission violations, 0 config drift).

## Verdict: BLOCK
Nine medium-severity gaps. Key blocking defects: occupancy rules (GAP-2240/2241/2242) allow invalid movement; lesser cover (GAP-2255/2256) means all cover-seeking tactics use wrong tier; suffocation chain (GAP-2266) is incomplete.
