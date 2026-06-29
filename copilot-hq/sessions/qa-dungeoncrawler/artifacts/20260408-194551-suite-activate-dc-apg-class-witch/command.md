# Suite Activation: dc-apg-class-witch

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T19:45:51+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-apg-class-witch"`**  
   This links the test to the living requirements doc at `features/dc-apg-class-witch/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-apg-class-witch-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-apg-class-witch",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-apg-class-witch"`**  
   Example:
   ```json
   {
     "id": "dc-apg-class-witch-<route-slug>",
     "feature_id": "dc-apg-class-witch",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-apg-class-witch",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-apg-class-witch

## Coverage summary
- AC items: ~30 (class record, patron, familiar, spell learning, hexes, lessons, integration, edge cases)
- Test cases: 18 (TC-WCH-01–18)
- Suites: playwright (character creation, encounter, downtime)
- Security: AC exemption granted (no new routes)

---

## TC-WCH-01 — Class record and saves
- Description: HP 6+Con/level; key ability Int; Trained Fortitude/Reflex; Expert Will; no armor proficiency; trained unarmored only
- Suite: playwright/character-creation
- Expected: stats correct; armor_proficiency = unarmored only; key_ability = Int
- AC: Fundamentals-1–4

## TC-WCH-02 — Patron theme: determines tradition, skill, hex cantrip, granted spell
- Description: Patron theme selected at L1; cannot change; grants tradition, patron skill (trained), hex cantrip, familiar's first spell
- Suite: playwright/character-creation
- Expected: 7 themes available; after selection, theme locked; patron_skill granted as trained; tradition = theme's tradition
- AC: Patron-1–2

## TC-WCH-03 — Familiar: mandatory class feature
- Description: Witch must have a familiar; cannot opt out; familiar holds all spells
- Suite: playwright/character-creation
- Expected: familiar created automatically at character creation; cannot be dismissed; all spells stored in familiar
- AC: Familiar-1

## TC-WCH-04 — Familiar: bonus abilities at L1/6/12/18
- Description: Familiar gains one extra ability at L1, L6, L12, and L18
- Suite: playwright/character-creation
- Expected: extra familiar ability unlocked at each milestone level; correct count total
- AC: Familiar-2

## TC-WCH-05 — Familiar death: spells preserved, replacement at next prep
- Description: Familiar death does NOT erase known spells; new familiar with same spells restored at next daily prep
- Suite: playwright/encounter
- Expected: familiar.death → spells_list unchanged; new familiar = same spell list at next daily_prep
- AC: Familiar-3–4

## TC-WCH-06 — Witch prepared spellcasting via familiar commune
- Description: Witch uses prepared (not spontaneous) spellcasting; requires communing with familiar to prepare
- Suite: playwright/character-creation
- Expected: spellcasting_type = prepared; preparation UI shows "commune with familiar" step; no spontaneous casting
- AC: Familiar-3

## TC-WCH-07 — Spell repertoire: starting count and per-level growth
- Description: Familiar starts with 10 cantrips + 5 1st-level + 1 patron spell; +2 player-chosen spells per level-up
- Suite: playwright/character-creation
- Expected: initial familiar.spells count = 16; on each level-up: +2 player selections from tradition spell list
- AC: Repertoire-1–2

## TC-WCH-08 — Familiar learns from scrolls and between familiars
- Description: Familiar can absorb scrolls (1 hr each); two witch familiars can share spells via Learn a Spell; direct preparation from another familiar blocked
- Suite: playwright/downtime
- Expected: scroll absorption available (1 hr, consumes scroll); familiar_to_familiar transfer uses Learn a Spell (cost + both present); direct prep from foreign familiar blocked
- AC: Repertoire-3–5

## TC-WCH-09 — Hexes: cost Focus Point; one per turn; focus pool starts at 1
- Description: Regular hexes cost 1 FP; hex cantrips cost 0 FP; only one hex per turn (any type); focus pool = 1 FP
- Suite: playwright/encounter
- Expected: hex.cost = 1 FP; hex_cantrip.cost = 0; second hex attempt same turn blocked; initial focus_pool = 1
- AC: Hexes-1–4

## TC-WCH-10 — Hex cantrips: auto-heighten, no FP, one-per-turn
- Description: Hex cantrips auto-heighten to half witch level rounded up; still count as "hex used this turn"
- Suite: playwright/character-creation
- Expected: hex_cantrip.effective_level = ceil(level/2); using hex_cantrip blocks second hex that turn
- AC: Hexes-5–7

## TC-WCH-11 — Hex cantrips are separate from prepared cantrips
- Description: Hex cantrips do not occupy prepared cantrip slots
- Suite: playwright/character-creation
- Expected: character.cantrip_slots unaffected by hex cantrips; hex cantrips in separate pool
- AC: Hexes-8

## TC-WCH-12 — Refocus: 10 minutes communing with familiar = +1 FP
- Description: Refocus for witch = 10 min commune with familiar; restores 1 FP
- Suite: playwright/encounter
- Expected: refocus.activity = commune_with_familiar; duration = 10 min; fp_restored = 1
- AC: Hexes-3

## TC-WCH-13 — Witch Lessons: tiered feat mechanism
- Description: Each lesson grants one hex + one familiar spell; tiered L2/L6/L10; Basic/Greater/Major tiers
- Suite: playwright/character-creation
- Expected: lesson feats gated by tier requirements; each lesson adds 1 hex to focus options and 1 spell to familiar
- AC: Lessons-1–2

## TC-WCH-14 — Basic Lessons catalog
- Description: 5 Basic lessons (Dreams, Elements, Life, Protection, Vengeance) each grant specific hex + spell
- Suite: playwright/character-creation
- Expected: each Basic lesson grants correct hex + spell pair; accessible at L2+
- AC: Lessons-3

## TC-WCH-15 — Greater and Major Lessons catalog
- Description: Greater Lessons (Mischief, Shadow, Snow) at L6+; Major Lessons (Death, Renewal) at L10+
- Suite: playwright/character-creation
- Expected: Greater lessons blocked before L6; Major lessons blocked before L10; correct hex/spell pairs
- AC: Lessons-4–5

## TC-WCH-16 — Notable hexes: Cackle, Evil Eye, Phase Familiar
- Description: Cackle extends active hex duration +1 round; Evil Eye: 0 FP, –2 status, ends on Will save; Phase Familiar: incorporeal briefly negating one damage source
- Suite: playwright/encounter
- Expected: Cackle extends current hex (not a second hex cast); Evil Eye status penalty removed on target Will success; Phase Familiar limited to 1 damage negation
- AC: NotableHexes-1–3, Edge-1–2

## TC-WCH-17 — Integration: familiar spell list grows by 2 per level-up
- Description: After each level gained, familiar.spells grows by exactly 2 player-chosen spells
- Suite: playwright/character-creation
- Expected: level_up event triggers familiar spell selection UI; +2 spells added
- AC: Integration-3

## TC-WCH-18 — Edge: Cackle on a hex cantrip (valid extension, not second hex)
- Description: Using Cackle to extend a sustained hex cantrip is valid; Cackle does not count as casting a second hex
- Suite: playwright/encounter
- Expected: Cackle extends hex_cantrip duration; one_hex_per_turn counter = 2 (cantrip + Cackle) is allowed since Cackle extends, does not cast
- AC: Edge-3

### Acceptance criteria (reference)

# Acceptance Criteria: Witch Class Mechanics (APG)

## Feature: dc-apg-class-witch
## Source: PF2E Advanced Player's Guide, Chapter 2

---

## Class Fundamentals

- [ ] Class record: HP 6 + Con per level, key ability Intelligence
- [ ] Saves: Trained Fortitude, Trained Reflex, Expert Will
- [ ] No armor proficiency; trained only in unarmored defense
- [ ] Spellcasting tradition determined by patron theme (not fixed at class level)

---

## Patron Theme (Select at L1; Cannot Change)

- [ ] Patron theme determines: spell tradition, patron skill, hex cantrip, familiar's granted spell
- [ ] Themes: Curse (occult), Fate (occult), Fervor (divine), Night (occult), Rune (arcane), Wild (primal), Winter (primal)
- [ ] Patron skill added as Trained skill automatically

---

## Familiar

- [ ] Witch **must** have a familiar; it is a class-locked feature, not optional
- [ ] Familiar gains bonus familiar abilities at L1, L6, L12, L18 (one extra each level milestone)
- [ ] All witch spells are stored in the familiar; communing with familiar is required to prepare spells
- [ ] Familiar death: does NOT erase known spells; replacement familiar provided at next daily prep with all same spells
- [ ] Witch uses prepared (not spontaneous) spellcasting

---

## Spell Learning and Repertoire

- [ ] Familiar starts with 10 cantrips + 5 first-level spells + 1 patron-granted spell
- [ ] Each class level gained: familiar learns 2 new spells chosen by player from tradition's spell list
- [ ] Familiar can learn spells by consuming scrolls (1 hour per spell)
- [ ] Witch can use Learn a Spell to create a consumable written version for familiar to absorb
- [ ] Two witch familiars can teach each other spells via Learn a Spell activity (both familiars present; standard cost applies)
- [ ] Witch cannot prepare spells from another witch's familiar directly

---

## Hexes (Focus Spells)

- [ ] Hexes are focus spells using the focus pool and focus spell rules
- [ ] Only one hex may be cast per turn; attempting a second hex in the same turn fails (actions wasted)
- [ ] Witch starts with focus pool of 1 Focus Point
- [ ] Refocus = 10 minutes communing with familiar; restores 1 Focus Point
- [ ] Hex cantrips (e.g., Evil Eye) do **not** cost Focus Points (free to cast)
- [ ] Hex cantrips still obey the one-hex-per-turn restriction
- [ ] Hex cantrips auto-heighten to half witch level rounded up
- [ ] Hex cantrips are a separate pool from prepared cantrips (do not take up cantrip slots)

---

## Witch Lessons (Tiered Feat Mechanism)

- [ ] Each lesson grants: one hex (added to focus spell options) + one spell added to familiar
- [ ] Lesson tiers: Basic (L2+), Greater (L6+), Major (L10+)
- [ ] **Basic Lessons**: Dreams (veil of dreams + sleep), Elements (elemental betrayal + element choice), Life (life boost + spirit link), Protection (blood ward + mage armor), Vengeance (needle of vengeance + phantom pain)
- [ ] **Greater Lessons**: Mischief (deceiver's cloak + mad monkeys), Shadow (malicious shadow + chilling darkness), Snow (personal blizzard + wall of wind)
- [ ] **Major Lessons**: Death (curse of death + raise dead), Renewal (restorative moment + field of life)

---

## Notable Hexes

- [ ] `Cackle` (1-action): extends another active hex's duration by 1 round (free action in some contexts)
- [ ] `Evil Eye` (hex cantrip): no FP cost; imposes –2 status penalty (sustained); ends early if target succeeds at Will save
- [ ] `Phase Familiar` (reaction hex): familiar becomes incorporeal briefly, avoiding damage

---

## Integration Checks

- [ ] Witch spell preparation uses familiar as the spell repository (visual: "communing with familiar")
- [ ] Patron skill displayed and granted automatically at character creation
- [ ] Familiar spell list grows by 2 per level-up
- [ ] One-hex-per-turn rule enforced across both hex cantrips and regular hexes
- [ ] Focus pool starts at 1; grows with feats that add focus spells
- [ ] Lesson feats are gated by tier level requirements (Basic/Greater/Major)

## Edge Cases

- [ ] Witch familiar dies mid-session: prepared spells for the day remain available; new familiar with same spells restored at next daily prep
- [ ] Two warlocks present: spell transfer via Learn a Spell only — no direct familiar-to-familiar preparation
- [ ] Cackle on a hex cantrip: extends the cantrip's duration (if the cantrip is sustained); does not break one-hex-per-turn rule
- [ ] Evil Eye ends early on Will save success: immediate termination, no partial duration
- Agent: qa-dungeoncrawler
- Status: pending
