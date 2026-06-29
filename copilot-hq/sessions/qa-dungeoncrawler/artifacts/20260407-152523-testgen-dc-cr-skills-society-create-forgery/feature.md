# Feature Brief: Society — Create Forgery

- Work item id: dc-cr-skills-society-create-forgery
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Society (Int)
- Depends on: dc-cr-skill-system

## Description
Implement Society (Int) action handlers (REQs 1731–1738).

- **Create Forgery** (downtime, trained, forger's kit): Society vs Perception of examiner;
  passive scrutiny on receipt; active scrutiny when examined
- Degrees: crit success=passes all scrutiny; success=passes casual; fail=obvious fake;
  crit fail=reveals forger
- Also covers Recall Knowledge (Society): Humanoids, local history, legal knowledge —
  coordination with dc-cr-creature-identification

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1731, 1732, 1733, 1734, 1735, 1736, 1737, 1738
- See `runbooks/roadmap-audit.md` for audit process.
