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
