# Feature Brief: Goblin Ancestry Feat — Goblin Weapon Frenzy

- Work item id: dc-cr-goblin-weapon-frenzy
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 7384–7683
- Category: game-mechanic
- Release: 20260412-dungeoncrawler-release-m
- Created: 2026-04-11

## Goal

Implements the Goblin ancestry Feat 5 "Goblin Weapon Frenzy": whenever a goblin character scores a critical hit using a goblin weapon, they apply the weapon's critical specialization effect. This makes goblin weapons distinctively rewarding at higher levels and encourages goblin characters to use their ancestral weapon types. Requires Goblin Weapon Familiarity as prerequisite (dc-cr-goblin-weapon-familiarity — stub pending from earlier source lines).

## Source reference

> GOBLIN WEAPON FRENZY — FEAT 5 — GOBLIN
> Prerequisites: Goblin Weapon Familiarity
> You know how to wield your people's vicious weapons. Whenever you score a critical hit using a goblin weapon, you apply the weapon's critical specialization effect.

## Implementation hint

- Critical hit handler: when a character with this feat crits with a goblin-tagged weapon, trigger the weapon's critical specialization effect node.
- Prerequisite gate: character must have dc-cr-goblin-weapon-familiarity feat (not yet stubbed; likely in source lines before 7384 — flag as dependency gap).
- Connects to dc-cr-critical-specialization system (if stubbed) or will require a new critical-specialization lookup per weapon type.
- Parallels dc-cr-gnome-weapon-specialist (gnome version of same mechanic); dev can reuse that implementation pattern.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: combat resolution remains server-authoritative; feat effects apply only for the acting character on owned/valid turns.
- CSRF expectations: all POST/PATCH encounter actions require `_csrf_request_header_mode: TRUE`.
- Input validation: crit-specialization triggers must validate both goblin weapon tags and the Goblin Weapon Familiarity prerequisite server-side.
- PII/logging constraints: no PII logged; log character_id, weapon_id, critical_hit, specialization_applied only.
