# Issue #9: Ability Score System

**Status**: Open  
**Type**: Feature Request  
**Priority**: Critical  
**Source**: PF2E Core Rulebook Chapter 1 — The Six Ability Scores, Ability Boosts, Ability Flaws, Ability Modifiers  
**Created**: 2026-02-27

## Overview

Ability scores are the foundational data layer of every character. This issue defines the six scores, their modifiers, the boost and flaw mechanics, the validation rules, the batch uniqueness constraint, and the optional dice-roll creation mode.

## Requirements

### REQ-9.1 — Six Ability Scores as First-Class Fields
All six ability scores must be first-class fields on every character record:
- `str` (Strength) — physical power
- `dex` (Dexterity) — agility, balance, reflexes
- `con` (Constitution) — health, stamina
- `int` (Intelligence) — learning, reasoning
- `wis` (Wisdom) — awareness, intuition
- `cha` (Charisma) — personal magnetism

Scores are grouped: Physical (STR/DEX/CON) and Mental (INT/WIS/CHA). This grouping should be representable in the data model (e.g., a `group` metadata field).

### REQ-9.2 — Ability Score Initialization
All six ability scores initialize to **10** at the start of character creation before any boosts or flaws are applied.

### REQ-9.3 — Ability Score Modifier Formula
Ability modifier = `floor((score − 10) / 2)`.

This must be computed as a derived value; it is never stored independently — always re-derived from the score. Full lookup table:

| Score | Modifier | Score | Modifier |
|---|---|---|---|
| 1 | −5 | 14–15 | +2 |
| 2–3 | −5 | 16–17 | +3 |
| 4–5 | −4 | 18–19 | +4 |
| 6–7 | −3 | 20–21 | +5 |
| 8–9 | −2 | 22–23 | +6 |
| 10–11 | +0 | 24–25 | +7 |
| 12–13 | +1 | (continues +1 per 2) | … |

### REQ-9.4 — Ability Boost Mechanics
- An ability boost adds **+2** to a score.
- Exception: if the target score is already **≥ 18**, the boost adds only **+1** instead.
- At 1st level, no ability score may exceed **18** after all boosts are applied (hard cap enforced at character creation).

### REQ-9.5 — Ability Flaw Mechanics
- An ability flaw subtracts **−2** from the target score.
- Maximum of **one flaw per ability score** (from any source combined: ancestry + voluntary).

### REQ-9.6 — Boost Types
Each boost is typed at the source:
- **Fixed**: must be applied to one specific named score.
- **Choice-of-two**: player chooses one of two named scores.
- **Free**: player chooses any score.

### REQ-9.7 — Batch Uniqueness Constraint
When multiple boosts are granted simultaneously (in the same "batch"), each boost in the batch must target a **different** score. No two boosts from the same batch may go to the same score.

Additionally, when a free boost is granted alongside fixed boosts in the same batch, the free boost cannot be applied to a score already boosted within that same batch.

### REQ-9.8 — Score Range Validation at 1st Level
After applying all boosts and flaws at 1st level (Step 6 of character creation), every score must be in the range **[8, 18]**. Any score outside this range is a validation error.

### REQ-9.9 — Stat Dependencies by Score
The system must apply the following modifier dependencies:
- **STR modifier** → added to melee damage rolls; determines Bulk carry thresholds (Encumbered = STR+5; Max = STR+10).
- **DEX modifier** → added to AC (subject to armor's Dex cap); added to Reflex saving throws; used for ranged attack rolls and Stealth.
- **CON modifier** → added to max HP (once per level, multiplied by level); added to Fortitude saving throws.
- **INT modifier** → number of additional trained skills above class base = INT modifier (minimum 0); number of additional known languages = INT modifier (if ≥ +1).
- **WIS modifier** → added to Perception; added to Will saving throws.
- **CHA modifier** → used as the governing modifier for social skills: Diplomacy, Deception, Intimidation, Performance.

### REQ-9.10 — Boost Pipeline Across Creation Steps
The boost pipeline applies in this order (all cumulative):
1. Ancestry boosts + flaws (Step 3)
2. Background boosts (Step 4)
3. Class key ability score boost (Step 5)
4. Four additional free boosts (Step 6)

Each step's boosts are a separate batch and must individually satisfy the batch uniqueness constraint (REQ-9.7).

### REQ-9.11 — Alternate Ancestry Boost Option
A player may replace an ancestry's listed fixed boosts and flaw entirely with **2 free ability boosts** and no flaw. This is a boolean toggle at character creation; when active, the ancestry's default boost/flaw array is ignored.

### REQ-9.12 — Voluntary Flaws (Optional)
A player may elect to take additional ability flaws beyond the ancestry's default during Step 3. Constraints:
- Purely optional (player-elected, no mechanical benefit).
- No more than one flaw total (ancestry + voluntary combined) per individual score.
- System should prompt for GM/group consent (informational warning, not a block).

### REQ-9.13 — Alternative: Dice-Roll Mode (GM-Enabled Option)
A GM-enabled toggle activates dice-roll ability score generation. When active:
- Roll 4d6, drop the lowest die, sum the remaining three. Repeat ×6 to produce 6 values.
- Player assigns the 6 values to the 6 scores freely.
- Apply ancestry fixed boosts and flaws normally, but with **one fewer free boost** than normal.
- Apply only one background ability boost (the typed choice; not the free one).
- Boosts cannot raise any score above 18; if they would, redirect to another score or cap at 18 (losing the excess).
- After rolling-mode generation, modifiers are derived identically from Table 1–1.
- Level-up ability boosts work identically to standard mode.

## Acceptance Criteria
- [ ] All six scores initialized to 10 at start of creation; verified by test.
- [ ] Boost of +2 applied correctly; score ≥18 results in +1 boost instead; verified at score 17, 18, 19.
- [ ] No score may exceed 18 at 1st level; validator rejects score of 19 at finalization.
- [ ] Two boosts in same batch cannot target the same score; system prevents/rejects duplicate.
- [ ] Free boost in batch with fixed boosts cannot duplicate a fixed-boost target.
- [ ] Modifier formula `floor((score-10)/2)` verified for scores 8, 10, 12, 14, 16, 18.
- [ ] Stat dependencies tested: STR→melee damage, DEX→AC, CON→HP, INT→skill count, WIS→Perception, CHA→Diplomacy.
- [ ] Alternate ancestry option produces 2 free boosts and 0 flaws (no fixed values).
- [ ] Rolling mode produces 6 values via 4d6-drop-lowest; assigns correctly; applies reduced boost pipeline.

## Source Paragraphs
- "Strength, Dexterity, and Constitution are physical ability scores... Intelligence, Wisdom, and Charisma are mental ability scores." (Ch1, The Six Ability Scores, §2)
- "An ability boost normally increases an ability score's value by 2. However, if the ability score... is already 18 or higher, its value increases by only 1." (Ch1, Ability Boosts, §1)
- "When you gain multiple ability boosts at the same time, you must apply each one to a different score." (Ch1, Ability Boosts, §2)
- "You should have no ability score lower than 8 or higher than 18." (Ch1, Step 6, §2)
