- Status: done
- Completed: 2026-04-14T00:14:54Z

# Suite Activation: dc-cr-gnome-heritage-wellspring

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T00:11:33+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-gnome-heritage-wellspring"`**  
   This links the test to the living requirements doc at `features/dc-cr-gnome-heritage-wellspring/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-gnome-heritage-wellspring-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-gnome-heritage-wellspring",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-gnome-heritage-wellspring"`**  
   Example:
   ```json
   {
     "id": "dc-cr-gnome-heritage-wellspring-<route-slug>",
     "feature_id": "dc-cr-gnome-heritage-wellspring",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-gnome-heritage-wellspring",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-gnome-heritage-wellspring

## Coverage summary
- AC items: 10 (7 happy path, 2 edge cases, 2 failure modes)
- Test cases: 8 (TC-WEL-01–08)
- Suites: playwright (character creation, spellcasting flows)
- Security: AC exemption granted (no new routes)

---

## TC-WEL-01 — Tradition selection stored
- Description: Player chooses arcane, divine, or occult; stored as wellspring_tradition
- Suite: playwright/character-creation
- Expected: character.wellspring_tradition ∈ {arcane, divine, occult} after heritage selection
- AC: Tradition Selection-1, Tradition Selection-2

## TC-WEL-02 — Primal not available as tradition choice
- Description: Primal is not an option when selecting Wellspring tradition
- Suite: playwright/character-creation
- Expected: tradition_options = [arcane, divine, occult]; primal absent
- AC: Edge Case-1

## TC-WEL-03 — Cantrip from chosen tradition stored at will
- Description: Cantrip from wellspring_tradition list stored as innate at-will spell
- Suite: playwright/character-creation
- Expected: character.innate_spells includes {cantrip, tradition: wellspring_tradition, at_will: true}
- AC: At-Will Cantrip-2, At-Will Cantrip-3

## TC-WEL-04 — Cantrip heightened by character level
- Description: Cantrip spell level = ceil(character_level / 2)
- Suite: playwright/character-creation
- Expected: at level 1 → spell level 1; at level 7 → spell level 4
- AC: At-Will Cantrip-4

## TC-WEL-05 — Cantrip cast at will (unlimited)
- Description: Wellspring cantrip has no per-day use cap
- Suite: playwright/encounter
- Expected: casting cantrip does not decrement any use counter
- AC: Failure Modes-1

## TC-WEL-06 — First World Magic tradition overridden
- Description: If character takes First World Magic feat, its primal innate spell is overridden to wellspring_tradition
- Suite: playwright/character-creation
- Expected: first-world-magic innate spell tradition = wellspring_tradition (not primal) when Wellspring heritage active
- AC: Tradition Override-1, Tradition Override-2

## TC-WEL-07 — Override applies to all gnome-ancestry primal innate spells
- Description: Every gnome ancestry feat that grants primal innate spells has its tradition overridden
- Suite: playwright/character-creation
- Expected: all gnome-ancestry innate spell records with tradition = primal are updated to wellspring_tradition
- AC: Tradition Override-3

## TC-WEL-08 — Override does not affect class spells
- Description: Tradition override is scoped to gnome ancestry feat innate spells only
- Suite: playwright/character-creation
- Expected: class innate spells and non-gnome feat innate spells retain their original tradition
- AC: Failure Modes-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-gnome-heritage-wellspring

## Gap analysis reference
- DB sections: core/ch02 (Gnome Heritages)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-spellcasting (innate spell subsystem), dc-cr-first-world-magic (tradition-override interaction)

---

## Happy Path

### Tradition Selection
- [ ] `[NEW]` At heritage selection, player chooses one non-primal magical tradition: arcane, divine, or occult.
- [ ] `[NEW]` Chosen tradition is stored in `character.wellspring_tradition` (persistent character field).

### At-Will Cantrip from Chosen Tradition
- [ ] `[NEW]` Player selects one cantrip from the chosen tradition's spell list at heritage selection.
- [ ] `[NEW]` Cantrip is stored as an at-will innate spell using the chosen tradition (not primal).
- [ ] `[NEW]` Cantrip is automatically heightened to ceil(character_level / 2).

### Tradition Override for Gnome Ancestry Feats
- [ ] `[NEW]` Any primal innate spell gained from a gnome ancestry feat (e.g., First World Magic) is overridden to the `wellspring_tradition` at the time the feat is applied.
- [ ] `[NEW]` The override is automatic — no player action required at feat selection.
- [ ] `[NEW]` Override applies to future feats as well (all gnome ancestry feats with innate primal spells).

---

## Edge Cases
- [ ] `[NEW]` Primal tradition choice is not available; player must choose arcane, divine, or occult.
- [ ] `[NEW]` If `wellspring_tradition` changes (not normally possible without a character rebuild), all gnome-ancestry innate spells re-override to the new value.

## Failure Modes
- [ ] `[TEST-ONLY]` Cantrip is at-will (not once/day); tradition override is on the spell's tradition tag, not the cantrip casting frequency.
- [ ] `[TEST-ONLY]` Override applies to gnome ancestry feat innate spells ONLY — not to class spells or non-gnome-ancestry innate spells.

## Security acceptance criteria
- Security AC exemption: game-mechanic heritage; no new routes or user-facing input beyond character creation
