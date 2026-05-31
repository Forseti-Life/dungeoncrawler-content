# Feature Brief: NPC System

- Work item id: dc-cr-npc-system
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260411-dungeoncrawler-release-b
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 301–600
- Category: game-mechanic
- Created: 2026-02-27
- DB sections: core/ch10/NPC Social Mechanics

## Goal

Implement NPCs (non-player characters) as a distinct entity type from monsters and player characters. NPCs include named allies, contacts, quest-givers, merchants, and villains. While monsters have stat blocks optimized for combat, NPCs need names, roles, dialogue hooks, relationship to the party, and abbreviated stat blocks for cases where they enter combat. The AI GM portrays all NPCs, so this content type must be AI-prompt-friendly.

## Source reference

> "The Game Master portrays nonplayer characters (NPCs) and monsters. While PCs and NPCs are both important to the story, they serve very different purposes in the game. PCs are the protagonists—the narrative is about them—while NPCs and monsters are allies, contacts, adversaries, and villains."

## Implementation hint

Content type: `npc` with fields for name, role (ally/contact/merchant/villain/neutral), attitude (friendly/indifferent/unfriendly/hostile), abbreviated stat block (level, perception, AC, HP, saves — for combat if needed), and dialogue/lore notes. Distinct from `creature` (monster) content type. AI GM prompt context includes relevant NPC data for each scene. NPC attitude can change based on player actions (Diplomacy/Deception checks).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: NPC data is campaign-scoped by GM; cross-campaign access blocked; `_campaign_gm_access: TRUE` on GM-only routes
- CSRF expectations: all POST/PATCH NPC routes require `_csrf_request_header_mode: TRUE`
- Input validation: NPC names/descriptions sanitized at Drupal field layer; attitude enum validated server-side
- PII/logging constraints: no PII logged; campaign id + NPC id + action type only

## Roadmap section

- Roadmap: Core Rulebook
