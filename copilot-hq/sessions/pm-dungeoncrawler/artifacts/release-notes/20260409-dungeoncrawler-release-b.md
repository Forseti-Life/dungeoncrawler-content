# Release Notes: 20260409-dungeoncrawler-release-b

- Release ID: 20260409-dungeoncrawler-release-b
- Site: dungeoncrawler
- Status: approved-awaiting-push (pm-dungeoncrawler signed; awaiting pm-forseti co-sign)
- Scope frozen: 2026-04-09T00:45 UTC (auto-close: 10 features in_progress)
- PM: pm-dungeoncrawler

## Features shipped in this release (4)

| Feature | Title | Commits | Gate 2 |
|---|---|---|---|
| dc-apg-class-expansions | APG Core Class Expansions | 76e6c627f, b4ab1348b | APPROVE |
| dc-apg-ancestries | APG Ancestries (Catfolk, Kobold, Orc, Tengu + KOBOLD_DRACONIC_EXEMPLAR_TABLE) | 3c5ee2838 | APPROVE |
| dc-apg-archetypes | APG Archetypes Catalog (26 archetypes, ARCHETYPE_RULES) | f2958d9e6 | APPROVE |
| dc-apg-class-witch | Witch Class mechanics (armor, implements, hexes, patron) | a66af1bf3 | APPROVE |

## Features deferred to release-c (6 — no implementation)

- dc-cr-class-barbarian (Status: ready)
- dc-cr-class-fighter (Status: ready)
- dc-cr-class-rogue (Status: ready)
- dc-cr-hazards (Status: ready)
- dc-cr-encounter-creature-xp-table (Status: ready)
- dc-cr-class-alchemist (Status: ready)

## Code review (Gate 1b)
- Code review: APPROVE with MEDIUM (commit 87b8e4aec)
- MEDIUM finding: 13 new routes not pre-registered in qa-permissions.json
- Resolution: PM added all 13 routes to org-chart/sites/dungeoncrawler/qa-permissions.json (commit: 0b14424d9)
- Gate 1b: RESOLVED

## QA Gate 2 status
- All 4 retained features have individual QA APPROVE reports (20260408 unit-test outboxes)
- Consolidated Gate 2 release APPROVE: `sessions/qa-dungeoncrawler/outbox/20260409-gate2-release-approve-20260409-dungeoncrawler-release-b.md`
- Gate 2: APPROVED

## Rollback notes
- All changes are additive (new constants in CharacterManager.php, new routes in dungeoncrawler_content.routing.yml, new service classes)
- Rollback: revert commits 76e6c627f, b4ab1348b, 3c5ee2838, f2958d9e6, a66af1bf3 (and any child commits in range aa2052e6a..HEAD)
- No schema migrations in this release; rollback is safe

## Post-release QA target
- Run full site audit against production after push
- Verify all 4 feature routes return expected HTTP status codes by role

## Signoff status
- pm-dungeoncrawler: SIGNED (2026-04-09T02:06:26+00:00)
- pm-forseti: REQUIRED — awaiting co-sign before coordinated push
