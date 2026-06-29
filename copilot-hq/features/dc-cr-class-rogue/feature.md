# Feature Brief: Rogue Class Mechanics

- Work item id: dc-cr-class-rogue
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: shipped
- Release:
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Rogue
- Depends on: dc-cr-character-class, dc-cr-character-creation, dc-cr-skill-system

## Goal

Implement the Rogue class: the premier skill class with the most skill increases and skill feats per level, Sneak Attack precision damage against flat-footed targets, and a Racket subclass system (Ruffian, Scoundrel, Thief) that defines key ability, bonus skills, and sneak attack eligibility. Covers 66 requirements in `core/ch03/Rogue`.

## Source reference

> "Life is full of opportunities, and you take advantage of each and every one. You might pick a noble's pocket, talk your way into a king's good graces, or deliver a devastating blow to an unsuspecting guard." (Chapter 3: Classes — Rogue)

## Implementation hint

Character class entity fields: `class_key = 'rogue'`, `key_ability` (dex, or racket-specific), `hp_per_level = 8`, `racket` (enum: ruffian/scoundrel/thief). Proficiency tracks: Expert Reflex + Expert Will at 1st (unique); Stealth + 7+INT skills; skill feat every level (not every 2); skill increase every level from 2nd. Sneak attack: precision damage (1d6 at 1st, scaling) only vs flat-footed targets; ineffective against creatures without vital organs. Racket mechanics: Ruffian — sneak attack with any simple weapon + crit specialization on flat-footed crits; Scoundrel — Feint grants flat-footed to all melee attacks until next turn on crit; Thief — DEX-to-damage with finesse. Surprise Attack: use Deception or Stealth for initiative. Debilitation system: mutually exclusive, new replaces old. Class features: Racket (1), Surprise Attack (1), Sneak Attack (1), Deny Advantage (3), Debilitating Strike (9), Master Strike (19). Full class feat chain through 20th level.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: racket enum validated server-side; sneak attack eligibility computed server-side, not client-asserted
- PII/logging constraints: no PII logged; gameplay action logs (character id, action type, target id) only
