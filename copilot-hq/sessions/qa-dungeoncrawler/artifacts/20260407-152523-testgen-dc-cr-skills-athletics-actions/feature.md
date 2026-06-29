# Feature Brief: Athletics Skill Actions

- Work item id: dc-cr-skills-athletics-actions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Athletics (Str)
- Depends on: dc-cr-skill-system, dc-cr-skills-calculator-hardening, dc-cr-conditions

## Description
Implement all Athletics (Str) skill action handlers (REQs 1620–1642). High priority —
Grapple, Trip, Shove, Disarm are core combat actions; Climb/Swim are core exploration.
REQ 1641 (falling damage handler) is HIGH severity and may be partially broken
(grab_edge wired but applyFallingDamage method absent).

Combat actions (EPH):
- **Climb** (1 action): Athletics vs terrain Climb DC; speed = half if failed
- **Force Open** (1 action): Athletics vs Hardness/Fortitude; degrees
- **Grapple** (1 action, reach): Athletics vs Fortitude; grabbed/restrained conditions
- **High/Long Jump** (2 actions): Stride + Athletics vs DC (DC 30 for 10-ft high jump)
- **Shove** (1 action, reach): Athletics vs Fortitude; pushed 5 ft (10 on crit)
- **Trip** (1 action, reach): Athletics vs Reflex; prone on success
- **Disarm** (1 action, reach, trained): Athletics vs Reflex; drop item

Exploration actions (ExPH): Swim (Speed or half), Climb (wall)

Falling damage fix (REQ 1641): wire applyFallingDamage to grab_edge reaction;
add fallDamage method to HPManager if absent.

**Escape action** (REQ 1601): Escape is a basic action that uses Athletics (or
Acrobatics) instead of its default unarmed modifier when the character chooses.

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1601, 1620–1642 (24 REQs)
- See `runbooks/roadmap-audit.md` for audit process.
