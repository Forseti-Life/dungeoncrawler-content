# Feature Brief: AI GM Narrative Engine

- Work item id: dc-cr-gm-narrative-engine
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-gm-tools, dc-cr-npc-system, dc-cr-session-structure
- Source: PF2E Core Rulebook (Fourth Printing), lines 301–600
- Category: rule-system
- Created: 2026-02-27

## Goal

Implement the AI-powered Game Master narrative engine that describes scenes, responds to player actions with story outcomes, portrays NPCs, and maintains narrative continuity across an adventure session. This is the storytelling layer that makes dungeoncrawler feel like a real GM-run game, distinct from the mechanical encounter-budget tooling.

Implement the AI GM's narrative engine: the system that describes scenes, responds to player character actions with story outcomes, portrays NPCs, and maintains narrative continuity across an adventure. This is distinct from `dc-cr-gm-tools` (which handles mechanical encounter budgets and loot tables). The narrative engine handles storytelling — scene framing, outcome description, NPC dialogue, and tone adaptation based on the group's preferences. It is the core of what makes dungeoncrawler feel like a real GM-run game.

## Source reference

> "The Game Master describes all the situations player characters experience in an adventure, considers how the actions of player characters affect the story, and interprets the rules along the way. The GM can create a new adventure—crafting a narrative, selecting monsters, and assigning treasure on their own—or they can instead rely on a published adventure, using it as a basis for the session and modifying it as needed to accommodate their individual players and the group's style of play."
> "Ultimately it's up to you and your group to determine what kind of game you are playing, from dungeon exploration to a nuanced political drama, or anything in between."

## Implementation hint

AI prompt pipeline: system prompt includes active adventure context (current scene, NPC roster, campaign history summary), player action as input, and tone/style constraints (set at campaign creation: gritty/heroic/comedic). Output: narrative description of outcome + any mechanical triggers (roll required, condition applied, XP awarded). Integrates with session state (dc-cr-session-structure), NPC system (dc-cr-npc-system), and encounter/exploration modes. Adventure context injected from published adventure content type or GM-authored scene nodes.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: AI narrative calls scoped to authenticated session owner; session context tokens validated server-side
- CSRF expectations: all POST routes for narrative generation require `_csrf_request_header_mode: TRUE`
- Input validation: player action inputs sanitized before injection into AI prompt; max token limits enforced; adventure context payloads must not include PII
- PII/logging constraints: AI context payloads must not include PII; AI calls are rate-limited per session to prevent API abuse; prompt/response logs retain session id + adventure id only

## Roadmap section

- Roadmap: Core Rulebook
