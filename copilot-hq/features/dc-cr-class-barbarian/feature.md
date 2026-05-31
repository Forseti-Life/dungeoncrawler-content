# Feature Brief: Barbarian Class Mechanics

- Work item id: dc-cr-class-barbarian
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260409-dungeoncrawler-release-f
20260409-dungeoncrawler-release-f
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Barbarian
- Depends on: dc-cr-character-class, dc-cr-character-creation, dc-cr-conditions

## Goal

Implement the Barbarian class: a primal martial class with the highest base HP (12+CON/level), the Rage action (temp HP, +2 melee damage, –1 AC, concentrate restriction with 1-min cooldown), and an Instinct subclass system (Animal, Giant, Spirit, Dragon, etc.) that shapes anathema tracking, bonus damage type, and feat access. Covers 120 requirements in `core/ch03/Barbarian`.

## Source reference

> "Fury wells within you—whether channeled into precise technique or raw aggression, you can bring it to bear against your foes with a ferocity that makes enemies cower." (Chapter 3: Classes — Barbarian)

## Implementation hint

Character class entity fields: `class_key = 'barbarian'`, `key_ability = 'str'`, `hp_per_level = 12`, `instinct` (enum: animal/giant/spirit/dragon/fury/superstition). Rage action: 1-action, concentrate+emotion+mental traits; grants temp HP = level + CON modifier, +2 melee damage (÷2 for agile/unarmed), –1 AC, blocks concentrate-trait actions except those with rage trait (Seek always allowed); duration 1 min or no perceived enemies or unconscious; cannot voluntarily end; 1-min cooldown after. Anathema: store per-character behavioral restriction from instinct; warn on violation (no auto-enforcement needed at MVP). Class features by level: Deny Advantage (3), Instinct ability (3), Brutality (5), Juggernaut (7), Weapon Specialization (7), Instinct Ability (7), Specialization ability (9), Raging Resistance (9), Mighty Rage (11), Greater Juggernaut (13), Medium Armor Mastery (13), Greater Weapon Specialization (15), Indomitable Will (15), Devastator (17), Heightened Senses (17), Instinct Ability (17), Second Instinct Ability (17), Apex ability (19). Full class feat chain through 20th level.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: instinct enum validated server-side; rage state (active/cooldown) stored as character session field, not free-text
- PII/logging constraints: no PII logged; gameplay action logs (character id, action type) only
