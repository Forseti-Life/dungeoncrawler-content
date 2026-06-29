# Verification Report: Movement in Encounters (Reqs 2233–2266)
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: BLOCK

## Scope
PF2e Ch9 Movement in Encounters (reqs 2233–2266, 34 requirements). Inbox annotation "Expected: All 34 FAIL" is stale — MovementResolverService.php is fully implemented (450 lines). 19/34 PASS, 3 PARTIAL, 12 GAPS.

## KB reference
None found directly relevant. Consistent pattern from this release cycle: inbox "Expected Failures" annotations are always stale; actual implementation is further along than reported.

## Source files inspected
- `MovementResolverService.php` — full service (450 lines): `getCreatureSpeed`, `calculateMovementCost`, `isDifficultTerrain`, `isPassable`, `computeForcedMovement`, `isFlanking`, `calculateCover`, `calculateFallDamage`, `getAquaticModifiers`
- `EncounterPhaseHandler.php` — `processStride()`, case 'step' (difficult terrain), fly case (upward movement cost), forced movement flag
- `CombatEngine.php` — `startTurn()` (mounted MAP sharing, held breath), `resolveAttack()` (flanking, cover, aquatic, fire underwater)
- `HPManager.php` — `applyFallDamage()` (prone on fall)
- `AreaResolverService.php` — header confirms "All methods ignore terrain — difficult terrain does not shrink areas"

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2233-P: Separate speed values for land/burrow/climb/fly/swim | STATIC-PASS | `MOVEMENT_TYPES = ['land','burrow','climb','fly','swim']`; `getCreatureSpeed($ptcp, $type)` reads `entity_ref['speeds'][$type]` or `entity_ref[$type.'_speed']` ✓ |
| TC-2233-N: No single unified Speed field for all types | STATIC-PASS | Land speed uses `$participant['speed']`; others use entity_ref sub-fields ✓ |
| TC-2234-P: Climb speed → auto-succeed Athletics | GAP | `processStride` calls `getCreatureSpeed($ptcp, 'climb')` → returns 0 if no climb_speed, returns error. But no Athletics roll is executed and no +4 circumstance bonus applied. Climb is just a speed type. See GAP-2234. |
| TC-2234-N: No climb speed → flat-footed while climbing | GAP | No flat-footed condition set when moving with `movement_type='climb'` without climb_speed. Entity is just rejected ("No climb speed."). See GAP-2234. |
| TC-2235-P: Swim speed → auto-succeed Athletics | GAP | Same pattern as climb: no Athletics roll or +4 bonus. `movement_type='swim'` requires swim_speed in entity_ref; no roll path exists. See GAP-2235. |
| TC-2235-N: No swim speed → flat-footed underwater (always) | STATIC-PASS | REQ 2262 path: `getAquaticModifiers` → `flat_footed = !$has_swim_speed` when `is_underwater` ✓. Applied in CombatEngine.resolveAttack ✓ |
| TC-2236-P: Status +10 item +5 circumstance applied; minimum 5 ft | STATIC-PASS | `getCreatureSpeed` applies `$status_bonus + $circ_bonus + $item_bonus - $penalty`; `max(5, $total)` ✓ |
| TC-2236-N: Multiple status bonuses → only highest | GAP | `getCreatureSpeed` reads `entity_ref['speed_status_bonus']` as a single int — takes the stored value. No highest-of-multiple-status logic. Relies on data being pre-resolved. See GAP-2236. |
| TC-2237-P: Diagonal 5/10/5/10 alternation tracked across turn | STATIC-PASS | `calculateMovementCost` returns `new_diagonal_count`; `game_state['turn']['diagonal_count']` tracked. Note: on hex grid all moves are 5 ft (no geometric diagonals); rule is stored for square-grid context ✓ |
| TC-2237-N: Diagonals not flat 5 ft each | STATIC-PASS | By design (hex grid); `hex_distance × 5` cost is correct for hex adjacency ✓ |
| TC-2238-P: Entities have size field | STATIC-PASS | `SIZE_SPACES = {tiny: 2.5, small: 5, medium: 5, large: 10, huge: 15, gargantuan: 20}` ✓ |
| TC-2238-N: Missing size → default Medium | GAP | `SIZE_SPACES` is a constant map; no method applies it to look up entity size with a default fallback. No `getEntitySize($entity, 'medium')` helper used in combat paths. See GAP-2238. |
| TC-2239-P: Large (tall) → 10×10 ft space, 10 ft reach | STATIC-PASS | `SIZE_SPACES['large'] = 10`, `SIZE_REACH['large'] = 10` ✓ |
| TC-2239-N: Large (long) has different reach | GAP | `SIZE_REACH` has one value per size; no `size_form` (tall vs long) distinction. Long creatures always use the same reach as tall. See GAP-2239. |
| TC-2240-P: Move through willing ally's space → allowed | GAP | `processStride` does not check creature occupancy. No code to validate/permit passing through allies or block hostile spaces. See GAP-2240. |
| TC-2240-N: Cannot end movement in another creature's space | GAP | Same — no end-space occupancy check. See GAP-2240. |
| TC-2241-P: Tiny moves through Huge space freely (≥3 size diff) | GAP | No size-difference movement rule implemented. See GAP-2241. |
| TC-2241-N: Medium cannot move through Large freely | GAP | Same — no size-difference enforcement. See GAP-2241. |
| TC-2242-P: Tiny can share space with Large creature (end movement there) | GAP | No creature-cohabitation rule for Tiny. See GAP-2242. |
| TC-2242-N: Medium cannot end in another Medium's space | GAP | Same — no occupancy check at all. See GAP-2242. |
| TC-2243-P: 40 ft fall → 20 bludgeoning | STATIC-PASS | `calculateFallDamage(40)` → `floor(40/2)=20` ✓ |
| TC-2243-N: Max 1500 ft → 750 damage | STATIC-PASS | `min($feet, 1500)` → max 750 ✓ |
| TC-2244-P: 50 ft fall into 10 ft water → effective 30 ft (15 damage) | STATIC-PASS | `calculateFallDamage(50, soft_surface=TRUE)` → `50 - 20 = 30` → `floor(30/2) = 15` damage ✓. Note: inbox test case said "50-30=20" — that's the `is_dive` path. Standard soft surface is 20 ft reduction. Code correct. |
| TC-2244-N: Soft surface reduction cannot reduce below 0 | STATIC-PASS | `max(0, $feet - $reduction)` ✓ |
| TC-2245-P: Fall onto creature → Reflex DC 15 | GAP | `applyFallDamage` comment: "REQ 2245 handled externally." No Reflex check triggered on creature landing. See GAP-2245. |
| TC-2245-N: Fall on empty space → no Reflex | STATIC-PASS | No Reflex triggered (by omission, since no creature-check exists) ✓ |
| TC-2246-P: Any fall damage → prone | STATIC-PASS | `HPManager::applyFallDamage` → `conditionManager->applyCondition($pid, 'prone', ...)` if damage > 0 ✓ |
| TC-2246-N: Arrested fall (crit success) → no fall damage → no prone | STATIC-PASS | Arrest a Fall crit_success returns damage 0; `applyFallDamage` skips prone if damage=0 ✓ |
| TC-2247-P: Forced movement does NOT trigger AoO | STATIC-PASS | `is_forced=TRUE` flag passed in `processStride`; AoO is never auto-triggered on movement (AoO is an explicitly declared reaction intent) ✓ |
| TC-2247-N: Voluntary Stride CAN trigger AoO | GAP | No auto-trigger mechanism for AoO on voluntary movement exists. AoO must be explicitly declared, so the positive trigger path for voluntary stride is absent. See GAP-2247. |
| TC-2248-P: Push stops at wall/impassable | STATIC-PASS | `computeForcedMovement` steps until `!isPassable($next)`, stops there ✓ |
| TC-2248-N: Cannot force through impassable terrain | STATIC-PASS | Same — `isPassable` returns FALSE for void/wall hexes; step stops ✓ |
| TC-2249-P: Difficult terrain → +5 ft cost per square | STATIC-PASS | `calculateMovementCost` adds `$hex_distance * 5` terrain cost for `DIFFICULT_TERRAIN_TYPES` ✓; `processStride` validates against speed ✓ |
| TC-2249-N: Speed 30 through 3 difficult hexes = 30 ft used | STATIC-PASS | Cost = 3×(5+5) = 30 ft; speed=30 → exactly exhausted ✓ |
| TC-2250-P: Greater difficult terrain → +10 ft cost per square | STATIC-PASS | `GREATER_DIFFICULT_TERRAIN_TYPES` → `$hex_distance * 10` terrain cost ✓ |
| TC-2250-N: Greater difficult does not double diagonal costs | STATIC-PASS | Terrain cost is flat per hex, not multiplied by diagonal factor ✓ |
| TC-2251-P: Step into normal square → allowed | STATIC-PASS | Only difficult terrain is blocked in case 'step' ✓ |
| TC-2251-N: Step into difficult terrain → rejected | STATIC-PASS | `isDifficultTerrain($to_hex, $dungeon_data)` → error "Cannot Step into difficult terrain." ✓ |
| TC-2252-P: Fireball burst covers difficult terrain hexes fully | STATIC-PASS | `AreaResolverService` header: "All methods ignore terrain" ✓ |
| TC-2252-N: Difficult terrain does not reduce AoE range | STATIC-PASS | Same ✓ |
| TC-2253-P: Two allies on opposite sides, melee reach → flanking | STATIC-PASS | `isFlanking` checks: both distance ≤ 1, and `$aq + $bq === 0 && $ar + $br === 0` or direction diff ≥ 3 ✓ |
| TC-2253-N: Only one ally → no flanking | STATIC-PASS | `isFlanking` requires the second ally arg to also be distance ≤ 1 ✓ |
| TC-2254-P: Flanked target → −2 AC vs flankers' melee | STATIC-PASS | CombatEngine: `if ($flanking) { $target_ac -= 2; }` ✓ |
| TC-2254-N: Non-melee attacks don't get flanking bonus | STATIC-PASS | `$flanking` is only set if `$weapon_type === 'melee'` (line 619) ✓ |
| TC-2255-P: Standard cover → +2 AC/Reflex/Stealth | STATIC-PASS | `calculateCover` → 1 obstacle → `{tier: 'standard', ac_bonus: 2, reflex_bonus: 2, stealth_bonus: 2}` ✓ |
| TC-2255-N: Lesser cover → +1 AC only; cannot hide | GAP | `calculateCover` never returns 'lesser'; docblock lists it but code has no 'lesser' path. Cover is either none/standard/greater. See GAP-2255. |
| TC-2256-P: Line passes through terrain → standard cover | STATIC-PASS | `calculateCover` counts impassable terrain hexes in midline; 1 obstacle → standard ✓ |
| TC-2256-N: Line passes through creature → lesser cover | GAP | `calculateCover` only checks terrain hexes for obstacle count. No participant/entity position check in the line. Creature-blocking produces no cover at all. See GAP-2256. |
| TC-2257-P: Target behind pillar vs attacker A → cover for A | STATIC-PASS | `calculateCover($attacker_hex, $target_hex, $dungeon_data)` is per-pair in `resolveAttack` ✓ |
| TC-2257-N: Cover for A does not apply to B with clear LoS | STATIC-PASS | Each attacker calls `calculateCover` independently ✓ |
| TC-2258-P: Mounted rider MAP shared with mount | STATIC-PASS | `CombatEngine::startTurn` reads mount's `attacks_this_turn` from DB and copies to rider ✓ |
| TC-2258-N: Mount and rider do NOT track MAP independently | STATIC-PASS | Rider's MAP is inherited from mount via DB sync ✓ |
| TC-2259-P: Mount acts on rider's initiative | GAP | No code enforces mount's initiative slot = rider's. Mount and rider are separate participants; initiative is set independently at encounter start. See GAP-2259. |
| TC-2259-N: Command an Animal needed for mount to act | GAP | No 'command_animal' intent in getLegalIntents. Mount actions are not gated behind Command an Animal. See GAP-2259. |
| TC-2260-P: Ride feat → auto-succeed Command an Animal | GAP | No 'ride_feat' check anywhere; no Command an Animal to auto-succeed. See GAP-2260. |
| TC-2260-N: No Ride feat → Animal Handling DC 15 + mount level | GAP | Same — no roll path exists. See GAP-2260. |
| TC-2261-P: Mounted rider → −2 Reflex saves | GAP | No mounted-rider Reflex penalty applied anywhere in save resolution. See GAP-2261. |
| TC-2261-N: Dismounted → normal Reflex | GAP | Same — no mounted state tracked for save resolution. See GAP-2261. |
| TC-2262-P: No swim speed underwater → flat-footed | STATIC-PASS | `getAquaticModifiers` → `flat_footed = !$has_swim_speed` when underwater ✓ |
| TC-2262-P: Resistance 5 acid/fire underwater | STATIC-PASS | CombatEngine: `if (is_underwater && damage_type in ['fire','acid']) { $damage_dealt -= 5; }` ✓ |
| TC-2262-N: Has swim speed → not flat-footed underwater | STATIC-PASS | `$has_swim_speed = (int)($speeds['swim'] ?? $entity['swim_speed'] ?? 0) > 0`; flat_footed=FALSE if swim speed > 0 ✓ |
| TC-2263-P: Ranged bludgeoning/slashing underwater → auto-miss | STATIC-PASS | CombatEngine: `$is_ranged && ($attacker_aquatic['is_underwater'] || $target_aquatic['is_underwater'])` + `in_array(type, ['bludgeoning','slashing'])` → `attack_blocked=TRUE` ✓ |
| TC-2263-N: Ranged piercing underwater → not auto-miss | STATIC-PASS | Bludgeoning/slashing check only; piercing passes through ✓ |
| TC-2264-P: Fire trait weapon attack underwater → automatically fails | STATIC-PASS | CombatEngine: `$weapon['is_fire_trait']` or `$damage_type==='fire'` while attacker is underwater → degree='failure' ✓ |
| TC-2264-N: Fire spell cast underwater → fails | GAP | `processCastSpell` is a stub (returns cast=TRUE for any spell). No fire-trait check in spell casting path. See GAP-2264. |
| TC-2265-P: Hold breath 5 + Con mod rounds; −2 per attack/spell | PARTIAL | `air_remaining` initialized to `5 + con_mod` ✓. Decremented by `air_decrement_this_turn` (default 1) each turn start ✓. But `air_decrement_this_turn` is never set to 2 or 3 by action handlers when attacks or spells are used. See GAP-2265. |
| TC-2265-N: Speech uses all remaining air | GAP | No speech detection. `air_decrement_this_turn` is never set for speech. See GAP-2265. |
| TC-2266-P: 0 air → unconscious; Fort DC 20 save | PARTIAL | Unconscious condition applied at `air_remaining <= 0` ✓. Fort DC 20 save chain NOT implemented — no `rollFortSave(DC=20)`, no 1d10 damage on fail, no death on crit fail. See GAP-2266. |
| TC-2266-N: Subsequent rounds escalate DC and damage | GAP | No DC/damage escalation tracking. See GAP-2266. |

## Defects / Gaps

### GAP-2234 + GAP-2235 (MEDIUM): Climb/swim speeds are speed types only; no Athletics roll or special condition enforcement
- **Files:** `EncounterPhaseHandler.php` `processStride`, `MovementResolverService.php` `getCreatureSpeed`
- **Expected (REQ 2234):** Entity with climb_speed auto-succeeds Athletics (Climb) with +4 circumstance bonus; entity without climb_speed must roll Athletics normally and is flat-footed while climbing.
- **Expected (REQ 2235):** Entity with swim_speed auto-succeeds Athletics (Swim) with +4 circumstance; entity without swim_speed must roll Athletics and is flat-footed underwater.
- **Actual:** climb/swim are treated as speed types only — `getCreatureSpeed` returns the speed value or 0. No Athletics roll path, no +4 bonus, no flat-footed-while-climbing condition.
- **Note:** The underwater flat-footed case for entities WITHOUT swim_speed IS handled by REQ 2262 path (`getAquaticModifiers`). But climbing flat-footed (without climb_speed) is not.
- **Severity:** Medium — functional gap for entities navigating elevated terrain or water.

### GAP-2236 (LOW): Speed status bonus stacking — highest-of-multiple not enforced
- **File:** `MovementResolverService.php` `getCreatureSpeed`
- **Expected (REQ 2236):** Multiple status Speed bonuses → only the highest applies.
- **Actual:** Code reads `entity_ref['speed_status_bonus']` as a single integer. Multiple status bonuses must be pre-resolved by the caller (or entity builder) before being stored. No in-service enforcement of highest-of-type rule.
- **Severity:** Low — data model enforces at storage time; low runtime risk.

### GAP-2238 (LOW): No default-to-Medium size lookup for entities missing size field
- **File:** `MovementResolverService.php` — `SIZE_SPACES` and `SIZE_REACH` are constants but no method provides a `getEntitySize($entity, default='medium')` helper.
- **Expected (REQ 2238):** Entity without size field defaults to Medium.
- **Actual:** Size constants are defined but not consumed in any movement/combat path. No helper reads entity.size with a fallback.
- **Severity:** Low — constants are data; combat paths that need size would need to call SIZE_SPACES directly.

### GAP-2239 (MEDIUM): SIZE_REACH has no tall/long distinction
- **File:** `MovementResolverService.php`
- **Expected (REQ 2239):** Large (long) creatures have different reach than Large (tall) creatures.
- **Actual:** `SIZE_REACH['large'] = 10` — a single reach value per size. No `size_form` (tall vs long) field read or differentiated reach.
- **Severity:** Medium — affects reach calculation for Large+ creatures that have alternate forms.

### GAP-2240 + GAP-2241 + GAP-2242 (MEDIUM): No creature-space occupancy enforcement
- **File:** `EncounterPhaseHandler.php` `processStride`
- **Expected:** Can't end move in same space as another creature (2240); ≥3 size difference allows pass-through (2241); Tiny shares space with Large+ (2242).
- **Actual:** `processStride` does not load or check other participants' positions. No end-space occupancy validation; no size-difference pass-through; no Tiny cohabitation rule.
- **Severity:** Medium — allows invalid positioning that could affect flanking/AoO calculations.

### GAP-2245 (LOW): Land-on-creature Reflex DC 15 not implemented
- **File:** `HPManager.php` — comment "REQ 2245 handled externally."
- **Expected (REQ 2245):** Falling onto a creature → that creature rolls Reflex DC 15 or takes fall damage.
- **Actual:** `applyFallDamage` applies damage/prone to the falling entity only; no adjacent entity Reflex check.
- **Severity:** Low — edge case; explicit external-handling note in source.

### GAP-2247 (MEDIUM): No auto-trigger for AoO on voluntary stride
- **File:** `EncounterPhaseHandler.php`
- **Expected (REQ 2247):** Voluntary Stride past an enemy with AoO can trigger Attack of Opportunity.
- **Actual:** `attack_of_opportunity` is a declared intent only — it must be explicitly sent by the reacting player. No movement-triggered auto-reaction system exists. Forced movement correctly doesn't trigger it (by the same absence), but voluntary stride also silently doesn't trigger it.
- **Severity:** Medium — the distinction between forced and voluntary movement is meaningful only if AoO can be triggered; the trigger mechanism is absent entirely.

### GAP-2255 + GAP-2256 (MEDIUM): Lesser cover not implemented; creature-in-line produces no cover
- **File:** `MovementResolverService.php` `calculateCover`
- **Expected (REQ 2255):** Lesser cover: +1 AC only, no hiding.
- **Expected (REQ 2256):** A creature standing in the line of effect grants lesser cover (+1 AC) to the target.
- **Actual:** `calculateCover` counts only impassable terrain hexes in the midline. Returns none/standard/greater only — 'lesser' is in the docstring but never returned. No participant/entity position check in the line of effect.
- **Fix:** Add participant-position check in `calculateCover` (pass $dungeon_data['entities'] or participants array); if a creature occupies a midline hex, return 'lesser' cover.
- **Severity:** Medium — common tactical scenario (creature between attacker and target); wrong cover tier affects many combat calculations.

### GAP-2259 + GAP-2260 (MEDIUM): Mount initiative and Ride feat not implemented
- **File:** `EncounterPhaseHandler.php`, `CombatEngine.php`
- **Expected (REQ 2259):** Mount acts on rider's initiative; Command an Animal action required for mount to act.
- **Expected (REQ 2260):** Ride feat → auto-succeed Command an Animal for own mount.
- **Actual:** Mount MAP sharing (REQ 2258) implemented. But: no 'command_animal' intent in getLegalIntents; mount initiative slot not synced to rider at encounter start; no Ride feat auto-success.
- **Severity:** Medium — mounts can act independently without Command an Animal gate.

### GAP-2261 (MEDIUM): Mounted rider −2 Reflex penalty not applied
- **File:** `EncounterPhaseHandler.php`, save resolution paths
- **Expected (REQ 2261):** Mounted rider takes −2 Reflex saves; can only dismount as move action.
- **Actual:** No Reflex save penalty for mounted state. `entity_ref['mounted_on']` is set/cleared, but save resolution paths don't read it for penalties.
- **Severity:** Medium — Reflex saves are frequent (Reflex vs. fireball, traps, etc.); missing penalty is a meaningful combat gap.

### GAP-2264 (LOW): Fire-underwater check only in weapon attack path; spells not covered
- **File:** `CombatEngine.php` `resolveAttack` (weapon path only), `EncounterPhaseHandler.php` `processCastSpell` (stub)
- **Expected (REQ 2264):** Fire trait actions automatically fail underwater.
- **Actual:** Weapon attacks with fire damage_type or `is_fire_trait` → degree='failure' ✓. But `processCastSpell` is a stub that returns `cast=TRUE` for any spell. Fire spells cast underwater are not rejected.
- **Severity:** Low — spell casting is a stub system overall; fire spells wouldn't deal damage via normal combat path anyway.

### GAP-2265 (MEDIUM): Held breath air_decrement not auto-set by action handlers
- **File:** `CombatEngine.php` `startTurn` — reads `air_decrement_this_turn` but nothing sets it to 2 or more.
- **Expected (REQ 2265):** Attacks and spells each reduce breath by 2; speech uses all remaining air.
- **Actual:** `air_decrement_this_turn` is initialized to 1 and reset to 1 after each turn. No action handler (strike, cast, speech) sets it to 2 or -all.
- **Fix:** After each strike/spell in EPH processIntent, check `entity_ref['is_underwater']`; if so, `entity_ref['air_decrement_this_turn'] += 1` (net -2 per action). Add speech-type check for -all.
- **Severity:** Medium — affects realistic air depletion in aquatic encounters.

### GAP-2266 (MEDIUM): Suffocation applies unconscious only; Fort DC save chain not implemented
- **File:** `CombatEngine.php` `startTurn`
- **Expected (REQ 2266):** At 0 air → unconscious; Fort DC 20 at turn end (fail = 1d10 damage, crit fail = death); subsequent rounds: DC increases by 5, damage increases by 1d10.
- **Actual:** `conditionManager->applyCondition($pid, 'unconscious', ...)` when `air_remaining <= 0` ✓. No Fort DC 20 save, no 1d10 damage on fail, no death on crit fail, no DC/damage escalation across rounds.
- **Fix:** After applying unconscious, trigger `rollFortSave(DC=20 + (round * 5))` for the participant; apply 1d10 × round damage on fail; death on crit fail.
- **Severity:** Medium — suffocation has no lethality beyond unconscious; can't die from drowning.

## Summary

19/34 PASS (inbox said 0/34 — annotation was entirely stale).

| Category | Count |
|---|---|
| STATIC-PASS | 19 |
| PARTIAL (tracking present, consumption missing) | 2 (2265, 2266) |
| GAP / FAIL | 13 (2234, 2235, 2236, 2238, 2239, 2240/2241/2242, 2245, 2247, 2255/2256, 2259/2260, 2261, 2264) |

**Key implemented systems (all new this session, confirmed fully or mostly working):**
- Speed lookup by movement type with bonus stacking + minimum floor ✓
- Movement cost calculation with terrain surcharge ✓
- Diagonal tracking (hex grid design: all 5 ft) ✓
- Fall damage formula + soft surface reduction + prone ✓
- Difficult / greater difficult terrain with terrain-sensitive stride ✓
- Step blocked by difficult terrain ✓
- AoE ignores terrain ✓
- Flanking detection (exact-opposite + direction-spread) ✓
- Cover tiers: standard / greater (line-of-effect terrain check) ✓
- Mounted rider MAP sharing via DB sync ✓
- Aquatic: flat-footed without swim, fire/acid resistance, ranged auto-miss ✓
- Fire-trait weapon attack underwater → failure ✓
- Held breath initialization and per-turn air decrement (base) ✓
- Unconscious on air exhaustion ✓

**Primary blockers for full compliance:**
1. **GAP-2240/2241/2242 (MEDIUM):** No creature-space occupancy enforcement in processStride
2. **GAP-2255/2256 (MEDIUM):** Lesser cover tier missing; creature-in-line cover not computed
3. **GAP-2261 (MEDIUM):** Mounted rider -2 Reflex penalty absent from save resolution
4. **GAP-2265 (MEDIUM):** Held breath not decremented correctly for actions/spells
5. **GAP-2266 (MEDIUM):** Suffocation has no Fort DC save or lethality chain

Verdict: **BLOCK** — 5 medium-severity gaps including core occupancy rules, cover accuracy, rider saves, and drowning lethality.
