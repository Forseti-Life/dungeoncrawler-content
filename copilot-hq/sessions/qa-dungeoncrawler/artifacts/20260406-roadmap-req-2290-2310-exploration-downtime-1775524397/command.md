# QA Task: Exploration Mode + Downtime Mode (Reqs 2290–2310)

**Type:** qa  
**Section:** Ch9 — Exploration Mode and Downtime Mode  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9 — "Exploration Mode" and "Downtime Mode"

---

## Scope

21 requirements (2290–2310). Only REQ 2298 (Search) is expected to pass.  
All others are stubs or not implemented.

Key files:
- `ExplorationPhaseHandler.php` — `set_activity`, `search`, `rest` cases
- `DowntimePhaseHandler.php` — `retrain` (stub), `processLongRest()`

---

## Test Cases: Exploration Mode

### REQ 2290 — Travel speed scales with land Speed
- **Positive:** Character with Speed 30 → 24 miles/day; Speed 25 → 20 miles/day
- **Negative:** System does NOT return a static miles/day value for all characters
- **Expected FAIL:** No travel speed calculation in `ExplorationPhaseHandler`

### REQ 2291 — Difficult terrain: half travel speed; greater difficult: 1/3 speed
- **Positive:** Traversing difficult terrain region → miles/day halved
- **Negative:** Greater difficult terrain → 1/3 (not 1/2)
- **Expected FAIL:** No terrain-based travel modifier

### REQ 2292 — Avoid Notice: half speed; Stealth for initiative
- **Positive:** `set_activity` with `avoid_notice` → movement at half speed; at encounter start, Stealth replaces Perception for initiative
- **Negative:** Characters not using Avoid Notice use Perception for initiative
- **Expected FAIL:** Activity stored but mechanics not applied

### REQ 2293 — Defend: half speed; shield Raised entering encounter
- **Positive:** `set_activity` with `defend` → speed halved; character starts encounter with shield raised
- **Negative:** Character without shield → Defend still halves speed (effect is protection stance, not shield-specific)
- **Expected FAIL:** Activity stored but no effect

### REQ 2294 — Detect Magic: half speed; auto-detect magic auras
- **Positive:** `set_activity` with `detect_magic` → magic items/auras in room auto-detected
- **Negative:** Without Detect Magic activity → must use Seek/Recall Knowledge for magic detection
- **Expected FAIL:** Not implemented

### REQ 2295 — Follow the Expert: match ally; gain proficiency bonus
- **Positive:** Character follows ally with Expert Stealth → gains trained+1 (expert) bonus to Stealth
- **Negative:** Only works when following an ally performing the same activity
- **Expected FAIL:** Not implemented

### REQ 2296 — Hustle: double travel speed; limit Con mod × 10 min (min 10 min)
- **Positive:** Hustle active → speed doubled for Con mod × 10 minutes; then fatigued if continued
- **Negative:** Cannot Hustle for more than Con mod × 10 minutes without fatigue
- **Expected FAIL:** Not implemented

### REQ 2297 — Scout: +1 circumstance to all party initiative rolls in next encounter
- **Positive:** Scout activity → next encounter all party members get +1 initiative
- **Negative:** Scout bonus does not stack with itself (only applies once per encounter)
- **Expected FAIL:** Not implemented

### REQ 2298 — Search: half speed; auto-Seek hazards, secret doors, hidden objects
- **Positive:** `search` action → Perception check vs hazard/hidden object DCs
- **Negative:** Hidden objects not found on failed Perception check
- **Status:** ✅ EXPECTED PASS — search case exists in ExplorationPhaseHandler

### REQ 2299 — Investigate: half speed; secret Recall Knowledge for clues
- **Positive:** `set_activity` with `investigate` → secret Recall Knowledge rolls for environmental clues
- **Negative:** GM receives results secretly; player does not see raw roll
- **Expected FAIL:** Not implemented

### REQ 2300 — Repeat a Spell: half speed; sustain or re-cast spell
- **Positive:** `set_activity` with `repeat_spell` → spell sustained each round automatically
- **Negative:** Cannot Repeat a Spell if concentration spell isn't active
- **Expected FAIL:** Not implemented

### REQ 2301 — 8-hour rest: regain HP = Con mod (min 1) × level
- **Positive:** Long rest → HP restored by Con mod × level (not full HP)
- **Negative:** `processLongRest()` currently restores full HP → EXPECTED FAIL (bug)
- **Expected FAIL:** Code restores `max_hp` unconditionally instead of `con_mod × level`

### REQ 2302 — Sleeping in armor: fatigued on waking
- **Positive:** Rest with armor equipped → wake with `fatigued` condition
- **Negative:** Rest without armor → no fatigued condition applied
- **Expected FAIL:** Not implemented

### REQ 2303 — >16 hours without rest: fatigued until ≥6 continuous hours rest
- **Positive:** `in_world_seconds` tracking shows >16 hours awake → fatigued condition applied
- **Negative:** Resting 6 continuous hours removes fatigued from no-sleep cause
- **Expected FAIL:** No awake-time tracking

### REQ 2304 — Daily preparations: 1 hour, requires rest; regain spell slots, invest items
- **Positive:** After long rest, 1-hour preparation → spell slots restored; up to 10 magic items invested
- **Negative:** Daily preparations cannot be done without rest first
- **Expected FAIL:** Not implemented

### REQ 2305 — Can only prepare once per 24 hours
- **Positive:** Second attempt to prepare within 24 hours → rejected
- **Negative:** New day starts → can prepare again
- **Expected FAIL:** No daily prep cooldown tracking

---

## Test Cases: Downtime Mode

### REQ 2306 — Downtime long-term rest: HP = Con mod (min 1) × (2 × level)
- **Positive:** Downtime rest → Con_mod × (2 × level) HP restored (more than normal 8-hour rest)
- **Negative:** Normal rest and downtime rest return different HP amounts
- **Expected FAIL:** Not implemented as distinct from normal rest

### REQ 2307 — Retraining: 1 week per feat/skill increase swap
- **Positive:** `retrain` action with `retrain_type: feat` → after 7 downtime days, old feat removed, new feat selected
- **Negative:** Retraining takes 7 days, not instantaneous
- **Expected FAIL:** `retrain` is a stub in `DowntimePhaseHandler`

### REQ 2308 — Cannot retrain: ancestry, heritage, background, class, ability scores
- **Positive:** Attempt to retrain class → error response
- **Negative:** Retrain feat of matching type/level → allowed
- **Expected FAIL:** Not validated (stub)

### REQ 2309 — Larger changes (druid order, wizard school): ≥1 month, GM-approved
- **Positive:** Retrain wizard school subclass → requires 30 downtime days and GM confirmation
- **Negative:** Smaller retrain (swap one skill feat) → 7 days only
- **Expected FAIL:** Not implemented

### REQ 2310 — Cannot perform other downtime activities while retraining
- **Positive:** Active `retrain` in progress → other downtime actions rejected
- **Negative:** Retraining ends → other activities available again
- **Expected FAIL:** No activity lock system

---

## Summary

- ✅ Expected Pass: 2298 (Search)
- ❌ Expected Fail: 2290–2297, 2299–2310 (20 requirements)
- 🐛 Known Bug: 2301 — `processLongRest()` sets HP = max_hp instead of Con_mod × level
- Agent: qa-dungeoncrawler
- Status: pending
