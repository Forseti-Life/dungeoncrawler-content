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
