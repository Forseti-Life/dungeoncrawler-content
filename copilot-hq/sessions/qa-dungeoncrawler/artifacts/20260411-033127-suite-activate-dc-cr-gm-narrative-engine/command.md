- Status: done
- Completed: 2026-04-11T03:54:44Z

# Suite Activation: dc-cr-gm-narrative-engine

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-11T03:31:27+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-gm-narrative-engine"`**  
   This links the test to the living requirements doc at `features/dc-cr-gm-narrative-engine/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-gm-narrative-engine-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-gm-narrative-engine",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-gm-narrative-engine"`**  
   Example:
   ```json
   {
     "id": "dc-cr-gm-narrative-engine-<route-slug>",
     "feature_id": "dc-cr-gm-narrative-engine",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-gm-narrative-engine",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-gm-narrative-engine

## Coverage summary
- AC items: ~18 (AI GM context assembly, scene narration, NPC dialogue, encounter triggering, session summary, GM tools integration, security)
- Test cases: 12 (TC-GNE-01–12)
- Suites: playwright (encounter, downtime)
- Security: No PII in AI context; rate-limited AI API calls

---

## TC-GNE-01 — AI GM context: includes session summary, NPC data, quests, location, recent events
- Description: Context assembly includes current session summary, active NPC data, quest hooks, location, recent events log
- Suite: playwright/encounter
- Expected: context payload contains all 5 required fields; no missing required context elements
- AC: AC-001

## TC-GNE-02 — Campaign sessions: prior session summaries appended (truncated, recent first)
- Description: Campaign mode context appends prior session summaries; truncated to fit context window; recent sessions prioritized
- Suite: playwright/encounter
- Expected: multi-session campaign → prior summaries appended; recent first; older summaries dropped when context window full
- AC: AC-001

## TC-GNE-03 — Scene narration: atmospheric description, NPC present, player action prompt
- Description: AI GM scene narration includes: atmospheric description, active NPC (if any), prompt for player action
- Suite: playwright/encounter
- Expected: narration response has all 3 components; missing NPC gracefully omits NPC section
- AC: AC-002

## TC-GNE-04 — Player action: narration reflects outcome and advances scene state
- Description: After player action, AI GM narration reflects result and updates scene state
- Suite: playwright/encounter
- Expected: action outcome → narration references the action; scene_state updated after resolution
- AC: AC-002

## TC-GNE-05 — NPC dialogue: reflects role, attitude, and lore notes
- Description: AI GM dialogue output reflects NPC's role, attitude, and lore notes from NPC record
- Suite: playwright/encounter
- Expected: NPC context (role, attitude, lore) present in AI prompt; response consistent with NPC data
- AC: AC-003

## TC-GNE-06 — Successful Diplomacy against hostile NPC: attitude improves, field updated
- Description: Diplomacy success vs. hostile NPC → AI GM describes NPC softening; NPC.attitude field updated in DB
- Suite: playwright/encounter
- Expected: Diplomacy success → npc.attitude improved by 1 step; narrative reflects change; DB persisted
- AC: AC-003

## TC-GNE-07 — Encounter trigger from narrative: transitions to encounter mode
- Description: AI GM determining an encounter (ambush, trap, random) fires encounter_trigger → initiative rolled, combat begins
- Suite: playwright/encounter
- Expected: encounter_trigger event → mode transition to combat; initiative automatically rolled; no manual GM step required
- AC: AC-004

## TC-GNE-08 — Hazard invocation from narrative
- Description: When narrative calls for a hazard/trap, the hazard system is invoked at the trigger point
- Suite: playwright/encounter
- Expected: GM narrative flag "hazard_trigger" → hazard system activates; hazard stat block loaded; detection check fires
- AC: AC-004

## TC-GNE-09 — Session summary generated at session end; saved to campaign history
- Description: End-of-session routine generates narrative summary (key events, XP, NPCs met); saved to session history
- Suite: playwright/downtime
- Expected: session_end event → summary generated; saved to campaign.session_history; accessible in chronological order
- AC: AC-005

## TC-GNE-10 — AI GM uses encounter budget from dc-gmg-running-guide for encounter generation
- Description: When generating an encounter, AI GM pulls from encounter budget calculator (appropriate threat level for party level)
- Suite: playwright/encounter
- Expected: encounter_generation request includes party_level and budget_data from running-guide integration; threat level validated
- AC: AC-006, Depends-on: dc-gmg-running-guide

## TC-GNE-11 — AI GM queries NPC catalog by role/level for random NPCs
- Description: When AI GM needs a random NPC, it queries NPC catalog by role/level
- Suite: playwright/encounter
- Expected: NPC catalog query returns NPC matching role and level range; NPC populated into scene
- AC: AC-006, Depends-on: dc-cr-npc-system

## TC-GNE-12 — No PII in AI context payloads; rate limiting enforced
- Description: AI API calls contain no PII (no player real names or real-world data); rate limiting prevents API abuse
- Suite: playwright/encounter
- Expected: AI payload scrubbed of PII; rate limit returns 429 when exceeded; no PII in context even after error
- AC: Security AC

### Acceptance criteria (reference)

# Acceptance Criteria: GM Narrative Engine
# Feature: dc-cr-gm-narrative-engine

## AC-001: AI GM Context Assembly
- Given a scene is requested by the player, when the AI GM assembles its context, then the context includes: current session summary, active NPC data (name, role, attitude), active quest hooks, current location description, and recent events from the session log
- Given the session is part of a campaign, when the AI GM context is built, then prior-session summaries are appended (truncated to fit context window with recent sessions prioritized)

## AC-002: Scene Narration
- Given the AI GM narrates a new scene, when triggered, then the output includes: atmospheric description, active NPC present in the scene (if any), and a prompt to the player for their action
- Given the player performs an action, when the AI GM resolves it, then the narration reflects the action's outcome and advances the scene state

## AC-003: NPC Dialogue Generation
- Given the AI GM portrays an NPC in dialogue, when NPC data is in context, then the AI GM's output reflects the NPC's role, attitude, and lore notes
- Given an NPC is hostile, when the player tries Diplomacy, when the check result meets the DC, then the AI GM describes the NPC softening and adjusts the stored attitude field

## AC-004: Encounter Triggering from Narrative
- Given the AI GM determines an encounter should begin (ambush, triggered trap, random encounter), when the encounter trigger fires, then the system transitions to encounter mode (initiative rolled, combat begins)
- Given the narrative calls for a hazard or trap, when the GM narrates the trigger point, then the hazard system is invoked

## AC-005: Session Summary Generation
- Given a session ends, when the end-of-session routine runs, then the AI GM generates a brief narrative summary (key events, XP earned, NPCs met) and saves it to the campaign's session history
- Given a campaign has multiple sessions, when the summary list is retrieved, then summaries are accessible in chronological order

## AC-006: GM Tools Integration
- Given encounter budgeting data exists (dc-gmg-running-guide), when the AI GM needs to generate an encounter, then it pulls from the encounter budget calculator to ensure threat level is appropriate to party level
- Given NPC gallery data exists, when the AI GM needs a random NPC, then it can query the NPC catalog by role/level to populate the scene

## Security acceptance criteria

- Security AC exemption: Narrative engine makes AI API calls. Ensure no PII (player names, real-world data) is included in AI context payloads. AI response content is not user-generated and does not require moderation for PII, but should be rate-limited to prevent API abuse.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 10: Game Mastering (AI GM tools)
- Depends on: dc-cr-npc-system, dc-cr-session-structure, dc-gmg-running-guide
