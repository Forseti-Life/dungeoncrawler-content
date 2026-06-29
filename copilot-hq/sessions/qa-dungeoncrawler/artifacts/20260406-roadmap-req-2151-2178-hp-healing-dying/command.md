# QA Task: Hit Points, Healing, and Dying (Reqs 2151–2178)

**Type:** qa  
**Section:** Ch9 — Hit Points, Healing, and Dying  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9 — "Hit Points, Healing, and Dying"

---

## Scope

Write positive and negative test cases for every requirement in this section (2151–2178).
Mark each with ✅ PASS or ❌ FAIL based on manual code trace or API call.

Key files to inspect:
- `HPManager.php` — applyDamage, applyDyingCondition, stabilizeCharacter, evaluateDeath, applyTempHp, applyHealing
- `ConditionManager.php` — processDying, condition catalog (dying, wounded, doomed, unconscious)
- `DowntimePhaseHandler.php` — processLongRest
- `CharacterCalculator.php` — calculateMaxHp

---

## Test Cases

### REQ 2151 — HP cannot go below 0
- **Positive:** Character with 5 HP receives 10 damage → HP = 0 (not −5)
- **Negative:** Character at 0 HP receives more damage → HP remains 0, not negative

### REQ 2152 — Max HP = (class_hp + con_mod) × level + ancestry_hp
- **Positive:** Fighter (class_hp=10), Con+2, level 3, ancestry_hp=8 → max_hp = (10+2)×3 + 8 = 44
- **Negative:** Missing ancestry_hp field → formula falls back gracefully (no crash)

### REQ 2153 — At 0 HP, initiative shifts to just before the attacker
- **Positive:** Character drops to 0 during round → their initiative updates in combat tracker
- **Negative:** Character at 0 HP in round 1 → initiative is NOT unchanged from start-of-round value

### REQ 2154 — Dying 1 at 0 HP; Dying 2 on crit success attack / crit fail save
- **Positive:** Normal hit dropping to 0 → dying = 1
- **Negative:** Critical hit dropping to 0 → dying = 2 (not 1)

### REQ 2155 — Wounded adds to dying when dying is gained
- **Positive:** Wounded 2 character drops to 0 → gains dying 1+2 = dying 3
- **Negative:** Character without wounded gains dying 1 → wounded NOT added

### REQ 2156 — Nonlethal attack → unconscious, not dying
- **Positive:** Nonlethal damage drops HP to 0 → character gains unconscious, dying NOT applied
- **Negative:** Lethal damage drops HP to 0 → dying is applied, not unconscious only

### REQ 2157 — Dying is valued 1–3; death at dying 4
- **Positive:** Character reaches dying 3 → alive, recovery check each turn
- **Negative:** Character reaches dying 4 → character is dead

### REQ 2158 — Recovery check DC = 10 + current dying value
- **Positive:** Dying 2 → recovery check DC = 12 (roll needed: 12+ for success)
- **Negative:** Recovery check does NOT use flat DC 10 regardless of dying value

### REQ 2159 — Recovery degree outcomes: crit success −2, success −1, fail +1, crit fail +2
- **Positive:** Dying 2, roll nat 20 → crit success → dying drops to 0 (death avoided)
- **Negative:** Dying 2, roll 1 → crit fail → dying becomes 4 (death)

### REQ 2160 — After losing dying (stabilized): remains unconscious at 0 HP
- **Positive:** Character stabilizes (dying → 0) → HP stays at 0, unconscious persists
- **Negative:** Stabilize does NOT set HP to 1 automatically

### REQ 2161 — Losing dying (stabilized) → gain wounded +1
- **Positive:** Wounded 1, stabilizes → wounded becomes 2
- **Negative:** Stabilizing with no wounded condition → wounded 1 is gained (not 0)

### REQ 2162 — Wounded increases by 1 each time dying is lost
- **Positive:** First stabilize from dying → wounded 1; second time → wounded 2
- **Negative:** Two distinct dying→stabilize events → wounded accumulates (+1 each time), not reset

### REQ 2163 — Gaining dying while wounded: add wounded value to dying gained
- **Positive:** Wounded 1, takes lethal damage to 0 → dying = 1 + 1 = dying 2
- **Negative:** Wounded 3, takes lethal damage to 0 → dying = 1+3 = dying 4 (immediate death)

### REQ 2164 — Wounded ends: Treat Wounds or reach full HP with 10 min rest
- **Positive:** Character reaches max HP during a rest → wounded condition removed
- **Negative:** Character healed to full HP mid-combat → wounded NOT removed (no rest)

### REQ 2165 — Doomed reduces dying death threshold by its value
- **Positive:** Doomed 1 → dying 3 = death (threshold 4−1=3)
- **Negative:** Doomed 2 + dying 2 = death (threshold 4−2=2)

### REQ 2166 — Doomed value ≥ reduced threshold → instant death
- **Positive:** Doomed 4 → character dies immediately upon gaining dying 1
- **Negative:** Doomed 0 → normal dying track (death at 4)

### REQ 2167 — Doomed decreases by 1 per full rest; cleared on resurrection
- **Positive:** Doomed 2, long rest → doomed becomes 1
- **Negative:** Short rest (not full night) → doomed unchanged

### REQ 2168 — Unconscious: −4 status penalty to AC, Perception, Reflex; blinded, flat-footed
- **Positive:** Unconscious character attacked → attacker gets flat check (blinded) AND defender AC is −4
- **Negative:** Conscious character → no −4 AC penalty from unconscious condition

### REQ 2169 — Unconscious at 0 HP: can naturally recover after 10 min rest
- **Positive:** After 10 in-game minutes at 0 HP → character wakes with 1 HP (or HP gain from Con)
- **Negative:** Character at 0 HP in active combat → does NOT auto-recover within a round

### REQ 2170 — Unconscious with HP > 0: wakes on damage, healing, shaken/slapped, loud noise
- **Positive:** Unconscious character with 10 HP receives healing → becomes conscious
- **Negative:** Unconscious character at 0 HP is healed above 0 → should also wake

### REQ 2171 — Hero Point: heroic recovery — lose dying condition, no wounded gained
- **Positive:** Dying 2, spend Hero Point → dying 0, wounded NOT incremented
- **Negative:** Normal stabilize (no Hero Point) → wounded IS incremented

### REQ 2172 — Death effects: bypass dying track, kill immediately
- **Positive:** Action with `death_effect: true` reducing HP to 0 → character dead (not dying 1)
- **Negative:** Normal attack reducing HP to 0 → dying track is followed (not instant death)

### REQ 2173 — Massive damage: damage ≥ double max HP in one hit → instant death
- **Positive:** Max HP 50, single blow = 100 damage → instant death (no dying track)
- **Negative:** Max HP 50, single blow = 99 damage → normal dying track (not instant death)

### REQ 2174 — Temp HP tracked separately; damage reduces temp HP first
- **Positive:** 10 temp HP, 15 damage → temp HP = 0, HP −5
- **Negative:** 10 temp HP, 5 damage → temp HP = 5, HP unchanged

### REQ 2175 — Only one temp HP pool; keep higher value
- **Positive:** 10 temp HP gained, then 8 more → still 10 (higher kept)
- **Negative:** 10 temp HP, gain 15 → becomes 15 (higher wins, stacking not allowed)

### REQ 2176 — Temp HP not restored by healing
- **Positive:** Temp HP at 0, receive healing → temp HP stays 0
- **Negative:** Receiving healing spell does NOT fill temp HP pool

### REQ 2177 — Fast healing: restore X HP at start of each turn
- **Positive:** Entity with fast_healing 5 starts turn at 45/50 HP → ends turn at 50
- **Negative:** Fast healing does NOT exceed max HP

### REQ 2178 — Regeneration: fast healing that blocks death; fire/acid bypasses
- **Positive:** Entity with regeneration, at 0 HP, start of turn → restored to positive HP (doesn't die)
- **Negative:** Fire/acid damage on regenerating entity → regeneration does NOT prevent death from fire/acid

---

## Expected Failures (Known Bugs — Report as FAIL)

- **REQ 2158:** `processDying` uses DC=10 hardcoded; should be DC = 10 + dying_value
- **REQ 2160:** `stabilizeCharacter` sets HP to 1 (line ~210 HPManager); should remain 0
- **REQ 2161/2162:** `stabilizeCharacter` sets `wounded = dying_value - 1` (wrong); should be `wounded + 1`
- **REQ 2165/2166:** `processDying` hardcodes death at `dying >= 4`; doomed not subtracted
- **REQ 2167:** `processLongRest` only has comment for doomed reduction; verify actual code path
- **REQ 2168:** `ConditionManager` unconscious only has `cannot_act + flat_footed`; missing −4 AC/Perception/Reflex
- **REQ 2173:** `evaluateDeath` checks `hp <= -max_hp` (wrong); should check `damage >= 2 * max_hp`
- **REQ 2153, 2154, 2156, 2164, 2169, 2170, 2171, 2172, 2177, 2178:** No implementation found
- Agent: qa-dungeoncrawler
- Status: pending
