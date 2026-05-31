# Feature Brief: Bestiary 1 (deferred)

- Work item id: dc-b1-bestiary1
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260409-dungeoncrawler-release-f
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Bestiary 1
- Category: creature-library
- Created: 2026-04-07
- DB sections: b1/s01/Baseline Requirements, b1/s01/Integration Notes, b1/s02/Baseline Requirements, b1/s02/Integration Notes, b1/s03/Baseline Requirements, b1/s03/Integration Notes
- Depends on: dc-cr-encounter-rules, dc-cr-npc-system

## Goal

Implement the Bestiary 1 creature library: creature stat blocks covering level, rarity, traits, perception, languages, skills, senses, AC, saves, HP, immunities, weaknesses, resistances, speeds, attacks, and special abilities/actions. Provides the encounter tooling filtering layer (by level/trait/role) needed for balanced combat composition. Covers 18 requirements across `b1/s01–s03`.

## Source reference

> "The monsters in this book represent fearsome creatures that might threaten the heroes in your campaign." (Bestiary 1 — Introduction)

## Implementation hint

Content type: `creature` with full stat block fields: `level`, `rarity` (enum), `traits[]`, `perception`, `languages[]`, `skills{}`, `senses[]`, `ac`, `saves{fort,ref,will}`, `hp`, `immunities[]`, `weaknesses[]`, `resistances[]`, `speeds{}`, `attacks[]`, `abilities[]`. Each attack encodes: name, traits[], damage dice, damage type, reach. Each ability encodes: action cost, trigger, frequency, traits[], save/DC, effect. Encounter tooling: filter creatures by level range, trait, and tactical role (brute/skirmisher/controller/support/spellcaster). DB sections contain baseline data-model and integration-note placeholders; real creature data loaded via drush import once stat block schema is implemented. Depends on encounter-rules (XP budget, encounter difficulty) and npc-system (attitude/social mechanics scaffold).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: creature library is read-only for players; GM-only mutation routes require `_campaign_gm_access: TRUE`
- CSRF expectations: any POST/PATCH creature import or override routes require `_csrf_request_header_mode: TRUE`
- Input validation: stat block fields validated against defined types and ranges; no free-text rule injection; import pipeline sanitizes all text fields
- PII/logging constraints: no PII logged; creature id + encounter id + action type only
