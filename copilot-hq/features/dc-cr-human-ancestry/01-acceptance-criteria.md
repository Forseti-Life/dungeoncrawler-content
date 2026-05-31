# Acceptance Criteria: dc-cr-human-ancestry

## Gap analysis reference
- DB sections: core/ch01 and core/ch02 — NOT YET LOADED in dc_requirements.
  See DB Gap Note in feature.md: zero rows for core/ch01 and core/ch02.
  This AC is written from source rules. A DB-load task must be dispatched separately.
- Depends on: dc-cr-ancestry-system ✓, dc-cr-heritage-system ✓, dc-cr-languages ✓, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Core Stats
- [ ] `[NEW]` Human ancestry: HP 8, Medium size, Speed 25 feet.
- [ ] `[NEW]` Ability boosts: two free ability boosts (player chooses any two different ability scores).
- [ ] `[NEW]` No ability flaw.
- [ ] `[NEW]` Traits: Human, Humanoid.
- [ ] `[NEW]` Starting languages: Common + one bonus language per positive Intelligence modifier.
- [ ] `[NEW]` Additional languages from the Regional Languages list available per Int modifier.

### Senses
- [ ] `[NEW]` Humans have standard (not low-light or darkvision) vision by default.

### Ancestry Feats (1st-level Human feats available at character creation)
- [ ] `[NEW]` Adapted Cantrip (1st, spellcasting class required): one cantrip from a different tradition added to spell list.
- [ ] `[NEW]` Cooperative Nature (1st): +4 circumstance bonus to Aid actions.
- [ ] `[NEW]` General Training (1st): one bonus general feat (non-skill general feat) taken immediately.
- [ ] `[NEW]` Haughty Obstinacy (1st): when critically failing vs. coercion/control, improve to failure; if target's Will save is higher than spell DC, save as normal.
- [ ] `[NEW]` Natural Ambition (1st): one bonus 1st-level class feat from own class.
- [ ] `[NEW]` Natural Skill (1st): trained in two additional skills of choice.
- [ ] `[NEW]` Unconventional Weaponry (1st): trained in one uncommon weapon; can select feats that require that weapon.

### Heritage Selection (mandatory at character creation — Half-Elf or Half-Orc or Versatile Heritage)
- [ ] `[NEW]` Half-Elf: gains low-light vision; can take elf ancestry feats in addition to human feats.
- [ ] `[NEW]` Half-Orc: gains low-light vision; can take orc ancestry feats in addition to human feats.
- [ ] `[NEW]` Skilled Heritage: trained in one additional skill; expert in one skill at level 5.
- [ ] `[NEW]` Versatile Heritage: one additional general feat at 1st level.
- [ ] `[NEW]` (Additional human heritages if added in later releases follow same pattern.)

---

## DB Gap Note — Action Required
- [ ] `[NEW]` Dev task dispatched: load core/ch01 (Ancestries) and core/ch02 (Heritages) requirement rows into `dc_requirements` before this feature reaches dev implementation.

---

## Edge Cases
- [ ] `[NEW]` Two free boosts: must be different ability scores (system enforces uniqueness).
- [ ] `[NEW]` Natural Ambition: restricted to 1st-level class feats from own class only.
- [ ] `[NEW]` Half-Elf/Half-Orc heritage: can take both human AND elf/orc ancestry feats — cross-ancestry feat pool enabled.

## Failure Modes
- [ ] `[TEST-ONLY]` Selecting same ability score for both free boosts: blocked.
- [ ] `[TEST-ONLY]` Natural Ambition with a 2nd-level class feat: blocked.

## Security acceptance criteria
- Security AC exemption: game-mechanic ancestry selection; no new routes or user-facing input beyond existing character creation forms
