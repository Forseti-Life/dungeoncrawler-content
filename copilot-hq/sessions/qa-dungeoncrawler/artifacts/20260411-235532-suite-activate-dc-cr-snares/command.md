- Status: done
- Completed: 2026-04-12T04:52:53Z

# Suite Activation: dc-cr-snares

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-11T23:55:32+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-snares"`**  
   This links the test to the living requirements doc at `features/dc-cr-snares/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-snares-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-snares",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-snares"`**  
   Example:
   ```json
   {
     "id": "dc-cr-snares-<route-slug>",
     "feature_id": "dc-cr-snares",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-snares",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-snares

## Coverage summary
- AC items: 14 (10 happy path, 2 edge cases, 2 failure modes)
- Test cases: 14 (TC-SNR-01–14)
- Suites: playwright (exploration, encounter, crafting)
- Security: AC exemption granted (no new routes)
- Note: These tests EXTEND dc-cr-magic-ch11 coverage; snares are a distinct ranger/crafting subsystem feature.

---

## TC-SNR-01 — Snare Crafting feat gate
- Description: Snares require the Snare Crafting feat; attempt without it is blocked
- Suite: playwright/crafting
- Expected: snare_craft attempt without Snare Crafting feat → blocked; not just penalized
- AC: Crafting-1, Failure-1

## TC-SNR-02 — Snare kit requirement
- Description: Snare Crafting also requires a snare kit in inventory
- Suite: playwright/crafting
- Expected: snare_craft attempt without snare_kit in inventory → blocked
- AC: Crafting-1

## TC-SNR-03 — Snare occupies one 5-ft square; cannot be relocated
- Description: Placed snare occupies exactly one 5-ft square and cannot be moved after placement
- Suite: playwright/exploration
- Expected: snare.size = 5ft_square; relocation attempt after placement → blocked
- AC: Crafting-2

## TC-SNR-04 — Quick crafting: 1 minute at full price
- Description: Quick-craft snare takes 1 minute and costs full item price
- Suite: playwright/exploration
- Expected: quick_craft.duration = 1 min; cost = snare.full_price; produces placed snare immediately
- AC: Crafting-3

## TC-SNR-05 — Discounted crafting: downtime Craft activity
- Description: Downtime Craft activity produces a snare at reduced cost per standard Craft rules
- Suite: playwright/crafting
- Expected: downtime_craft.type = snare; cost < full_price; duration follows Craft activity rules
- AC: Crafting-3

## TC-SNR-06 — Crafted snare placed in square, not stored in inventory
- Description: Crafting a snare places it in a square; no inventory item is created
- Suite: playwright/crafting
- Expected: after craft completion: snare exists as placed_object in square; character.inventory unchanged
- AC: Crafting-4

## TC-SNR-07 — Detection DC and Disable DC equal creator's Crafting DC
- Description: Detection DC = creator's Crafting DC; Disable DC (Thievery) = same value
- Suite: playwright/exploration
- Expected: snare.detection_dc = creator.crafting_dc; snare.disable_dc = creator.crafting_dc
- AC: Detection-1

## TC-SNR-08 — Expert+ Crafter snares: active-search only
- Description: Snares made by Expert+ crafter can only be found by actively-searching creatures; passive observers automatically fail
- Suite: playwright/exploration
- Expected: snare.expert_crafter = true → passive_perception fails automatically; only Search action detects
- AC: Detection-2
- Note: This is the active vs. passive Perception distinction. Dev must implement an explicit "passive observer fails" path, not just a high DC.

## TC-SNR-09 — Detection proficiency gates
- Description: Minimum Perception proficiency required to detect/disable based on creator's Crafting proficiency
- Suite: playwright/exploration
- Expected: snare proficiency gates enforce Trained/Expert/Master thresholds for detection and disabling
- AC: Detection-3

## TC-SNR-10 — Creator self-disarm: 1 Interact action, no check
- Description: Creator can disarm their own snare with 1 Interact action while adjacent; no Thievery check required
- Suite: playwright/exploration
- Expected: creator adjacent → disarm_action = 1 Interact; no check rolled; snare removed
- AC: Detection-4, Failure-2

## TC-SNR-11 — Snare type: Alarm Snare
- Description: Triggers audible alarm on activation; loud noise in 300-ft radius
- Suite: playwright/encounter
- Expected: alarm_snare.triggered = true → noise event fires with 300-ft radius
- AC: Types-1

## TC-SNR-12 — Snare type: Hampering Snare
- Description: Triggering creature's terrain becomes difficult or greater difficult; persists for limited time
- Suite: playwright/encounter
- Expected: hampering_snare.triggered → triggering_creature terrain_effect = difficult_terrain; duration = per snare spec
- AC: Types-2

## TC-SNR-13 — Snare type: Marking Snare
- Description: Applies a visual marker to triggering creature
- Suite: playwright/encounter
- Expected: marking_snare.triggered → triggering_creature.condition includes visual_marker; useful for tracking
- AC: Types-3

## TC-SNR-14 — Snare type: Striking Snare
- Description: Deals physical damage to triggering creature; damage scales with snare level
- Suite: playwright/encounter
- Expected: striking_snare.triggered → triggering_creature takes physical damage per snare level table
- AC: Types-4

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-snares

## Gap analysis reference
- DB sections: core/ch11/Snares (7 reqs) — already covered in dc-cr-magic-ch11 and flipped to in_progress.
  This feature focuses on snares as a distinct ranger/crafting subsystem.
- Depends on: dc-cr-equipment-system ✓, dc-cr-skill-system ✓, dc-cr-class-ranger

---

## Happy Path

### Crafting Snares
- [ ] `[EXTEND]` Snares require the Snare Crafting feat and a snare kit.
- [ ] `[EXTEND]` Snares occupy one 5-ft square and cannot be relocated after placement.
- [ ] `[EXTEND]` Quick crafting: 1 minute at full price; discounted version: downtime Craft activity.
- [ ] `[EXTEND]` Crafting produces a functional snare in place; no inventory item stored.

### Detection and Disabling
- [ ] `[EXTEND]` Detection DC = creator's Crafting DC; Disable DC = same (Thievery skill).
- [ ] `[EXTEND]` Expert+ Crafter snares: only found by actively-searching creatures (passive observers fail automatically).
- [ ] `[EXTEND]` Detection/disable minimum proficiency gates: Trained/Expert/Master based on creator's Crafting proficiency.
- [ ] `[EXTEND]` Creator disarms their own snare: 1 Interact action while adjacent (no check required).

### Snare Types (initial catalog)
- [ ] `[NEW]` Alarm Snare: triggers an audible alarm on trigger condition (loud noise in 300-ft radius).
- [ ] `[NEW]` Hampering Snare: terrain becomes difficult or greater difficult terrain for triggering creature; persists for limited time.
- [ ] `[NEW]` Marking Snare: applies a visual marker to the triggering creature (useful for tracking).
- [ ] `[NEW]` Striking Snare: deals physical damage to triggering creature (damage scales with snare level).

---

## Edge Cases
- [ ] `[EXTEND]` Snare detection while actively searching: normal Perception check vs Detection DC.
- [ ] `[EXTEND]` Snare placement in occupied square: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Snare Crafting without Snare Crafting feat: blocked (not just penalized).
- [ ] `[TEST-ONLY]` Creator disarms own snare without Thievery check: 1 Interact action only.

## Security acceptance criteria
- Security AC exemption: game-mechanic snare crafting and placement logic; no new routes or user-facing input beyond existing exploration and encounter handlers
- Agent: qa-dungeoncrawler
- Status: pending
