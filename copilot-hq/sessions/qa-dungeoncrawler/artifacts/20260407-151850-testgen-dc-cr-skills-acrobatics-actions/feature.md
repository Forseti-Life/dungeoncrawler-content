# Feature Brief: Acrobatics Skill Actions

- Work item id: dc-cr-skills-acrobatics-actions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Acrobatics (Dex)
- Depends on: dc-cr-skill-system, dc-cr-skills-calculator-hardening

## Description
Implement all Acrobatics (Dex) skill action handlers in EncounterPhaseHandler and
ExplorationPhaseHandler. Trained-only gating and armor check penalty via
dc-cr-skills-calculator-hardening.

Actions (REQs 1603–1614):
- **Balance** (1 action, trained): Acrobatics vs terrain DC; degrees (crit=no penalty,
  success=pass, fail=fall prone + stop, crit fail=fall prone + damage)
- **Tumble Through** (1 action): move through enemy space; Acrobatics vs enemy Reflex DC;
  fail = movement stops; triggers AoO
- **Maneuver in Flight** (1 action, trained, requires flight): maneuver; DC 15+level;
  fail = Reflex or fall
- **Squeeze** (exploration, trained): move through tiny space at half Speed; DC varies;
  crit success = no penalty

Also: Escape can use Acrobatics (already partially wired; full wiring and enforcement).

## Security acceptance criteria

- Security AC exemption: skill action logic; no new routes or user-facing input beyond existing encounter/exploration phase handlers

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1603, 1604, 1605, 1606, 1607, 1608, 1609, 1610, 1611, 1612, 1613, 1614
- See `runbooks/roadmap-audit.md` for audit process.
