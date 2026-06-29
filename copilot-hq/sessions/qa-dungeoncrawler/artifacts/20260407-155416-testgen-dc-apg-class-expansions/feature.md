# Feature Brief: APG Core Class Expansions

- Work item id: dc-apg-class-expansions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Core Class Expansions)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Core Class Expansions
- Depends on: dc-cr-class-alchemist, dc-cr-class-barbarian, dc-cr-class-bard, dc-cr-class-champion, dc-cr-class-cleric, dc-cr-class-druid, dc-cr-class-fighter, dc-cr-class-monk, dc-cr-class-ranger, dc-cr-class-rogue, dc-cr-class-sorcerer, dc-cr-class-wizard

## Description
Implement APG Chapter 2 expanded options for the 12 core classes (REQs 191–221).
These are new class features, research fields, instincts, and feats added in the APG
that extend existing class services.

Scope by class:
- **Alchemist**: Toxicologist research field (REQs 191–196)
- **Barbarian**: Superstition instinct (REQs 197–200+)
- Other core classes: additional feats, archetypes, and features as catalogued in
  REQs 191–221

Depends on all 12 core class features being implemented first.

## Security acceptance criteria

- Security AC exemption: game-mechanic character data logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: apg, Chapter: ch02
- REQs: 191–221 (31 REQs, Core Class Expansions section)
- See `runbooks/roadmap-audit.md` for audit process.
