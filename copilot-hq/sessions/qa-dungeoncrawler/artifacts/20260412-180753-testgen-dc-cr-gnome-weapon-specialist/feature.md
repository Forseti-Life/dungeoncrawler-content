# Feature Brief: Gnome Weapon Specialist (Gnome Ancestry Feat 5)

- Work item id: dc-cr-gnome-weapon-specialist
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6784–7083
- Category: game-mechanic
- Created: 2026-04-09

## Goal

Gnome Feat 5 (requires Gnome Weapon Familiarity) granting critical specialization effects whenever the character critically hits with a glaive, kukri, or any gnome weapon. Rewards investment in the gnome weapon tree by unlocking weapon-specific critical effects that deal extra conditions or damage on a 20+ roll.

## Source reference

> Prerequisites Gnome Weapon Familiarity. You produce outstanding results when wielding unusual weapons. Whenever you critically hit using a glaive, kukri, or gnome weapon, you apply the weapon's critical specialization effect.

## Implementation hint

Ancestry feat (level 5) that registers glaive, kukri, and all gnome weapons as eligible for critical specialization in the combat resolution layer. Depends on dc-cr-gnome-weapon-familiarity and the combat system's crit-specialization lookup table keyed by weapon type. Critical specialization effects vary by weapon damage type (slashing, piercing, etc.).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: combat resolution remains server-authoritative; prerequisite and weapon-tag validation happen server-side.
- CSRF expectations: all POST/PATCH encounter actions require `_csrf_request_header_mode: TRUE`.
- Input validation: crit-specialization trigger applies only on critical hits with glaive, kukri, or gnome-tagged weapons while the character has Gnome Weapon Familiarity.
- PII/logging constraints: no PII logged; log character_id, weapon_id, critical_hit, specialization_applied only.
