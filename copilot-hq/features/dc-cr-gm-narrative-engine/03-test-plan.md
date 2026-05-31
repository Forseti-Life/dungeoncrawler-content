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
