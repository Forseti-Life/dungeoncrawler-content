# Feature Brief: Skill System

- Work item id: dc-cr-skill-system
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 20260406-dungeoncrawler-release-b
- Priority: P1 (core activity resolution; exploration and social gameplay)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: game-mechanic
- Created: 2026-02-26

## Goal

Implement the 17 core skills (Acrobatics, Arcana, Athletics, Crafting, Deception, Diplomacy, Intimidation, Lore, Medicine, Nature, Occultism, Performance, Religion, Society, Stealth, Survival, Thievery) with proficiency ranks (Untrained/Trained/Expert/Master/Legendary). Skills determine success at non-combat actions and gate access to skill feats. This system underpins exploration and social gameplay.

## Source reference

> "Execute acrobatic maneuvers, trick your enemies, tend to an ally's wounds, or learn all about strange creatures and magic using your training in skills." (Chapter 4: Skills)

## Implementation hint

Character entity fields: `skills{}` map of skill → proficiency rank (0–4). Skill check = d20 + ability modifier + proficiency bonus + item bonus + conditional modifiers vs. DC. API endpoint for rolling skill checks given a DC. Content type for Lore skills (variable specializations). Skill actions should be referenced from the action economy system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria
- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: entity field types enforced at Drupal entity layer; no raw free-text user input stored without sanitization
- PII/logging constraints: no PII logged; gameplay action logs (character id, action type) only
