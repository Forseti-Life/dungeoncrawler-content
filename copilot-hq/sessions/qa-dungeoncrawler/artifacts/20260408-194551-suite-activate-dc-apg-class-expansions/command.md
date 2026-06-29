# Suite Activation: dc-apg-class-expansions

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
   **CRITICAL: tag every new entry with `"feature_id": "dc-apg-class-expansions"`**  
   This links the test to the living requirements doc at `features/dc-apg-class-expansions/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-apg-class-expansions-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-apg-class-expansions",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-apg-class-expansions"`**  
   Example:
   ```json
   {
     "id": "dc-apg-class-expansions-<route-slug>",
     "feature_id": "dc-apg-class-expansions",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-apg-class-expansions",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-apg-class-expansions

## Coverage summary
- AC items: ~30 (6 class expansions + integration + edge cases)
- Test cases: 16 (TC-EXP-01–16)
- Suites: playwright (character creation, encounter, downtime)
- Security: AC exemption granted (no new routes)

---

## TC-EXP-01 — Alchemist: Toxicologist research field available
- Description: Toxicologist appears as a selectable research field option for Alchemist
- Suite: playwright/character-creation
- Expected: research_field_options includes Toxicologist; selecting it grants 2 common 1st-level poison formulas
- AC: Toxicologist-1–2

## TC-EXP-02 — Alchemist Toxicologist: 1-action injury poison application
- Description: Applying injury poison costs 1 action (not 2) for Toxicologist
- Suite: playwright/encounter
- Expected: apply_poison.action_cost = 1 for Toxicologist (standard = 2)
- AC: Toxicologist-3, Integration-1

## TC-EXP-03 — Alchemist Toxicologist: class DC substitution for infused poisons
- Description: May substitute class DC for poison save DC when using infused poisons
- Suite: playwright/encounter
- Expected: infused_poison.save_dc = max(item_dc, class_dc) — or class_dc when using infused
- AC: Toxicologist-4

## TC-EXP-04 — Alchemist Toxicologist: L5 and L15 discoveries
- Description: L5 Field Discovery = 3 poisons per batch; L15 Greater Discovery = apply two injury poisons simultaneously with lower DC
- Suite: playwright/character-creation
- Expected: at L5: poison_batch_size = 3; at L15: double_poison_application = enabled; combined DC = min(dc1, dc2)
- AC: Toxicologist-5–6, Edge-1

## TC-EXP-05 — Barbarian: Superstition instinct anathema
- Description: Superstition anathema: willingly accepting any magical spell effects (including from allies); does NOT restrict potions or non-spell magic activations
- Suite: playwright/character-creation
- Expected: anathema_flag set; spell effect acceptance triggers warning; potions/non-spell activations bypass flag
- AC: Superstition-1–4

## TC-EXP-06 — Bard: Warrior muse grants Martial Performance and fear
- Description: Warrior muse: Martial Performance feat granted at L1; fear added to repertoire at L1
- Suite: playwright/character-creation
- Expected: warrior_muse selection → Martial Performance feat in character.feats; fear spell in repertoire
- AC: WarriorMuse-1–2

## TC-EXP-07 — Bard Warrior muse: Song of Strength
- Description: Song of Strength composition cantrip (+2 circumstance to Athletics); unlocked via L2 feat for warrior muse bards
- Suite: playwright/character-creation
- Expected: Song of Strength available at L2 feat slot for warrior muse only; +2 circumstance Athletics bonus when active
- AC: WarriorMuse-3

## TC-EXP-08 — Champion: evil alignment options behind Uncommon gate
- Description: Evil champion options are Uncommon; require GM access grant; have appropriate alignment-based reaction and devotion spells
- Suite: playwright/character-creation
- Expected: evil_champion_options.availability = uncommon; blocked without GM approval; parallel structure to good champion
- AC: Champion-1–3, Integration-4

## TC-EXP-09 — Rogue: Eldritch Trickster racket
- Description: Free multiclass spellcasting dedication at L1; Magical Trickster feat available at L2 (down from L4)
- Suite: playwright/character-creation
- Expected: eldritch_trickster selection → free_multiclass_dedication at L1; Magical Trickster appears at L2
- AC: Rogue-1–2

## TC-EXP-10 — Rogue: Mastermind racket
- Description: Int as key ability; gains Society + one knowledge Lore; successful Recall Knowledge → flat-footed until next turn; crit success = 1 minute
- Suite: playwright/encounter
- Expected: key_ability = Int; skills.society trained; recall_knowledge.success → target.flat_footed until next turn; crit_success → 1 minute
- AC: Rogue-3–4, Edge-2, Integration-6

## TC-EXP-11 — Sorcerer: Genie bloodline
- Description: Arcane spell list; subtype selection at L1 (Janni/Djinni/Efreeti/Marid/Shaitan); determines granted spells
- Suite: playwright/character-creation
- Expected: genie bloodline selectable; 5 subtypes available; subtype stored and used for spell lookup; required choice
- AC: Sorcerer-1–2, Edge-3

## TC-EXP-12 — Sorcerer: Nymph bloodline
- Description: Primal spell list; follows standard bloodline structure
- Suite: playwright/character-creation
- Expected: nymph bloodline = primal tradition; granted_spells, bloodline_spells, blood_magic all implemented
- AC: Sorcerer-3

## TC-EXP-13 — Wizard: Staff Nexus arcane thesis
- Description: Staff Nexus as arcane thesis option; makeshift staff (1 cantrip + 1 1st-level spell); charges via expended spell slots only
- Suite: playwright/character-creation
- Expected: Staff Nexus in thesis selection; makeshift_staff created with 1 cantrip + 1 1st-level; charges = sum of expended slots
- AC: StaffNexus-1–3, Integration-7, Edge-4

## TC-EXP-14 — Wizard Staff Nexus: L8 and L16 slot-stacking
- Description: L8: may expend 2 spell slots; L16: up to 3 spell slots; charges = sum of spell levels
- Suite: playwright/character-creation
- Expected: at L8: max_slots_per_charge = 2; at L16: max_slots_per_charge = 3; charges = sum of all expended spell levels
- AC: StaffNexus-4–5

## TC-EXP-15 — Wizard Staff Nexus: cantrip in makeshift staff is free
- Description: Cantrip stored in makeshift staff does not cost a charge to cast
- Suite: playwright/encounter
- Expected: cantrip_cast_from_staff.charges_spent = 0
- AC: Edge-4

## TC-EXP-16 — Integration: each expansion appears in correct class selection screen
- Description: All 6 expansion options visible in their respective class's selection screen
- Suite: playwright/character-creation
- Expected: Toxicologist in Alchemist Research Field; Superstition in Barbarian Instinct; Warrior in Bard Muse; evil options in Champion (Uncommon); Eldritch Trickster/Mastermind in Rogue Racket; Genie/Nymph in Sorcerer Bloodline; Staff Nexus in Wizard Thesis
- AC: Integration-1–7

### Acceptance criteria (reference)

# Acceptance Criteria: APG Core Class Expansions

## Feature: dc-apg-class-expansions
## Source: PF2E Advanced Player's Guide, Chapter 2 (Core Class Expansions)

---

## Alchemist — Toxicologist Research Field

- [ ] Toxicologist added as a new research field option for Alchemist
- [ ] Starts with 2 common 1st-level alchemical poison formulas in formula book
- [ ] Applying injury poison costs 1 action (standard is 2 actions)
- [ ] May substitute class DC for poison save DC when using infused poisons
- [ ] L5 Field Discovery: create 3 poisons per batch (instead of 2)
- [ ] L15 Greater Field Discovery: apply two injury poisons to same weapon simultaneously; combined as double poison with lower DC; cannot use perpetual poisons with this option

---

## Barbarian — Superstition Instinct

- [ ] Superstition added as new instinct option for Barbarian
- [ ] Anathema: willingly accepting magical spell effects (from any source)
- [ ] Restriction includes spell-casting items (wands, scrolls that cast spells)
- [ ] Does **not** restrict potions or non-spell magic item activations
- [ ] Continuing to associate with allies who repeatedly cast magic on the barbarian against their will is also anathema

---

## Bard — Warrior Muse

- [ ] Warrior added as a new muse option for Bard
- [ ] Warrior muse grants Martial Performance feat at L1
- [ ] Adds fear to repertoire at L1
- [ ] `Song of Strength` composition cantrip unlocked via a L2 feat for warrior muse bards; grants +2 circumstance bonus to Athletics checks for duration

---

## Champion — Evil Alignment Options

- [ ] Champion system supports evil-aligned options as Uncommon access
- [ ] Evil champion tenets require GM access grant (flagged as Uncommon in UI)
- [ ] Evil champions gain appropriate alignment-based champion's reaction and devotion spells (parallel to good champion structure)

---

## Rogue — New Rackets

- [ ] Eldritch Trickster: free multiclass spellcasting archetype dedication at L1
- [ ] Eldritch Trickster: Magical Trickster feat available at L2 (down from L4)
- [ ] Mastermind: Intelligence as key ability; gains Society + one knowledge skill
- [ ] Mastermind: successful Recall Knowledge renders target flat-footed until next turn; critical success = 1 minute
- [ ] Both rackets allow Intelligence as the key ability score choice

---

## Sorcerer — New Bloodlines

- [ ] Genie bloodline: arcane spell list
- [ ] Genie bloodline subtype selection at L1: Janni, Djinni, Efreeti, Marid, or Shaitan; determines certain granted spells
- [ ] Nymph bloodline: primal spell list
- [ ] Both bloodlines follow standard bloodline structure (granted spells, bloodline spells, blood magic effect)

---

## Wizard — Staff Nexus Arcane Thesis

- [ ] Staff Nexus added as new arcane thesis option for Wizard
- [ ] Wizard starts with a makeshift staff: 1 cantrip + 1 first-level spell from spellbook
- [ ] Makeshift staff gains charges only via expended spell slots (not standard staff charging rules)
- [ ] L8: may expend 2 spell slots; combined spell levels = charges added
- [ ] L16: may expend up to 3 spell slots; combined spell levels = charges added
- [ ] Can Craft makeshift staff into any standard staff type (standard cost); retains the two original spells

---

## Integration Checks

- [ ] Each expansion option appears within the correct class's selection screen (research field, instinct, muse, racket, bloodline, thesis)
- [ ] Toxicologist 1-action poison application replaces standard 2-action in action economy
- [ ] Superstition anathema triggers a warning/tracking flag when relevant (GM tool)
- [ ] Warrior muse Martial Performance feat granted automatically at bard creation
- [ ] Evil champion options gated behind GM unlock / Uncommon access flag
- [ ] Mastermind flat-footed state from Recall Knowledge correctly linked to timing (until next turn / 1 minute)
- [ ] Staff Nexus makeshift staff charge rules use expended-slot logic, not passive daily recharge

## Edge Cases

- [ ] Toxicologist double-poison at L15: both poisons' effects apply; save DC = lower of the two DCs
- [ ] Mastermind Recall Knowledge flat-footed: applies to ALL attacks against the target this turn (not just investigator)
- [ ] Genie subtype: if subtype not selected at creation, flag as incomplete character (required choice)
- [ ] Staff Nexus cantrip in makeshift staff: does not cost a charge to cast (cantrips are free)
- Agent: qa-dungeoncrawler
- Status: pending
