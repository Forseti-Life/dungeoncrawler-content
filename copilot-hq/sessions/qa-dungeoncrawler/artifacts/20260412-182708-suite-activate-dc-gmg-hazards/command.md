- Status: done
- Completed: 2026-04-12T22:59:55Z

# Suite Activation: dc-gmg-hazards

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:27:08+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-gmg-hazards"`**  
   This links the test to the living requirements doc at `features/dc-gmg-hazards/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-gmg-hazards-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-gmg-hazards",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-gmg-hazards"`**  
   Example:
   ```json
   {
     "id": "dc-gmg-hazards-<route-slug>",
     "feature_id": "dc-gmg-hazards",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-gmg-hazards",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-gmg-hazards

## Coverage summary
- AC items: ~22 (hazard stat blocks, simple/complex/haunt types, disabled/destroyed/reset states, XP, NPC gallery integration)
- Test cases: 10 (TC-HAZ-01–10)
- Suites: playwright (encounter, character creation)
- Security: AC exemption granted (no new routes)

---

## TC-HAZ-01 — Hazard stat block completeness
- Description: Every hazard has all required fields: Stealth DC, Disable DC, AC, Saves, HP, Hardness, Immunities, Weaknesses, Resistances, Actions
- Suite: playwright/encounter
- Expected: hazard detail view shows all fields; no null/missing required stat block fields
- AC: Hazard-1

## TC-HAZ-02 — Hazard detection: Stealth DC vs. Perception check
- Description: Hazard Stealth DC used for initial detection; Perception check vs. Stealth DC determines awareness
- Suite: playwright/encounter
- Expected: entering area → auto Perception check vs. hazard.stealth_dc; success → hazard revealed; fail → hazard hidden
- AC: Hazard-2

## TC-HAZ-03 — Hazard disarming: Disable DC via applicable skill
- Description: Disable check uses applicable skill (Thievery, Arcana, etc.) vs. Disable DC
- Suite: playwright/encounter
- Expected: disable action shows correct skill check; DC sourced from hazard.disable_dc; varied by hazard type
- AC: Hazard-3

## TC-HAZ-04 — Simple hazards: resolve in one action, no initiative
- Description: Simple hazards resolve on trigger (one action); no initiative entry required
- Suite: playwright/encounter
- Expected: simple hazard trigger → immediate resolution; no initiative tracker entry
- AC: Hazard-4

## TC-HAZ-05 — Complex hazards: join initiative when triggered
- Description: Complex hazards enter initiative when triggered; take own turns with defined actions
- Suite: playwright/encounter
- Expected: complex hazard trigger → added to initiative order; takes turns with its action block
- AC: Hazard-5

## TC-HAZ-06 — Haunt hazards: deactivated (not destroyed) after disable; re-activates
- Description: Haunt not destroyed until underlying supernatural condition resolved; disable = temporary (deactivated state only); re-activates on next trigger
- Suite: playwright/encounter
- Expected: haunt.disabled → state = deactivated (not destroyed); next trigger re-activates; destruction requires separate condition resolution
- AC: Hazard-7

## TC-HAZ-07 — Disabled vs. Destroyed state distinction; reset behavior
- Description: Disabled = inactive until reset; Destroyed = removed permanently; some hazards auto-reset after time interval; others require manual reset
- Suite: playwright/encounter
- Expected: disabled hazard shows "disabled" state; timed reset restores after interval; manual-reset hazard stays disabled until GM action; destroyed = removed from encounter
- AC: Hazard-8–9

## TC-HAZ-08 — Hazard XP award on disable or destroy
- Description: Hazards award XP when disabled or destroyed; same trigger framework as creature death
- Suite: playwright/encounter
- Expected: hazard.disabled or hazard.destroyed → XP awarded = hazard.xp_value; XP event fires once
- AC: Hazard-10

## TC-HAZ-09 — Hazard damage through existing pipeline (typed, resistances, immunities)
- Description: Hazard damage applies through standard damage pipeline; typed damage respected; resistances/immunities applied
- Suite: playwright/encounter
- Expected: fire trap damage checks fire resistance; hazard damage type stored in stat block; pipeline identical to creature attacks
- AC: Integration-4

## TC-HAZ-10 — APG hazards loaded alongside GMG hazards in catalog
- Description: APG-sourced hazards (Engulfing Snare, etc.) appear in hazard catalog alongside GMG hazards
- Suite: playwright/encounter
- Expected: hazard selector shows entries from both APG and GMG sources; filterble by source
- AC: Hazard-11

### Acceptance criteria (reference)

# Acceptance Criteria: GMG Hazards and NPC Gallery

## Feature: dc-gmg-hazards (covers GMG ch02 — Hazards and GM Tools)
## Feature: dc-gmg-npc-gallery (sub-feature; umbrella covered here)
## Source: PF2E Game Master's Guide, Chapter 2

---

## GM Tooling Framework (ch02 Baseline Requirements)

- [ ] GM tooling exposes configurable policies for adjudication, pacing, and scenario construction
- [ ] Subsystem framework supports pluggable mechanics with explicit setup, turn flow, and resolution states
- [ ] Variant rules are feature-flagged with compatibility checks against baseline campaign assumptions
- [ ] Encounter/adventure/map planning artifacts preserve traceability between scene intent and mechanics

---

## Hazard System Integration

- [ ] Hazards have complete stat blocks: Stealth/Disable/AC/Saves/HP/Hardness/Immunities/Weaknesses/Resistances/Actions
- [ ] Hazard Stealth DC used for initial detection; Perception check vs. Stealth DC determines awareness
- [ ] Hazard Disable skill DC used for disarming via applicable skill (Thievery, Arcana, etc.)
- [ ] Simple hazards: resolve in one action or trigger; no initiative required
- [ ] Complex hazards: join initiative when triggered; take their own turn(s)
- [ ] Hazards can be complex traps, environmental hazards, or haunts (all follow the same framework)
- [ ] Haunt hazards: not destroyed until underlying supernatural condition is resolved; deactivation is temporary
- [ ] Disabled vs. Destroyed: disabled = inactive until reset; destroyed = removed permanently
- [ ] Reset behavior: some hazards reset after a time interval; others require manual reset
- [ ] Hazard level and XP: awards Experience Points like creatures when overcome (disabled or destroyed)
- [ ] APG hazards (Engulfing Snare, etc.) loaded into the hazard catalog alongside GMG hazards

---

## NPC Gallery Integration

- [ ] NPC stat blocks follow the standard creature stat block format with NPC archetype tag
- [ ] NPC Gallery entries are pre-built stat blocks representing common archetypes (guard, merchant, assassin, etc.)
- [ ] NPCs can be used as allies, enemies, or neutral parties without modification
- [ ] GM can quickly assign an NPC Gallery entry to a scene via creature selector
- [ ] NPC Gallery entries classified by level range for encounter building

---

## Integration Notes (ch02)

- [ ] Integration points from GMG ch02 map to existing core rules without introducing duplicate semantics
- [ ] Conflicts between chapter-specific and core rules resolved through explicit precedence notes in implementation docs
- [ ] Hazard detection integrates with existing Perception and Seek rules
- [ ] Hazard damage applies through existing damage pipeline (typed damage, resistances, immunities respected)

---

## Integration Checks

- [ ] Complex hazard joins initiative tracker when triggered; has own turn with defined actions
- [ ] Haunt hazard UI: shows "deactivated" state (not destroyed) after disable action; re-activates on next trigger
- [ ] Hazard XP award fires when hazard is disabled or destroyed (same trigger as creature death)
- [ ] NPC Gallery entries searchable by level, archetype tag, and alignment

## Edge Cases

- [ ] Hazard triggered before being detected: initiative is rolled for the hazard; PC awareness state depends on Perception vs. Stealth result at trigger point
- [ ] Hazard reset: if a character stands in the reset area when reset triggers, hazard re-activates and may retrigger
- [ ] NPC used as ally: NPC stat block should remain editable for temporary boosts (companion NPC HP tracking)
- Agent: qa-dungeoncrawler
- Status: pending
