# QA Task: Senses, Detection, Hero Points, Encounter Mode (Reqs 2267–2289)

**Type:** qa  
**Section:** Ch9 — Senses and Detection, Hero Points, Encounter Mode Structure  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9

---

## Scope

23 requirements. Known implemented:
- 2279 (Hero Points max 3): tracked in `GameplayActionProcessor`
- 2283 (Initiative = Perception): `CombatEngine.startEncounter`
- 2284 (Sort descending + tie-break): `CombatEngine.startRound`
- 2285 (Order fixed unless Delay/knockout): persistent in encounter store
- 2287 (Delay re-entry): `CombatEngine.resumeFromDelay`
- 2289 (Ready doesn't change initiative): no initiative manipulation in ready

Expected to FAIL: 2267–2278, 2280–2282, 2286, 2288.

Key files:
- `CombatEngine.php` — `startEncounter()`, `startRound()`, `resumeFromDelay()`
- `GameplayActionProcessor.php` — hero_points tracking
- `NarrationEngine.php` — perception filtering (narrative only)
- `MapGeneratorService.php` — lighting data

---

## Test Cases: Senses and Detection

### REQ 2267 — Sense precision: precise/imprecise/vague
- **Positive:** Vision is precise (can make target observed); hearing is imprecise (max: hidden); smell is vague (max: undetected)
- **Negative:** Imprecise sense cannot make target "observed" (only hidden)
- **Expected FAIL:** No sense precision system in combat engine

### REQ 2268 — Default senses: vision (precise), hearing (imprecise), smell (vague)
- **Positive:** All entities default to vision/hearing/smell with correct precision
- **Negative:** Entity without special senses still has these three defaults
- **Expected FAIL:** Entity data lacks sense definitions

### REQ 2269 — Darkvision: see in darkness/dim light normally
- **Positive:** Entity with darkvision in dark room → can target normally (no blindness)
- **Negative:** Entity without darkvision in darkness → blinded
- **Expected FAIL:** Darkness not applied to combat targeting

### REQ 2270 — Greater darkvision: see through magical darkness
- **Positive:** Entity with greater_darkvision in magical darkness → can still see
- **Negative:** Normal darkvision in magical darkness → blinded
- **Expected FAIL:** Magical darkness not tracked

### REQ 2271 — Low-light vision: dim light = bright light
- **Positive:** Entity with low_light_vision in dim room → no concealment penalty
- **Negative:** Entity without low_light_vision in dim light → concealed (DC 5 flat check)
- **Expected FAIL:** Light level not applied to combat rolls

### REQ 2272 — Tremorsense (imprecise): detect movement on same surface
- **Positive:** Moving creature on same floor detected by tremorsense entity; max hidden
- **Negative:** Flying creature (not on surface) not detected by tremorsense
- **Expected FAIL:** No tremorsense implementation

### REQ 2273 — Scent (vague): detect by smell; wind modifies range
- **Positive:** Scent entity detects creature within range; target undetected at most
- **Negative:** Upwind direction: scent range doubled; downwind: halved
- **Expected FAIL:** No scent implementation

### REQ 2274 — Light levels: bright (normal), dim (concealed DC 5), darkness (blinded)
- **Positive:** Room with dim lighting → all creatures in it have concealed condition
- **Negative:** Darkness room → blinded applies; attacks need flat check to identify target
- **Expected FAIL:** Light level data exists in map but NOT applied to combat calculations

### REQ 2275 — Light source radius: bright to radius; dim to double radius
- **Positive:** Torch bright radius 20 ft → dim light to 40 ft
- **Negative:** Creatures beyond 40 ft are in darkness (not dim light)
- **Expected FAIL:** Light source mechanics not implemented

### REQ 2276 — Detection states: observed/hidden/undetected/unnoticed
- **Positive:** Correct state machine: hidden = know space; flat-footed; DC 11 flat check to attack
- **Negative:** Undetected → must guess square; DC unknown + attack roll vs "flat-footed ghost AC"
- **Expected FAIL:** Only a `hidden: bool` flag exists; no full state machine

### REQ 2277 — Concealed: DC 5 flat check before attack
- **Positive:** Attacking concealed target → flat check DC 5 first; fail = miss
- **Negative:** Concealed condition from dim light → still requires flat check even if observed
- **Expected FAIL:** No flat check for concealment in `CombatEngine.resolveAttack()`

### REQ 2278 — Invisible: automatically undetected to sight-only perceivers
- **Positive:** Invisible entity vs sight-only perceiver → undetected; found via Seek → hidden
- **Negative:** Invisible entity with sound-based perceiver → at most hidden (not undetected)
- **Expected FAIL:** No invisible detection state integration in combat engine

---

## Test Cases: Hero Points

### REQ 2279 — Hero Points: max 3; gained per session; lost at session end
- **Positive:** Character has 3 Hero Points; gaining more → capped at 3
- **Negative:** Hero Points NOT carried between sessions
- **Status:** ✅ EXPECTED PASS — `min(3, ...)` enforced in `GameplayActionProcessor`

### REQ 2280 — Spend 1 Hero Point: reroll any check, use second result (fortune)
- **Positive:** Player declares Hero Point reroll → d20 rerolled; must use new result
- **Negative:** Fortune effect cannot stack with another fortune (reroll the reroll not allowed)
- **Expected FAIL:** No mechanically enforced reroll trigger in combat engine

### REQ 2281 — Spend all Hero Points: stabilize at 0 HP (no wounded gained)
- **Positive:** Dying character spends all Hero Points → dying condition removed; no wounded added; remains at 0 HP
- **Negative:** Spending only 1 Hero Point → heroic recovery (see REQ 2171 in HP section); requires ALL points here
- **Expected FAIL:** Not implemented (separate from REQ 2171 heroic recovery)

### REQ 2282 — Hero Points can be spent for a familiar or animal companion
- **Positive:** GM-level: Hero Point spent on behalf of familiar → familiar rerolls a check
- **Negative:** Familiar cannot independently spend Hero Points; must be declared by owner
- **Expected FAIL:** No companion/familiar system

---

## Test Cases: Encounter Mode Structure

### REQ 2283 — Roll initiative at start; usually Perception
- **Positive:** `startEncounter()` rolls d20 + perception for each participant
- **Expected:** ✅ PASS

### REQ 2284 — Sort descending; enemy ties → foe first; PC ties → player choice
- **Positive:** Initiative sort in `startRound()` is descending; ties broken by perception mod
- **Negative:** PC vs PC tie → system should prompt player choice (currently auto-resolved)
- **Status:** Partial pass — perception tie-break ✅; PC choice on ties ❌

### REQ 2285 — Order fixed unless Delay or knockout
- **Positive:** Initiative stays stable across rounds; `resumeFromDelay()` allows changing it
- **Expected:** ✅ PASS

### REQ 2286 — Each round = 6 seconds in-world
- **Positive:** Round counter increments correctly; 6 seconds tracked for time-sensitive effects
- **Negative:** No actual in-game time tracking based on rounds (e.g., poison durations in seconds)
- **Expected FAIL:** Rounds counted but no second-level time tracking

### REQ 2287 — Delay: re-enter at chosen later position (permanent change)
- **Positive:** `resumeFromDelay(new_initiative)` updates participant's initiative permanently
- **Expected:** ✅ PASS

### REQ 2288 — Knockout at 0 HP: initiative shifts to just after attacker
- **Positive:** When reduced to 0 HP, entity's initiative updated to immediately after damaging attacker
- **Expected FAIL:** Not implemented (also see REQ 2153 in HP section)

### REQ 2289 — Ready action: initiative position unchanged
- **Positive:** Ready declared → no initiative manipulation
- **Expected:** ✅ PASS

---

## Summary

- ✅ Expected Pass: 2279, 2283, 2284 (partial), 2285, 2287, 2289
- ❌ Expected Fail: 2267–2278, 2280, 2281, 2282, 2286, 2288
- Agent: qa-dungeoncrawler
- Status: pending
