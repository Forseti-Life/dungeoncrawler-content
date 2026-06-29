# QA Task: Movement in Encounters (Reqs 2233–2266)

**Type:** qa  
**Section:** Ch9 — Movement in Encounters  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9 — "Movement in Encounters"

---

## Scope

34 requirements (2233–2266). All expected to FAIL except light-level data in map.  
Test each systematically. Key files:
- `EncounterPhaseHandler.php` — `processStride()`
- `CombatEngine.php` — `resolveAttack()`
- `Calculator.php` — `calculateAC()`
- `MapGeneratorService.php` — terrain/lighting data

---

## Test Cases

### Movement Types (REQ 2233)
- **Positive:** Entity has separate speed values for land, burrow, climb, fly, swim in stats
- **Negative:** No single unified "Speed" field used for all movement types

### Climb Speed (REQ 2234)
- **Positive:** Entity with climb_speed auto-succeeds Athletics (Climb); +4 circumstance to roll; not flat-footed while climbing
- **Negative:** Entity WITHOUT climb_speed must roll Athletics normally; is flat-footed while climbing

### Swim Speed (REQ 2235)
- **Positive:** Entity with swim_speed auto-succeeds Athletics (Swim); +4 circumstance; still flat-footed underwater
- **Negative:** Entity WITHOUT swim_speed must roll; IS flat-footed underwater always

### Speed Bonuses/Penalties (REQ 2236)
- **Positive:** Status +10 Speed item + circumstance +5 applied to movement; minimum 5 ft enforced
- **Negative:** Stacking multiple status Speed bonuses → only highest applies (no stacking)

### Diagonal Movement (REQ 2237)
- **Positive:** 1st diagonal = 5 ft, 2nd diagonal = 10 ft, alternating; tracked across full turn
- **Negative:** Diagonals do NOT each cost flat 5 ft (must alternate 5/10/5/10...)

### Size Categories (REQ 2238)
- **Positive:** Entities have `size` field with one of: Tiny, Small, Medium, Large, Huge, Gargantuan
- **Negative:** Entity without size field → defaults to Medium (not missing/error)

### Size Space and Reach (REQ 2239)
- **Positive:** Large (tall) creature occupies 10×10 ft space with 10 ft reach
- **Negative:** Large (long) creature has different reach value than tall variant

### Moving Through Creature Space (REQ 2240)
- **Positive:** Moving through willing ally's space → allowed; Tumble Through allows vs hostile
- **Negative:** Cannot END movement in another creature's space

### ≥3 Size Difference Movement (REQ 2241)
- **Positive:** Tiny creature moves through Huge creature's space freely
- **Negative:** Medium creature CANNOT move through Large creature's space freely (not ≥3 difference)

### Tiny Creature Space (REQ 2242)
- **Positive:** Tiny creature can occupy same hex as Large creature; can end movement there
- **Negative:** Medium creature cannot end movement in another Medium creature's space

### Fall Damage (REQ 2243)
- **Positive:** 40 ft fall → 20 bludgeoning damage (½ distance)
- **Negative:** Max 1,500 ft fall → 750 damage (not more)

### Soft Surface Fall Reduction (REQ 2244)
- **Positive:** 50 ft fall into 10 ft deep water → treat fall as 50−30 = 20 ft (10 damage), capped by depth
- **Negative:** Soft surface reduction cannot reduce below 0 ft

### Land on Creature (REQ 2245)
- **Positive:** Falling onto creature → that creature rolls Reflex DC 15 or takes damage
- **Negative:** Landing on empty space → no Reflex check triggered

### Prone on Fall Damage (REQ 2246)
- **Positive:** Character takes any fall damage → prone condition applied
- **Negative:** Arrested fall (Arrest a Fall crit success) → no fall damage = no prone

### Forced Movement + Reactions (REQ 2247)
- **Positive:** Push/pull effect moves entity → does NOT trigger AoO or move-triggered reactions
- **Negative:** Voluntary Stride past enemy → CAN trigger AoO

### Forced Movement Terrain (REQ 2248)
- **Positive:** Push stops at wall/impassable square (not pushed through)
- **Negative:** Creature cannot be forced into impassable terrain

### Difficult Terrain (REQ 2249)
- **Positive:** Moving into difficult terrain hex costs +5 ft (1 square = 10 ft cost)
- **Negative:** Stride with Speed 30 through 3 difficult terrain hexes → uses all 30 ft (3×10 ft)

### Greater Difficult Terrain (REQ 2250)
- **Positive:** Moving into greater difficult terrain hex costs +10 ft (1 square = 15 ft cost)
- **Negative:** Greater difficult terrain does NOT double diagonal costs separately

### Cannot Step into Difficult Terrain (REQ 2251)
- **Positive:** Step into normal adjacent square → allowed
- **Negative:** Step into difficult terrain square → rejected

### AoE Ignores Difficult Terrain (REQ 2252)
- **Positive:** Fireball burst covers difficult terrain squares fully
- **Negative:** Difficult terrain does NOT reduce AoE damage/range

### Flanking Conditions (REQ 2253)
- **Positive:** Two allies on opposite sides of enemy, both with melee reach → flanking applies
- **Negative:** Only one ally adjacent → no flanking; ally cannot act → no flanking

### Flanked = Flat-Footed to Flankers (REQ 2254)
- **Positive:** Flanked target → −2 AC (flat-footed) vs flanking creatures' melee attacks only
- **Negative:** Flanked target → non-melee attacks do NOT get the −2 bonus

### Cover Tiers (REQ 2255)
- **Positive:** Standard cover → +2 AC, +2 Reflex, +2 Stealth (can hide)
- **Negative:** Lesser cover → +1 AC only; cannot hide; greater cover → +4 all

### Cover Determination (REQ 2256)
- **Positive:** Line from attacker to target passes through terrain → standard cover for target
- **Negative:** Line passes through a creature → only lesser cover (not standard)

### Cover Per-Pair (REQ 2257)
- **Positive:** Target behind pillar vs attacker A (cover) but not vs attacker B (clear LoS)
- **Negative:** Cover for one attacker does NOT apply to another attacker with clear LoS

### Mounted Rider MAP (REQ 2258)
- **Positive:** Mounted rider's attack MAP shared with mount's attacks (combined attacks_this_turn)
- **Negative:** Mount and rider do NOT track MAP independently

### Mount Initiative (REQ 2259)
- **Positive:** Mount acts on rider's initiative; Command an Animal needed for mount to take actions
- **Negative:** Mount does NOT act independently unless riderless

### Ride Feat (REQ 2260)
- **Positive:** Character with Ride feat auto-succeeds Command an Animal for own mount
- **Negative:** Without Ride feat, must roll Animal Handling vs DC 15 + mount level

### Rider Penalties (REQ 2261)
- **Positive:** Mounted rider → −2 Reflex saves; only move action available is dismount
- **Negative:** Dismounted → normal Reflex; can use all actions freely

### Aquatic Combat (REQ 2262)
- **Positive:** Entity without swim_speed underwater → flat-footed; resistance 5 acid/fire; −2 slashing/bludgeoning melee
- **Negative:** Entity WITH swim_speed → not flat-footed underwater

### Ranged Underwater (REQ 2263)
- **Positive:** Ranged bludgeoning/slashing attack underwater → auto-miss
- **Negative:** Ranged piercing underwater → only half range increments (not auto-miss)

### Fire Underwater (REQ 2264)
- **Positive:** Fire spell cast underwater → automatically fails
- **Negative:** Non-fire spell cast underwater → can succeed

### Held Breath Tracking (REQ 2265)
- **Positive:** Character holds breath for 5 + Con mod rounds; attacks/spells reduce by 2; speech uses all
- **Negative:** Character not holding breath → drowning track starts immediately when submerged

### Suffocation (REQ 2266)
- **Positive:** 0 air rounds → unconscious; Fort DC 20 at turn end; fail = 1d10; crit fail = death
- **Negative:** Subsequent checks: DC and damage increase cumulatively (e.g., DC 25, 2d10 on round 2)

---

## Expected Failures

All 34 requirements — no movement rules validation in `processStride()`.  
`processStride()` only updates hex position; no distance check, terrain check, or rule enforcement.  
Document each failure with specific API call and error/incorrect response.
- Agent: qa-dungeoncrawler
- Status: pending
