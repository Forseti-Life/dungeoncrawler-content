- Status: done
- Completed: 2026-04-13T05:42:47Z

# Suite Activation: dc-cr-gnome-heritage-fey-touched

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-13T03:30:51+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-gnome-heritage-fey-touched"`**  
   This links the test to the living requirements doc at `features/dc-cr-gnome-heritage-fey-touched/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-gnome-heritage-fey-touched-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-gnome-heritage-fey-touched",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-gnome-heritage-fey-touched"`**  
   Example:
   ```json
   {
     "id": "dc-cr-gnome-heritage-fey-touched-<route-slug>",
     "feature_id": "dc-cr-gnome-heritage-fey-touched",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-gnome-heritage-fey-touched",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-gnome-heritage-fey-touched

## Coverage summary
- AC items: 10 (7 happy path, 2 edge cases, 2 failure modes)
- Test cases: 8 (TC-FTG-01–08)
- Suites: playwright (character creation, spellcasting flows)
- Security: AC exemption granted (no new routes)

---

## TC-FTG-01 — fey trait added
- Description: Fey-touched Gnome gains the fey trait
- Suite: playwright/character-creation
- Expected: character.traits includes [Gnome, Humanoid, fey]
- AC: Traits

## TC-FTG-02 — Primal cantrip selectable at character creation
- Description: Player must choose one primal cantrip from spell list; stored as innate spell
- Suite: playwright/character-creation
- Expected: character.innate_spells includes selected primal cantrip; tradition = primal; at_will = true
- AC: At-Will Primal Cantrip-1, At-Will Primal Cantrip-2

## TC-FTG-03 — Cantrip heightened by character level
- Description: Cantrip spell level = ceil(character_level / 2)
- Suite: playwright/character-creation
- Expected: at level 1 → spell level 1; at level 5 → spell level 3; at level 9 → spell level 5
- AC: At-Will Primal Cantrip-3, Failure Modes-2

## TC-FTG-04 — Cantrip cast at will (unlimited)
- Description: Innate cantrip has no use-per-day cap
- Suite: playwright/encounter
- Expected: casting the cantrip does not decrement a use counter; can be cast multiple times per encounter
- AC: Failure Modes-1

## TC-FTG-05 — Daily cantrip swap: 10-minute activity
- Description: Swap action is 10 min, concentrate; replacement must be from primal list
- Suite: playwright/downtime
- Expected: swap_action.duration = 10 min; swap_action.traits includes concentrate; replacement from primal list only
- AC: Daily Cantrip Swap-1, Daily Cantrip Swap-2

## TC-FTG-06 — Daily swap resets at preparation
- Description: Only one swap per day; resets on long rest / daily preparation
- Suite: playwright/downtime
- Expected: swap_used_today = true blocks second swap; resets to false on new day
- AC: Daily Cantrip Swap-3, Edge Case-2

## TC-FTG-07 — Second swap attempt blocked
- Description: Attempting a second same-day swap triggers a system block message
- Suite: playwright/downtime
- Expected: second swap attempt returns error "Daily cantrip swap already used"
- AC: Edge Case-2

## TC-FTG-08 — fey trait does not replace other traits
- Description: fey trait is additive, not a replacement
- Suite: playwright/character-creation
- Expected: character.traits = [Gnome, Humanoid, fey] (all three present)
- AC: Edge Case-1

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-gnome-heritage-fey-touched

## Gap analysis reference
- DB sections: core/ch02 (Gnome Heritages)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-spellcasting (innate spell subsystem), dc-cr-gnome-heritage-wellspring (tradition override path)

---

## Happy Path

### Traits
- [ ] `[NEW]` Character gains the fey trait in addition to Gnome and Humanoid.

### At-Will Primal Cantrip
- [ ] `[NEW]` Player selects one cantrip from the primal spell list at heritage selection time.
- [ ] `[NEW]` Cantrip is stored on the character record as a primal innate spell castable at will.
- [ ] `[NEW]` Cantrip is automatically heightened to a spell level equal to ceil(character_level / 2).

### Daily Cantrip Swap
- [ ] `[NEW]` Character can swap the selected cantrip once per day via a 10-minute activity tagged with the concentrate trait.
- [ ] `[NEW]` Replacement cantrip must be chosen from the primal spell list.
- [ ] `[NEW]` Swap resets at daily preparation (24-hour period / long rest).

### Wellspring Override Integration
- [ ] `[NEW]` If character also has Wellspring Gnome heritage (not normally stacked — but tradition-override flag applies if a multiclass/variant rule grants both), the cantrip tradition is overridden to the Wellspring tradition.

---

## Edge Cases
- [ ] `[NEW]` fey trait adds to character traits — does NOT replace Gnome or Humanoid.
- [ ] `[NEW]` Only one cantrip swap allowed per day; attempting a second swap in the same day is blocked with a system message.

## Failure Modes
- [ ] `[TEST-ONLY]` Cantrip is at-will (not once/day); the daily-swap is the limited action, not the casting itself.
- [ ] `[TEST-ONLY]` Heightening must be dynamic — if character level increases, the cantrip level re-calculates automatically.

## Security acceptance criteria
- Security AC exemption: game-mechanic heritage; no new routes or user-facing input beyond character creation
- Agent: qa-dungeoncrawler
- Status: pending
