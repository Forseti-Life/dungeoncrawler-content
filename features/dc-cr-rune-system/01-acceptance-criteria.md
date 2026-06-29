# Acceptance Criteria: dc-cr-rune-system

## Gap analysis reference
- DB sections: core/ch11/Runes (19 reqs) and core/ch11/Precious Materials (18 reqs)
  Note: These were already covered in dc-cr-magic-ch11 and flipped to in_progress.
  This feature focuses on the rune system as a distinct subsystem (pre-ch11 feature stub).
- Depends on: dc-cr-equipment-system ✓, dc-cr-magic-system

---

## Happy Path

### Fundamental Runes
- [ ] `[EXTEND]` Weapon potency runes: +1, +2, +3 — each adds attack bonus and unlocks property rune slots equal to potency value.
- [ ] `[EXTEND]` Striking runes: Striking (2d), Greater Striking (3d), Major Striking (4d) — increases weapon damage dice count.
- [ ] `[EXTEND]` Armor potency runes: +1, +2, +3 — each adds item bonus to AC and unlocks property rune slots.
- [ ] `[EXTEND]` Resilient runes: Resilient (+1 saves), Greater Resilient (+2), Major Resilient (+3).

### Property Runes
- [ ] `[EXTEND]` Property rune slots = potency rune value (0 slots without potency rune).
- [ ] `[EXTEND]` Duplicate property runes: only higher-level applies (exception: energy-resistant, different damage types all apply).
- [ ] `[EXTEND]` Orphaned property runes (potency rune removed): go dormant until compatible potency rune present.
- [ ] `[EXTEND]` Energy-resistant property runes can stack if each is a different energy type.

### Etching and Transferring Runes
- [ ] `[EXTEND]` Etch a Rune: Craft activity (downtime); requires Magical Crafting feat, formula, item in possession; one rune at a time.
- [ ] `[EXTEND]` Transfer Rune: Craft activity; DC by rune level; cost = 10% of rune price; minimum 1 day.
- [ ] `[EXTEND]` Transfer from runestone: free (no cost).
- [ ] `[EXTEND]` Incompatible rune transfer: automatic critical failure.
- [ ] `[EXTEND]` Only same-category swaps: fundamental ↔ fundamental, property ↔ property.
- [ ] `[EXTEND]` Item upgrade Crafting: cost = (new rune price) – (existing rune price); DC uses new rune level.

### Precious Materials
- [ ] `[EXTEND]` Items have at most one precious material.
- [ ] `[EXTEND]` Material grades: Low (Expert Crafting, max level 8), Standard (Master Crafting, max level 15), High (Legendary Crafting, no restriction).
- [ ] `[EXTEND]` Investment percentages: Low = 10%, Standard = 25%, High = 100% of initial cost.
- [ ] `[EXTEND]` All material Hardness/HP/BT values implemented (Adamantine, Cold Iron, Darkwood, Dragonhide, Mithral, Orichalcum, Silver, plus base materials).
- [ ] `[EXTEND]` Cold iron, adamantine, darkwood, dragonhide, mithral, orichalcum: special material properties implemented per dc-cr-magic-ch11.

---

## Edge Cases
- [ ] `[EXTEND]` Rune slots blocked without potency rune: property runes cannot be etched.
- [ ] `[EXTEND]` Specific locked magic items (0 property slots): only fundamental runes allowed.

## Failure Modes
- [ ] `[TEST-ONLY]` Incompatible rune transfer: auto-crit fail (does not charge cost).
- [ ] `[TEST-ONLY]` Orphaned property rune: dormant, not deleted; reactivates when compatible potency present.

## Security acceptance criteria
- Security AC exemption: game-mechanic rune and material system; no new routes or user-facing input beyond existing character creation and inventory management forms
