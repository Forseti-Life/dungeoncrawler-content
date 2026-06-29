# Verification Report: Exploration Mode + Downtime Mode (Reqs 2290–2310)

**Decision: BLOCK**
**Score: 8/21 PASS** (inbox expected 1/21)
**Date:** 2026-04-07
**Agent:** qa-dungeoncrawler

---

## Summary

Implementation is significantly more advanced than inbox expected. ExplorationPhaseHandler.php has `calculateTravelSpeed()` and `processDailyPrepare()` implemented; DowntimePhaseHandler.php has full `processLongRest()`, `processRetrain()`, and `processAdvanceDay()`. REQ 2301 inbox claim of a bug is incorrect — processLongRest uses `max(1, $con_mod) * $level` formula correctly. REQs 2307–2310 (retrain system) are fully implemented.

Key gaps: travel speed calculations exist but are NOT wired to `processMove()` (hex movement updates position but ignores speed/terrain/activity); activity effects (avoid_notice, defend, detect_magic, scout) are stored in `game_state` but never applied to movement, encounter start, or any mechanical calculation.

---

## Test Results by Requirement

| REQ | Description | Result | Evidence |
|---|---|---|---|
| 2290 | Travel speed = base_speed × terrain | PARTIAL | `calculateTravelSpeed()` exists but NOT called from `processMove()`; hex position updated directly without speed math |
| 2291 | Difficult=½ speed; greater_difficult=⅓ speed | FAIL | Code uses `greater_difficult => 0.25` (¼); PF2e requires ~0.333 (⅓); difficult=0.5 ✓ |
| 2292 | Avoid Notice: ½ speed + Stealth initiative | PARTIAL | `avoid_notice` stored in `character_activities`; no half-speed in processMove; no Stealth initiative in CombatEngine::startEncounter |
| 2293 | Defend: ½ speed + shield raised | FAIL | Activity stored only; no speed reduction; no shield_raised state at encounter start |
| 2294 | Detect Magic: auto-detect auras | FAIL | Activity stored only; no aura detection logic |
| 2295 | Follow the Expert: proficiency bonus | FAIL | Not implemented |
| 2296 | Hustle: 2× speed; fatigue after Con×10 min | PARTIAL | `calculateTravelSpeed()` doubles speed for hustle; no Con mod timer; no fatigue trigger |
| 2297 | Scout: +1 party initiative in next encounter | FAIL | Not implemented; no party initiative bonus tracking |
| 2298 | Search: half speed + auto-Seek | PASS | `processSearch()` rolls Perception vs room search_dc; reveals hidden entities on success ✓ |
| 2299 | Investigate: secret Recall Knowledge | FAIL | Activity stored only; no recall knowledge rolls |
| 2300 | Repeat a Spell: auto-sustain | FAIL | Activity stored only; no spell sustain logic |
| 2301 | 8h rest: HP = Con_mod × level | PASS | `processLongRest()` line 260: `$hp_per_rest = max(1, $con_mod) * $level` ✓ (inbox bug claim incorrect) |
| 2302 | Sleep in armor: fatigued on waking | PASS | Lines 281–295: `hasArmorEquipped(['medium','heavy'])` → appends fatigued condition ✓ |
| 2303 | >16h awake: fatigued until 6h rest | PARTIAL | `hours_since_rest` reset to 0 on rest (line 300); NO >16h check that triggers fatigue; sleep deprivation never fires |
| 2304 | Daily prep: 1h, requires rest, invest items | PARTIAL | `processDailyPrepare()` exists, restores focus/daily abilities, costs 60 min; NO "requires rest first" gate; NO item investment logic |
| 2305 | Daily prep: once per 24h | PARTIAL | `last_daily_prepare` recorded (line 845); NO 24h cooldown check on re-entry |
| 2306 | Downtime rest: Con_mod × (2 × level) HP | PASS | DowntimePhaseHandler.php line 384: `max(1, $con_mod) * (2 * $level)` ✓ |
| 2307 | Retrain: 7 days per feat/skill | PASS | `processRetrain()` sets `days_required = 7`; `processAdvanceDay()` decrements and applies ✓ |
| 2308 | Cannot retrain: ancestry/heritage/class/etc. | PASS | Lines 426–430: `$prohibited` array validated; returns error ✓ |
| 2309 | Major retrain: 30 days, GM-approved | PASS | Lines 441–443: druid_order/wizard_school/sorcerer_bloodline → 30 days ✓ |
| 2310 | Cannot perform other downtime while retraining | PASS | Lines 432–435: blocks new retrain if `game_state['downtime']['retraining']` not empty ✓ |

---

## Defects

### GAP-2290 (MEDIUM) — calculateTravelSpeed() not wired to processMove()
- `ExplorationPhaseHandler::processMove()` updates `$entity['placement']['hex']` directly with no speed math
- `calculateTravelSpeed()` is a public utility method that correctly computes speed but is never called
- Fix: processMove should call calculateTravelSpeed with entity base_speed + current terrain + current activity, enforce max movement range per action

### DEF-2291 (LOW) — greater_difficult terrain multiplier wrong
- Code: `'greater_difficult' => 0.25` (¼ speed)
- PF2e REQ: ⅓ speed (~0.333)
- Fix: change 0.25 to `1/3`

### GAP-2292 (MEDIUM) — Activity effects not applied to movement or encounter start
- `avoid_notice`: `character_activities[$actor_id] = 'avoid_notice'` stored but:
  - processMove does not halve speed
  - CombatEngine::startEncounter does not read `character_activities` to substitute Stealth for Perception
- `defend`: same pattern — no half-speed, no shield_raised
- Fix: processMove reads `character_activities` and enforces speed modifier; CombatEngine::startEncounter reads `character_activities` for initiative substitution

### GAP-2296 (LOW) — Hustle fatigue timer missing
- calculateTravelSpeed doubles multiplier for hustle but no Con mod × 10 min timer tracked
- No fatigue condition applied after hustle limit exceeded
- Fix: track `hustle_minutes_elapsed` in exploration state; apply fatigued condition when `> max(1, $con_mod) * 10`

### GAP-2303 (LOW) — Sleep deprivation fatigue never fires
- `hours_since_rest` is reset to 0 on rest but never incremented as time passes
- No check that applies fatigued when `hours_since_rest > 16`
- Fix: `advanceExplorationTime()` should increment `hours_since_rest += ($minutes / 60)`; startTurn/advanceTime should check threshold

### GAP-2304 (LOW) — Daily prepare missing rest prerequisite and item investment
- `processDailyPrepare()` runs without checking `hours_since_rest == 0` (recent rest)
- No item investment logic (up to 10 magic items)
- Fix: gate on `$entity['state']['hours_since_rest'] == 0`; add item_investments array processing

### GAP-2305 (LOW) — No 24-hour cooldown on daily preparation
- `last_daily_prepare` is written as `time_elapsed_minutes` but never read on entry
- Can daily_prepare multiple times in same in-game day
- Fix: check `($current_time - $last_daily_prepare) >= 1440` (1440 min = 24h) before allowing

---

## KB Reference
- None found for exploration phase activity wiring pattern.

---

## BLOCK Rationale
GAP-2290 (travel speed not calculated) and GAP-2292 (activity effects not applied) mean exploration mode movement has no mechanical weight — all moves are distance-free and activity bonuses/penalties are silently ignored. These affect player-facing game feel on every exploration action. The downtime system (REQs 2306–2310) is fully implemented and should not block release independently.
