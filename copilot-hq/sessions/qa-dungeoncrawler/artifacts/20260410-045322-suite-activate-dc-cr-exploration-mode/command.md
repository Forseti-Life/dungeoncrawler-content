# Suite Activation: dc-cr-exploration-mode

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-10T04:53:22+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-exploration-mode"`**  
   This links the test to the living requirements doc at `features/dc-cr-exploration-mode/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-exploration-mode-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-exploration-mode",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-exploration-mode"`**  
   Example:
   ```json
   {
     "id": "dc-cr-exploration-mode-<route-slug>",
     "feature_id": "dc-cr-exploration-mode",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-exploration-mode",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-exploration-mode

## Coverage summary
- AC items: ~14 (time scale, activities, initiative from activity, light/darkness, encounter transition, stealth surprise)
- Test cases: 10 (TC-EXP-01–10)
- Suites: playwright (encounter, character creation)
- Security: AC exemption granted

---

## TC-EXP-01 — Exploration time scale: minutes and hours
- Description: Exploration mode tracks time in minutes/hours (not rounds)
- Suite: playwright/encounter
- Expected: time_unit = minutes when exploration_mode = active; round tracker absent
- AC: AC-001

## TC-EXP-02 — Exploration activities: correct set available
- Description: Available activities: Avoid Notice, Detect Magic, Hustle, Investigate, Repeat a Spell, Scout, Search, Sense Direction
- Suite: playwright/encounter
- Expected: all 8 activities in exploration activity picker; no encounter-only actions listed
- AC: AC-002

## TC-EXP-03 — Search: detects secrets/hazards/items per 10-ft square moved
- Description: While Searching, each 10-ft square moved checks for secret doors, hazards, hidden items
- Suite: playwright/encounter
- Expected: movement while Searching → Perception check per 10-ft segment; detection fires on success
- AC: AC-002

## TC-EXP-04 — Hustle: double speed, fatigue after 10 minutes
- Description: Hustle doubles movement speed; accrues fatigue after 10 minutes of Hustling
- Suite: playwright/encounter
- Expected: speed_bonus = ×2; after 10 min → fatigue condition applied
- AC: AC-002

## TC-EXP-05 — Initiative from exploration activity: skill per current activity
- Description: When encounter begins, initiative skill = associated with current exploration activity (e.g., Stealth for Avoid Notice, Perception for Scout)
- Suite: playwright/encounter
- Expected: initiative_skill determined by active exploration activity; correct skill mapping per activity
- AC: AC-003

## TC-EXP-06 — Light/darkness: vision type determines sight
- Description: Normal/low-light/darkvision determines what character sees per area lighting; bright radius and dim radius tracked for carried light sources
- Suite: playwright/encounter
- Expected: light_source has bright_radius and dim_radius; area beyond bright = dim; beyond dim = dark; vision type applied
- AC: AC-004

## TC-EXP-07 — Encounter transition: exploration → combat round scale
- Description: Encounter trigger automatically transitions from exploration time scale to combat rounds
- Suite: playwright/encounter
- Expected: encounter_trigger event → time_unit changes to rounds; initiative rolls fire; exploration activities deactivated
- AC: AC-005

## TC-EXP-08 — Stealth approach: enemies that failed Perception are surprised
- Description: Characters using Avoid Notice: when encounter begins, enemies failing Perception vs. Stealth are surprised (cannot act first round)
- Suite: playwright/encounter
- Expected: each enemy makes Perception vs. stealth_roll; failure → enemy.state = surprised; surprised enemies skip first turn
- AC: AC-005

## TC-EXP-09 — Exploration-to-encounter bonus: accumulated activity affects first round
- Description: Prior exploration activity affects first encounter round (initiative bonuses, detection state)
- Suite: playwright/encounter
- Expected: initiative roll uses exploration activity skill; detection states from pre-combat carry into first round
- AC: AC-001

## TC-EXP-10 — Grid position server-validated: no client-side spoofing
- Description: Grid/map positions in exploration mode are server-validated
- Suite: playwright/encounter
- Expected: position updates rejected if not consistent with server state; client cannot directly set coordinates
- AC: Security AC

### Acceptance criteria (reference)

# Acceptance Criteria: Exploration Mode
# Feature: dc-cr-exploration-mode

## AC-001: Time Scale
- Given exploration mode is active, when time is tracked, then time advances in minutes and hours (not rounds)
- Given exploration mode transitions to encounter mode (combat begins), when the transition occurs, then accumulated exploration activity affects the first round (e.g., initiative bonuses, detection)

## AC-002: Exploration Activities
- Given a character is in exploration mode, when they select an exploration activity, then available activities include: Avoid Notice, Detect Magic, Hustle, Investigate, Repeat a Spell, Scout, Search, and Sense Direction
- Given a character performs Search while exploring, when each 10-foot square is moved through, when search checks are resolved, then secret doors, hazards, and hidden items in the path are detected on success
- Given a character uses Hustle, when the activity is active, then movement speed is doubled but the character accrues fatigue after 10 minutes

## AC-003: Initiative From Exploration Activity
- Given an encounter begins while the party is in exploration mode, when initiative is rolled, then each character uses the skill associated with their current exploration activity (e.g., Stealth for Avoid Notice, Perception for Scout/Search)

## AC-004: Light and Darkness
- Given the dungeon has areas of varying light, when a character moves into an area, then their vision type (normal, low-light, darkvision) determines what they can see
- Given a light source has a radius, when the character carries the source, then squares beyond the bright radius are dim light, and beyond the dim radius are darkness

## AC-005: Encounter Transition
- Given the party triggers an encounter in exploration mode, when combat begins, then the system transitions from exploration time scale to combat rounds automatically
- Given stealth-based approach (Avoid Notice activity), when the encounter begins, then enemies that failed their Perception checks are surprised (cannot act in the surprise round)

## Security acceptance criteria

- Security AC exemption: Exploration state is session-scoped data with no PII. Grid/map positions are server-validated to prevent location spoofing.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 9: Playing the Game (Exploration)
- Agent: qa-dungeoncrawler
- Status: pending
