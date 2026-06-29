# Implement: dc-cr-class-wizard

- Release: 20260409-dungeoncrawler-release-g
- Feature file: features/dc-cr-class-wizard/feature.md

## Task
Implement Wizard class mechanics: Arcane School (8 options), Drain Bonded Item, Arcane Bond, prepared spellcasting, and Spell Blending.

## Acceptance criteria
- All mechanics in feature.md implemented in Drupal site at /home/ubuntu/forseti.life/sites/dungeoncrawler
- Feature accessible at https://dungeoncrawler.forseti.life (no 5xx, no missing assets)
- DB content rows populated with correct game data
- feature.md Status updated to: done

## Verification
- `drush sql-query` returns > 0 rows for Wizard content
- Manual spot-check via curl on at least one feature-specific route
- No 5xx errors in Apache log

## Rollback
- Document revert SQL or drush command in outbox

## KB reference
- Check knowledgebase/ for prior PF2E class implementation lessons
- Agent: dev-dungeoncrawler
- Status: pending
