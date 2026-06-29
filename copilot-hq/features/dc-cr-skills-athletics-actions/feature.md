# Feature Brief: Athletics Skill Actions

- Work item id: dc-cr-skills-athletics-actions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260408-dungeoncrawler-release-f
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Athletics (Str)
- Depends on: dc-cr-skill-system, dc-cr-skills-calculator-hardening, dc-cr-conditions

## Goal

Implement all Athletics (Str) skill action handlers: Climb, Force Open, Grapple, High Jump, Long Jump, Shove, Swim, and Trip — covering all degree-of-success outcomes, size restrictions, forced movement rules, and the attack-trait interactions that integrate with MAP. Also includes the Escape action (REQ 1601). Covers `core/ch04/Athletics (Str)`.

## Source reference

> "You call upon your physical prowess to overcome physical challenges, whether you're Climbing a cliff face, Grappling a foe, or propelling yourself over a chasm." (Chapter 4: Skills — Athletics)

## Implementation hint

**Climb** (1-action move): flat-footed without climb Speed; ~5 ft success / ~10 ft crit; crit fail = fall + prone. **Force Open** (1-action attack): –2 without crowbar; success=broken; crit success=opens undamaged; crit fail=jammed + –2 future. **Grapple** (1-action attack): requires 1 free hand, size ≤1 larger; crit=restrained / success=grabbed / fail=release / crit fail=target may grab you or knock prone; grabbed/restrained last until end of next turn, broken by move or Escape. **High Jump** (2-action): Stride ≥10ft required or auto-fail; DC 30; crit=8ft / success=5ft / fail=normal Leap / crit fail=prone. **Long Jump** (2-action): DC = distance in feet; max = Speed; must Stride ≥10ft same direction; crit fail = normal leap + prone. **Shove** (1-action attack): forced movement ignores reactions; crit=10ft / success=5ft / crit fail=attacker prone; may follow with Stride. **Swim** (1-action move): no check in calm water; sink 10ft if no swim action at turn end; crit fail costs 1 held-breath round. **Trip** (1-action attack): crit=1d6 bludgeoning + prone / success=prone / crit fail=attacker prone. **Escape** (1-action): uses Athletics (or Acrobatics) vs DC of effect that grabbed/restrained/immobilized. Falling damage: wire applyFallingDamage to grab_edge reaction; add fallDamage method to HPManager if absent (REQ 1641).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; character ownership enforced via `_character_access: TRUE` on all character-scoped routes
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: action results (grabbed/prone/restrained) are server-computed from dice roll + modifiers; not client-asserted
- PII/logging constraints: no PII logged; gameplay action logs (character id, action key, target id, degree of success) only
