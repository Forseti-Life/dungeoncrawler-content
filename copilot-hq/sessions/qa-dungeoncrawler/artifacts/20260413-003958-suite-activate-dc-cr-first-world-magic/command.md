- Status: done
- Completed: 2026-04-13T02:52:03Z

# Suite Activation: dc-cr-first-world-magic

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-13T00:39:58+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-first-world-magic"`**  
   This links the test to the living requirements doc at `features/dc-cr-first-world-magic/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-first-world-magic-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-first-world-magic",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-first-world-magic"`**  
   Example:
   ```json
   {
     "id": "dc-cr-first-world-magic-<route-slug>",
     "feature_id": "dc-cr-first-world-magic",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-first-world-magic",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-first-world-magic

## Coverage summary
- AC items: 9 (6 happy path, 2 edge cases, 2 failure modes)
- Test cases: 7 (TC-FWM-01–07)
- Suites: playwright (character creation, spellcasting flows)
- Security: AC exemption granted (no new routes)

---

## TC-FWM-01 — Feat selectable for Gnome at level 1
- Description: First World Magic appears in level 1 gnome ancestry feat list
- Suite: playwright/character-creation
- Expected: feat_pool[gnome][level_1] includes first-world-magic
- AC: Availability

## TC-FWM-02 — Primal cantrip selected and stored
- Description: Player selects primal cantrip at feat acquisition; stored as innate at-will spell
- Suite: playwright/character-creation
- Expected: character.innate_spells includes {cantrip, tradition: primal, at_will: true, source: first-world-magic, fixed: true}
- AC: Fixed Primal Cantrip-1, Fixed Primal Cantrip-3

## TC-FWM-03 — Cantrip is fixed (no swap)
- Description: First World Magic cantrip cannot be changed after feat acquisition
- Suite: playwright/character-creation
- Expected: no swap action available for first-world-magic innate spell; UI does not offer daily swap
- AC: Fixed Primal Cantrip-2, Failure Modes-2

## TC-FWM-04 — Cantrip at will (no use counter)
- Description: Innate cantrip has no per-day use cap
- Suite: playwright/encounter
- Expected: casting the cantrip does not decrement any use counter
- AC: Failure Modes-1

## TC-FWM-05 — Cantrip heightened by character level
- Description: Cantrip spell level = ceil(character_level / 2)
- Suite: playwright/character-creation
- Expected: at level 1 → spell level 1; at level 5 → spell level 3; at level 9 → spell level 5
- AC: Fixed Primal Cantrip-4

## TC-FWM-06 — Wellspring override applied at feat acquisition
- Description: If Wellspring Gnome, cantrip tradition overridden to wellspring_tradition on feat acquisition
- Suite: playwright/character-creation
- Expected: innate_spell.tradition = character.wellspring_tradition (not primal) when Wellspring heritage active
- AC: Wellspring Override-1, Wellspring Override-2

## TC-FWM-07 — Stacks with Fey-touched Heritage cantrip
- Description: First World Magic + Fey-touched Heritage both grant separate at-will cantrips
- Suite: playwright/character-creation
- Expected: character.innate_spells includes two separate entries (one from each source); same spell allowed
- AC: Edge Case-1, Edge Case-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-first-world-magic

## Gap analysis reference
- DB sections: core/ch02 (Gnome Ancestry Feats)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-spellcasting (innate spell subsystem), dc-cr-gnome-heritage-wellspring (tradition-override interaction)

---

## Happy Path

### Availability
- [ ] `[NEW]` First World Magic is a Gnome ancestry feat (level 1); selectable at character creation and at level 1 ancestry feat slots.

### Fixed Primal Cantrip
- [ ] `[NEW]` Player selects one primal cantrip from the primal spell list at feat acquisition time.
- [ ] `[NEW]` Selected cantrip is fixed — cannot be swapped after acquisition (unlike Fey-touched Heritage).
- [ ] `[NEW]` Cantrip is stored as a primal innate spell castable at will.
- [ ] `[NEW]` Cantrip is automatically heightened to ceil(character_level / 2).

### Wellspring Override
- [ ] `[NEW]` If character has the Wellspring Gnome heritage, the cantrip's tradition is overridden to `character.wellspring_tradition` at the moment the feat is applied.
- [ ] `[NEW]` Override is automatic; no player action required at feat selection.

---

## Edge Cases
- [ ] `[NEW]` First World Magic and Fey-touched Heritage can both be taken (different slots); each grants a separate at-will innate cantrip.
- [ ] `[NEW]` The two cantrips may be the same spell — system must allow duplicate cantrip registrations (each is a separate innate spell record).

## Failure Modes
- [ ] `[TEST-ONLY]` The cantrip is at will (not once/day or once/encounter); no use counter applies.
- [ ] `[TEST-ONLY]` The cantrip is fixed at acquisition — no in-play or per-day swap available (swap is only for Fey-touched Heritage).

## Security acceptance criteria
- Security AC exemption: game-mechanic ancestry feat; no new routes or user-facing input
