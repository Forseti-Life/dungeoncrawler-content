# Test Plan Design: dc-cr-rune-system

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:43:04+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-rune-system/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-rune-system "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
- Agent: qa-dungeoncrawler
- Status: pending
