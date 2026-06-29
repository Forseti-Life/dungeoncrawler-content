# QA Verification: dc-cr-gnome-ancestry

- **Inbox item:** `20260409-unit-test-20260409-050000-impl-dc-cr-gnome-ancestry`
- **Dev commit:** `a50c84e34`
- **Audit run:** `20260409-051852`
- **Regression checklist commit:** `687d12933`

---

## Verdict: APPROVE

---

## AC verification

### Core Stats
- `hp = 8` ✅ (not 10 — Dwarf differentiation confirmed)
- `size = 'Small'` ✅
- `speed = 25` ✅ (not 20 — no Dwarf speed penalty)
- `boosts = ['Constitution', 'Charisma', 'Free']` ✅
- `flaw = 'Strength'` ✅ (hardcoded, non-overrideable)
- `traits = ['Gnome', 'Humanoid']` ✅
- `vision = 'low-light vision'` ✅
- `languages = ['Common', 'Gnomish', 'Sylvan']` ✅

### Bonus Languages
- `special.bonus_language_per_int = 1` ✅
- `special.bonus_language_options = ['Draconic', 'Dwarven', 'Elven', 'Goblin', 'Jotun', 'Orcish']` ✅ (all 6 listed)
- `special.bonus_language_uncommon_ok = TRUE` ✅

### Heritages (exactly 5)
- `chameleon` — +2 circ Stealth in matching terrain, 1-action minor color shift, downtime dramatic shift ✅
- `fey-touched` ✅
- `sensate` ✅
- `umbral` ✅
- `wellspring` — 2 innate cantrips from chosen tradition + once-per-day innate cantrip/spell recovery ✅

### Ancestry Feats (9 confirmed)
| Feat | Key AC element | Verified |
|---|---|---|
| animal-accomplice | Familiar from limited list | ✅ |
| burrow-elocutionist | Speak with burrowing animals | ✅ |
| fey-fellowship | Full conditions sub-array (see dc-cr-fey-fellowship report) | ✅ |
| first-world-magic | Primal cantrip at will | ✅ |
| gnome-obsession | +1 circ downtime (no "Assurance feat" text — Dev corrected) | ✅ |
| gnome-weapon-familiarity | Glaive + kukri trained; gnome martial = simple | ✅ |
| illusion-sense | +1 circ Will vs illusions; auto Perception on area entry — corrected from +2 | ✅ |
| natural-performer | Performance training + +1 circ to chosen specialization | ✅ |
| vibrant-display | 2-action Visual; Will DC = 10+CHA+level; fail = fascinated; 1-min immunity | ✅ |

### Dev corrections verified
- **Gnome Obsession:** prior text incorrectly mentioned "Assurance feat"; now correctly states +1 circumstance bonus to downtime checks — matches AC ✅
- **Illusion Sense:** prior value was +2; corrected to +1 per PF2e CRB AC ✅

---

## Evidence

| Check | Result |
|---|---|
| PHP lint | No syntax errors |
| Suite `dc-cr-gnome-ancestry-e2e` | 14 TCs, activated for `20260409-dungeoncrawler-release-c`, `required_for_release: true` |
| Site audit `20260409-051852` | 0 violations, 0 failures (no new routes; ancestry selection uses existing creation flow) |
| Regression checklist | Updated to APPROVE — commit `687d12933` |
