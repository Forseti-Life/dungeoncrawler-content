# QA Task: Specialty Basic Actions + Reactions in Encounters (Reqs 2219–2232)

**Type:** qa  
**Section:** Ch9 — Specialty Basic Actions and Reactions in Encounters  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9

---

## Scope

All 14 requirements (2219–2232) are expected to be NOT IMPLEMENTED.  
Verify by attempting each action via the encounter API. Document the error response.

Key file: `EncounterPhaseHandler.php` — `getLegalIntents()` and action dispatch switch.

---

## Test Cases

### REQ 2219 — Arrest a Fall (reaction): Acrobatics DC 15; crit fail = fall damage
- **Positive:** Creature with fly Speed falls → Arrest a Fall reaction triggers; success = no fall damage
- **Negative:** Creature without fly Speed → Arrest a Fall not available
- **Expected:** NOT in `getLegalIntents()` → error response

### REQ 2220 — Avert Gaze: +2 circumstance to saves vs gaze abilities until next turn
- **Positive:** Avert Gaze declared → `avert_gaze_active` flag set; next gaze save gets +2
- **Negative:** Avert Gaze expires at start of next turn (not permanent)
- **Expected:** NOT in `getLegalIntents()` → error response

### REQ 2221 — Burrow: 1 action; requires burrow Speed; no tunnel unless ability says so
- **Positive:** Entity with burrow_speed > 0 uses Burrow → moves through ground
- **Negative:** Entity without burrow_speed → Burrow rejected
- **Expected:** NOT in `getLegalIntents()` → error response

### REQ 2222 — Fly: 1 action; upward = difficult terrain cost; fall at end of turn if no Fly used
- **Positive:** Entity with fly_speed > 0 uses Fly → moves to target hex in air
- **Negative:** Moving upward costs double movement (difficult terrain); not treating upward as flat
- **Expected:** NOT in `getLegalIntents()` → error response

### REQ 2223 — Fly 0 feet = hover (stay airborne without moving)
- **Positive:** `fly` action with `distance: 0` → entity stays in place, `airborne: true` maintained
- **Negative:** Hovering does NOT count as free (still costs 1 action)
- **Expected:** NOT implemented → error

### REQ 2224 — Grab an Edge (reaction): Reflex save vs DC 15 when falling past handhold
- **Positive:** Falling entity passes a handhold → Reflex DC 15 reaction → success stops fall
- **Negative:** No handhold available → reaction cannot trigger
- **Expected:** NOT in `getLegalIntents()` → error response

### REQ 2225 — Mount: 1 action; target must be willing, adjacent, and ≥1 size larger
- **Positive:** Mount used on willing Large creature → character becomes mounted
- **Negative:** Mounting an unwilling creature → rejected; mounting same-size → rejected
- **Expected:** NOT in `getLegalIntents()` → error response

### REQ 2226 — Point Out: 1 action; undetected creature becomes hidden to allies who hear you
- **Positive:** Point Out on undetected goblin → goblin becomes hidden (not undetected) for allies
- **Negative:** Point Out cannot make a hidden creature observed (only undetected → hidden)
- **Expected:** NOT in `getLegalIntents()` → error response

### REQ 2227 — Raise a Shield: 1 action; gain shield's circumstance AC bonus until next turn start
- **Positive:** Character with shield uses Raise a Shield → AC increases by shield bonus
- **Negative:** Shield not equipped → Raise a Shield has no effect / rejected
- **Expected:** NOT in `getLegalIntents()` → error response

### REQ 2228 — Attack of Opportunity (fighter): reaction, melee Strike on trigger
- **Positive:** Fighter class; enemy uses Stride past fighter → AoO triggers; Strike fires
- **Negative:** Non-fighter class → no AoO available
- **Expected:** NOT implemented as automated reaction trigger

### REQ 2229 — AoO critical hit + manipulate trigger = disrupt the action
- **Positive:** Fighter AoO crits enemy casting spell (manipulate trait) → spell disrupted
- **Negative:** AoO hit (non-crit) on manipulate → action NOT disrupted

### REQ 2230 — AoO does not count for or apply MAP
- **Positive:** Fighter makes AoO as reaction → `attacks_this_turn` NOT incremented; MAP not applied to AoO roll
- **Negative:** Regular Strike increases MAP; AoO does not

### REQ 2231 — Shield Block (reaction): damage reduced by Hardness; remaining splits creature + shield
- **Positive:** 10 damage incoming, shield Hardness 5 → 5 damage to character, 5 damage to shield HP
- **Negative:** Shield at 0 HP (broken) → Shield Block not available

### REQ 2232 — Shield must have been Raised to use Shield Block
- **Positive:** Raise a Shield on turn → can Shield Block on enemy turn
- **Negative:** Shield not raised → Shield Block reaction rejected

---

## Summary

All 14 requirements are expected to FAIL. Document the specific error message for each in QA report.  
File a consolidated bug report in `/sessions/qa-dungeoncrawler/outbox/` after testing.
- Agent: qa-dungeoncrawler
- Status: pending
