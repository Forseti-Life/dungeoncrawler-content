# Feature Brief: Fighter Class Mechanics

- Work item id: dc-cr-class-fighter
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-06
- DB sections: core/ch03/Fighter
- Depends on: dc-cr-character-class, dc-cr-character-creation, dc-cr-encounter-rules

## Goal

Implement the Fighter class: the premier martial class with Expert proficiency in simple, martial, and advanced weapons from level 1, Attack of Opportunity as a class feature (not a feat), and a deep class feat chain covering stances, presses, reactions, and positioning maneuvers. Covers 84 requirements in `core/ch03/Fighter`.

## Source reference

> "Fighters have unparalleled mastery with weapons and armor. Though a fighter might aspire to a noble's title or a simple life on the farm, their true calling is battle." (Chapter 3: Classes — Fighter)

## Implementation hint

Character class entity fields: `class_key = 'fighter'`, `key_ability` (str or dex), `hp_per_level = 10`. Class features by level: Attack of Opportunity (1), Bravery (3), Fighter Weapon Mastery (5), Weapon Specialization (7), Combat Flexibility (9), Juggernaut (9), Shield Warden (optional via feat), Weapon Legend (13), Evasion (15), Greater Weapon Specialization (15), Improved Flexibility (15), Versatile Legend (17). Fighter class feats: Double Slice, Exacting Strike, Power Attack, Sudden Charge, Point-Blank Shot, Reactive Shield, Snagging Strike (1st); Aggressive Block, Assisting Shot, Brutish Shove, Combat Grab, Dueling Parry, Intimidating Strike, Lunge, Double Shot (2nd); and full feat chain through 20th level. Proficiency tracks: simple+martial+advanced weapons at Expert from 1st (unique); armor Expert from 1st. Key terms: Press trait (only under MAP, failure omits crit-fail effects), Stance trait (one active, 1-round cooldown).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: entity field types enforced at Drupal entity layer; class key and feat selections validated against allowed enums
- PII/logging constraints: no PII logged; gameplay action logs (character id, action type, feat id) only
