# Implementation Notes — dc-apg-class-witch

**Commit:** `a66af1bf3`
**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php`

## What was implemented

### 1. CLASSES['witch'] expansion
Added to the existing minimal entry:
- `armor_proficiency: unarmored_only` — witch has no armor proficiency
- `familiar` block: required, stores_spells, starting_cantrips:10, starting_spells:5, bonus_ability_levels:[1,6,12,18], scroll_learning, death_note (familiar death does not erase spells)
- `hexes` block: focus_pool_start:1, refocus (commune 10 min), one_hex_per_turn:TRUE, hex_cantrips_free:TRUE, auto_heighten (half level rounded up)

### 2. WITCH_PATRONS expansion
Added three new fields to all 7 patrons:
- `patron_skill` — automatically trained at L1 (e.g., Curse→Intimidation, Wild→Nature)
- `hex_cantrip` — the patron's free hex cantrip (references WITCH_HEXES key)
- `granted_spell` — the spell the familiar knows from day 1

| Patron  | Tradition | Patron Skill | Hex Cantrip          | Granted Spell     |
|---------|-----------|--------------|----------------------|-------------------|
| Curse   | occult    | Intimidation | evil-eye             | bane              |
| Fate    | occult    | Society      | nudge-fate           | ill-omen          |
| Fervor  | divine    | Religion     | stoke-the-heart      | enfeeble          |
| Night   | occult    | Stealth      | shroud-in-darkness   | sleep             |
| Rune    | arcane    | Arcana       | discern-secrets      | magic-weapon      |
| Wild    | primal    | Nature       | wilding-word         | summon-plant      |
| Winter  | primal    | Survival     | clinging-ice         | snowball          |

### 3. CASTER_SPELL_SLOTS['witch']
Changed from simple cantrips:5/first:1 to familiar-model:
- `familiar_cantrips: 10`
- `familiar_spells: 5` (per spell level the witch has access to)
- `familiar_model: TRUE` — signals UI to show familiar spell management, not spellbook

### 4. WITCH_HEXES constant (new)
Added after CASTER_SPELL_SLOTS. Contains:
- 7 hex cantrips (one per patron): evil-eye, nudge-fate, stoke-the-heart, shroud-in-darkness, discern-secrets, wilding-word, clinging-ice
- `cackle` — free action hex extending duration
- `phase-familiar` — reaction hex (shield/teleport familiar)
- 10 lesson hexes (one per lesson feat):
  - Basic: tempest-touch, life-boost, malicious-shadow, needle-of-vengeance, pact-broker
  - Greater: curse-of-death, elemental-betrayal, ice-tomb
  - Major: sap-life, agonizing-despair

Each hex entry includes: `type` (hex_cantrip|hex), `action_cost`, `duration` (where applicable), `save` (where applicable), `one_hex_per_turn: TRUE`, and `lesson` key linking to the CLASS_FEATS entry that grants it.

### 5. CLASS_FEATS['witch'] (new section)
10 lesson feats organized by tier:
- **Basic Lessons (L2+, 5 feats):** Dreams, Earth, Mischief, Protection, Shadow
- **Greater Lessons (L6+, 3 feats):** Curse, Elements, Vengeance  
- **Major Lessons (L10+, 2 feats):** Life, Death

Each feat entry includes `lesson_tier: basic|greater|major` for UI gating.

### 6. CLASS_ADVANCEMENT['witch'] (new entry)
Key milestones matching PF2e CRB:
- L1: Patron Spellcasting, Witch's Familiar, Patron Theme, Hexes
- L3: Magical Fortitude (Fort→Expert)
- L5: Expert Spellcaster (spell attack/DC→Expert)
- L6/12/18: Familiar bonus abilities
- L9: Alertness (Perception→Expert)
- L11: Master Spellcaster (spell attack/DC→Master) + Resolve (Will→Master)
- L13: Weapon Expertise (simple weapons→Expert)
- L15: Evasion (Reflex→Expert)
- L17: Legendary Spellcaster (spell attack/DC→Legendary)
- L19: Patron's Gift (1 extra 10th-rank spell/day)

## Design decisions

### One-hex-per-turn enforcement
Both the CLASSES['witch']['hexes'] block and each WITCH_HEX entry carry `one_hex_per_turn: TRUE`. The data layer documents the rule; the UI layer enforces it. Hex cantrips and regular hexes share the same per-turn restriction.

### Familiar model vs. prepared model
`familiar_model: TRUE` in CASTER_SPELL_SLOTS is a flag for the spellcasting UI to distinguish witch from wizard/cleric. Witch prepares spells by choosing from familiar; wizard prepares from spellbook. Same mechanic, different framing.

### Auto-heighten for hex cantrips
Documented in CLASSES['witch']['hexes']['auto_heighten'] = 'half_level_rounded_up'. Individual hex cantrips also carry `auto_heighten: TRUE`. This matches PF2e rules without needing per-spell-level copies of the hex.

### Patron subclass resolution
Witch uses the same pattern as sorcerer: `character_data['subclass'] ?? character_data['patron']` — already in `resolveClassTradition()`, no changes needed.

### Scroll learning
`familiar.scroll_learning: TRUE` documents that witches can have their familiar learn spells from scrolls (consuming the scroll). UI layer implements the flow.
