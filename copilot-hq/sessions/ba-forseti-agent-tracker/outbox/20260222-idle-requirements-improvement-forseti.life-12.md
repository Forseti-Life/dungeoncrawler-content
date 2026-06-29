# Outbox update: 20260222-idle-requirements-improvement-forseti.life-12

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-requirements-improvement-forseti.life-12
- Generated: 2026-02-22T14:01:04-05:00

---

I have enough context. PR-05 exists as target-state design (verified "partial implementation" note) with Focus spells, Innate spells, and Signature spells explicitly deferred to "Future Enhancements." No acceptance criteria exist for the core cast-a-spell-in-combat path. This is the highest-ROI remaining dungeoncrawler spec gap.

---

- Status: done
- Summary: Completed BA idle cycle 12 for forseti.life. Selected Dungeoncrawler Spellcasting Core Path Acceptance Criteria as the top-ROI item — PR-05 exists as a comprehensive target-state design document but its 2026-02-18 verification notes explicitly state that "the full standalone route surface described below may be partial or planned," and Focus spells, Innate spells, and Signature spells are listed as deferred "Future Enhancements" with no tracking items. No acceptance criteria exist for the core paths (cast a spell in combat, prepared caster daily preparation, spontaneous caster slot usage, spell disruption). Without AC, Dev has no definition of done and no clear boundary between MVP and deferred scope. This spec closes that gap for the core combat spellcasting path.

## Next actions
- Executor: write this artifact to outbox
- Route to PM (dungeoncrawler) for AC sign-off; Q1 (focus spell MVP scope) and Q3 (spell disruption on concentration damage) are the critical decisions before Dev begins
- Route dev task to Dev (dungeoncrawler) after PM sign-off; note that `SpellcastingController` route surface is confirmed partial — Dev must audit which routes are live before scoping implementation work
- Note: Level-Up Wizard spec (Cycle 9) depends on spell slot progression — this spec's AC for slot assignment must be confirmed consistent with the level-up wizard's spell step

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 22
- Rationale: Spellcasting is a core combat mechanic for roughly half of PF2e character classes (Wizard, Sorcerer, Cleric, Druid, Bard, etc.); without acceptance criteria for the core cast path, Dev cannot determine what is shippable and the entire spellcasting feature set remains in an ambiguous partial state. The design doc is thorough — the AC gap is the only thing preventing Dev from starting implementation against a verifiable target.

---

## ROI-Ranked Requirements Improvements (3 candidates)

| Rank | Item | Module | ROI | Rationale |
|------|------|--------|-----|-----------|
| 1 ✅ | Spellcasting Core Path Acceptance Criteria | dungeoncrawler | 22 | PR-05 design is complete but partial implementation confirmed; no AC exists; Focus/Innate/Signature deferred with no tracking |
| 2 | XP Award System Spec | dungeoncrawler | 15 | XP threshold triggers level-up but no spec exists for award mechanism (how much per encounter, GM manual vs. automatic, milestone option) |
| 3 | Character Creation UX Flow Spec | dungeoncrawler | 12 | `SchemaLoader::getClassData()` is a confirmed TODO; character creation UX spec is thin on step-by-step AC; needed to verify correct class data loading at character creation |

---

## Requirements Artifact: dungeoncrawler — Spellcasting Core Path Acceptance Criteria

### Problem Statement

PR-05 (`PR-05-spellcasting-implementation.md`) provides a comprehensive target-state design for a full PF2e spellcasting system, but its 2026-02-18 verification notes confirm: "Current spellcasting-related runtime behavior is primarily represented through character state APIs and service logic, not the full standalone route surface described below." The document explicitly defers Focus spells, Innate spells, and Signature spells to a "Future Enhancements" section with no tracking items or target dates.

No acceptance criteria exist for any spellcasting path. This spec defines the **core MVP path** only: a spellcasting character can cast a spell in combat, the appropriate slot is expended, and the outcome (attack roll or saving throw) is resolved and applied. Daily preparation for prepared casters and spontaneous casting from repertoire are both in scope. Focus spells, Innate spells, Rituals, Metamagic, and Spell Scrolls are explicitly deferred.

**Current behavior:** Character state APIs may carry spell slot data. `SpellcastingController` route surface is "partial or planned." A player controlling a Wizard or Sorcerer has no confirmed working path to cast a spell in combat.  
**Expected behavior:** In an active combat encounter, a spellcasting character can open their spell list, select a spell, choose a target (or area), expend the appropriate slot, and see the spell's effect resolved (damage applied, condition added, or miss reported). Prepared casters complete daily preparation before the first encounter of the day. Cantrips can be cast unlimited times with no slot cost.

### Scope

**In scope (MVP core path):**
- Character spell data loading: `GET /spellcasting/character/{character_id}` returns available spell slots, remaining slots, and known/prepared spells
- Daily preparation for prepared casters (Wizard, Cleric, Druid): `POST /spellcasting/prepare` — assign spells to slots; valid only outside combat; resets at daily preparations
- Spontaneous casting slot availability (Sorcerer, Bard): slot count loaded from `character_spell_slots`; any known spell usable in any same-level or higher slot
- Cast a spell in combat: `POST /spellcasting/cast` — validate components available (verbal: must not be silenced; somatic: must have at least one free hand; material: pouch or focus present); validate slot available; validate range to target; expend slot; resolve outcome
- Spell attack roll path: roll d20 + spell attack bonus vs. target AC; apply hit/critical hit/miss; apply spell damage/effect
- Saving throw path: target rolls vs. spell DC; apply effect at appropriate degree of success (critical success / success / failure / critical failure)
- Cantrip casting: no slot expended; automatically heightened to half caster level (rounded up); unlimited per day
- Spell slot display: combat UI shows current slot counts (e.g., "1st: ●●○") updated in real time
- Heightening: casting a lower-level spell in a higher-level slot uses the higher slot and applies the spell's heightened effect text
- Spell disruption: if a caster takes damage on a concentrate-trait action during spell casting, spell fails, slot is expended, no effect is produced
- Active spell tracking: sustained spells listed in combat UI with "Sustain" button; `POST /spellcasting/sustain` extends duration by 1 round; spell ends if not sustained

**Non-goals (explicitly deferred):**
- Focus spell system (class-specific focus pools, focus point recovery) — Phase 2
- Innate spells (ancestry/heritage at-will and limited-use spells) — Phase 2
- Signature spells (Sorcerer/Bard specific known-spell amplification) — Phase 2
- Ritual casting — Phase 2
- Spell scrolls and wands — Phase 2
- Metamagic feat integration — Phase 2
- Counterspell reaction — Phase 2
- `identifySpell` (observer recognizing a spell being cast) — Phase 2
- Spell component pouch purchasing / tracking (assume pouch is always available for MVP) — Phase 2

### Definitions

| Term | Definition |
|------|------------|
| Prepared caster | A character class (Wizard, Cleric, Druid) that assigns specific spells to slots during daily preparations; can only cast those assigned spells |
| Spontaneous caster | A character class (Sorcerer, Bard) that has a repertoire of known spells and can cast any known spell using any appropriate slot |
| Spell DC | 10 + proficiency bonus + key ability modifier; the difficulty class targets must beat to resist a spell |
| Spell attack bonus | Proficiency bonus + key ability modifier; added to d20 roll vs. target AC for spell attack spells |
| Cantrip | A 0-level spell; never expends a slot; cast unlimited times; always heightened to half caster level (rounded up) |
| Heightening | Casting a lower-level spell using a higher-level slot; slot cost is the higher slot level; spell gains heightened effects per its description |
| Concentration / Concentrate trait | Actions marked Concentrate can be disrupted when the caster takes damage during the action |
| Slot expended | `character_spell_slots.used_slots` incremented by 1 for the relevant spell level; slot is unavailable until daily preparations reset it |
| Daily preparations | The once-per-day event (typically after 8 hours rest) where: (a) prepared casters assign spells to slots, (b) all slot `used_slots` counts reset to 0 |

### Key User Flows

**Flow A: Wizard casts Magic Missile in combat**
1. Player opens spell panel during combat turn
2. Slot display shows "1st: ●○○" (1 remaining)
3. Player selects Magic Missile (1st-level, 2 actions)
4. Spell panel shows target selector — player selects one enemy
5. Player confirms cast
6. System validates: somatic component (free hand: yes), verbal component (not silenced: yes), slot available: yes, range to target: yes (within 120 ft)
7. `character_spell_slots.used_slots` incremented; slot display updates to "1st: ○○○"
8. No attack roll needed (Magic Missile auto-hits); damage `1d4+1` rolled and applied to target HP
9. Combat log: "Seelah casts Magic Missile on Goblin for 4 force damage."

**Flow B: Sorcerer casts Fireball as a spontaneous caster**
1. Player selects Fireball (3rd-level, 2 actions, basic Reflex save)
2. Slot display shows available 3rd-level slots
3. Player selects "Cast at 3rd level" (normal) or "Cast at 4th level" (heightened for extra damage)
4. Player selects burst center point
5. System identifies all targets in 20-foot burst
6. For each target: target rolls Reflex save vs. spell DC; outcome applied per degree of success
7. Slot expended; combat log shows each target's result

**Flow C: Prepared Wizard — daily preparations**
1. Before first encounter, player accesses "Prepare Spells" at character sheet
2. UI shows slot grid: 3 × 1st, 2 × 2nd, 1 × 3rd (example for 3rd-level Wizard)
3. Player drags spells from spellbook to slots
4. Attempting to assign the same spell to a slot it's already occupying shows warning: "Slot already prepared with this spell"
5. Player clicks "Finalize Preparations" — all `used_slots` reset to 0; `prepared_spells` table updated
6. During the day, as spells are cast, `used_slots` increments; slots cannot be re-prepared until next daily preparations

**Flow D: Spell disruption (concentrate damage)**
1. Enemy attacks wizard who is mid-cast (2-action spell, spending actions)
2. Wizard takes damage while casting (on a concentrate-trait action)
3. System flags concentration disruption
4. Spell fails: slot is expended, no effect occurs, actions are lost
5. Combat log: "Ezren's Fireball was disrupted! Slot expended, no effect."

### Acceptance Criteria (Draft — PM to finalize)

**Happy path:**
- AC1: `GET /spellcasting/character/{character_id}` returns current slot counts, used slot counts per level, and the character's spell list (prepared spells for prepared casters; known spells for spontaneous casters); cantrips appear in a separate list with no slot count.
- AC2: `POST /spellcasting/cast` with a valid prepared/known spell, available slot, in-range target, and valid components returns a resolution result and decrements `character_spell_slots.used_slots` for the appropriate level by 1.
- AC3: After casting a spell using a 3rd-level slot for a 1st-level spell (heightening), `used_slots` decrements for the 3rd-level slot (not the 1st-level slot), and the spell effect uses the heightened values per the spell's description.
- AC4: Casting a cantrip does not decrement any slot count; the cantrip can be cast again on the next turn with no restriction.
- AC5: For a spell attack spell (e.g., Ray of Frost), the system rolls `d20 + spell_attack_bonus` vs. target AC; on a hit, damage is rolled and applied to target HP; on a miss, no damage is applied; on a critical hit (≥ AC + 10), double damage is applied.
- AC6: For a saving throw spell (e.g., Fireball), the target rolls the appropriate save vs. spell DC; critical success = no damage; success = half damage; failure = full damage; critical failure = double damage — each outcome correctly applied per the spell's description.
- AC7: A prepared caster completing `POST /spellcasting/prepare` has all `used_slots` reset to 0 and `character_prepared_spells` rows updated to reflect the new preparation; the route is blocked during active combat with error: "Cannot prepare spells during active combat."
- AC8: Attempting to cast a spell when `used_slots >= total_slots` for the required level returns an error: "No {N}th-level spell slots remaining."
- AC9: Cantrips are automatically heightened to half the caster's current level (rounded up); a 5th-level Wizard casting Electric Arc uses the 3rd-level heightened stats (half of 5, rounded up = 3).
- AC10: The combat UI spell panel displays current slot counts updated in real time (e.g., "2nd: ●●○" → "2nd: ●○○" after casting a 2nd-level spell); no page reload required.

**Failure modes:**
- AC11: Attempting to cast a spell that requires a verbal component while the caster has the "silenced" condition returns an error: "Cannot cast spells with verbal components while silenced."
- AC12: Attempting to cast a spell that requires somatic/material components while the caster has both hands occupied (no free hand) returns an error: "No free hand available for somatic/material components." (Exception: Bard with instrument can bypass this per component substitution rules.)
- AC13: Spell disruption (caster takes damage on a Concentrate-trait action): the spell fails, the slot is expended, no effect is produced, and the combat log records: "{Caster} was disrupted while casting {Spell}. Spell slot expended."
- AC14: A prepared caster attempting to cast a spell not in their current prepared slots (even if it is in their spellbook) receives: "You have not prepared {Spell} today." The spell does not fire and no slot is expended.
- AC15: Attempting to cast a spell targeting an enemy outside the spell's range returns: "{Spell} requires a target within {N} feet. {Enemy} is {M} feet away."

**Verification method:**
- PHPUnit: `SpellcastingControllerTest` — test AC2 slot decrement (wizard casts magic missile, verify `used_slots` goes from 0 to 1); test AC8 out-of-slots error; test AC7 prepare-during-combat block.
- PHPUnit: `CastSpellResolutionTest` — test spell attack path (mock d20 roll hit and miss); test basic save path (mock save results for all 4 degrees of success); test heightened slot use (AC3).
- PHPUnit: `CantripTest` — assert no slot decrement; assert level 5 character cantrip heightens to level 3.
- Manual (staging): Load a Wizard character; perform daily preparations; cast 3 × 1st-level spells; attempt 4th cast; verify AC8 error.
- Manual (staging): Cast a save-based spell against multiple enemies; verify each target's save result is logged and damage applied correctly.

### Known Implementation Gap: `SpellcastingController` Route Surface

PR-05 verification notes (2026-02-18) confirm the route surface is "partial or planned." Before Dev begins implementation, the following must be audited:
- Which routes in `copilot_agent_tracker.routing.yml` (or dungeoncrawler routing) are live and return valid responses?
- Is `character_spell_slots` table currently created and populated for spellcasting characters?
- Does `character_prepared_spells` table exist?

Dev task: run `drush route:list | grep spellcast` on staging to enumerate live spellcasting routes; report back to PM before implementation scoping begins.

### Assumptions

1. `character_spell_slots` table exists (designed in PR-05 schema) with columns: `character_id`, `spell_level`, `total_slots`, `used_slots`; if not created, table creation is prerequisite Dev task 0.
2. `game_spells` table exists with at minimum: `id`, `name`, `level`, `traditions[]`, `cast_actions`, `components[]`, `range_feet`, `area`, `save_type`, `damage_formula`, `heightened_effects JSON`.
3. The action economy (2-action cost for most spells) is already implemented in the action system (PR-03); `POST /spellcasting/cast` hooks into the existing action consumption logic.
4. Component availability check (verbal: silenced condition, somatic: free hand) reuses existing condition system (`combat_conditions` table) and inventory system.
5. For MVP, assume spell component pouch is always available (component pouch tracking deferred — see non-goals); only verbal and somatic components are enforced at MVP.

### 3–5 Clarifying Questions for Stakeholders

1. **Focus spell MVP scope:** Focus spells are listed as a "Future Enhancement" in PR-05, but several core PF2e classes (Champion, Monk, Cleric with certain domains) rely on focus spells as their primary combat magic. Should focus spells remain deferred, or does the MVP need at least the basic focus spell + 1 focus point per 10-minute refocus mechanic? Recommendation: include minimal focus spell support (1 focus point, single use, refocus action) in MVP if any launch class has a focus spell as their primary magic feature; otherwise defer.

2. **Daily preparations reset trigger:** What event triggers daily preparations? Options: (a) player clicks "Rest" on character sheet (manual, always available), (b) automatically after 8 hours of in-game time passes (requires time tracking), (c) admin-triggered per session. Recommendation: option (a) for MVP — manual "Rest" button is simplest and avoids requiring a game clock system.

3. **Spell disruption implementation:** When a caster takes damage during a concentrate-trait action (mid-cast), should disruption be automatic (slot expended, spell fails with no roll) or require a flat check (DC = 15 + damage taken, as in some PF2e interpretations)? PF2e RAW (rules as written) is automatic failure on hit + damage. Recommendation: automatic disruption for MVP (simpler, correct RAW); flat check variant is a configurable option for Phase 2.

4. **Spell component pouch assumption:** AC11 and AC12 enforce verbal and somatic components but the spec defers material component pouch tracking. Should the system display a warning ("Materials: Using component pouch") without blocking, or should the cast be allowed silently? Recommendation: allow silently with no display for MVP; add pouch tracking in Phase 2 when inventory system is more complete.

5. **Sustained spell multi-sustain:** PF2e allows a caster to sustain multiple concurrent spells, each requiring its own 1-action "Sustain a Spell" action per round. Should the MVP support multiple concurrent sustained spells, or limit to 1 sustained spell at a time? Recommendation: support multiple sustained spells (correct RAW and simpler to implement correctly from the start than to retrofit later), but cap at 3 concurrent sustained spells as a practical UI limit for MVP.

### Delegation Table

| Role | Action |
|------|--------|
| **PM** | Finalize AC1–AC15; answer Qs 1–5; confirm focus spell MVP scope (Q1); confirm daily prep trigger (Q2) |
| **Dev (dungeoncrawler)** | Audit live spellcasting routes (`drush route:list`); confirm `character_spell_slots` and `character_prepared_spells` tables exist; implement cast/prepare/sustain routes per AC; integrate with action economy (PR-03) and condition system |
| **QA** | Test Wizard daily prep → cast → out-of-slots (AC2, AC7, AC8); test spell attack hit/miss/crit (AC5); test Reflex save 4 degrees (AC6); test cantrip heightening (AC9); test silenced block (AC11); test disruption (AC13) |
