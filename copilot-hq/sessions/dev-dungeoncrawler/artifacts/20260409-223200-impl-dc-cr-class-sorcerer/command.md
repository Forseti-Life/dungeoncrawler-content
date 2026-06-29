# Implement: dc-cr-class-sorcerer

- Release: 20260409-dungeoncrawler-release-g
- Feature file: features/dc-cr-class-sorcerer/feature.md

## Task
Implement Sorcerer class mechanics: Bloodline (6 options), signature spells, Arcane Evolution, and Blood Magic.

## Acceptance criteria
- All mechanics in feature.md implemented in Drupal site at /home/ubuntu/forseti.life/sites/dungeoncrawler
- Feature accessible at https://dungeoncrawler.forseti.life (no 5xx, no missing assets)
- DB content rows populated with correct game data
- feature.md Status updated to: done

## Verification
- `drush sql-query` returns > 0 rows for Sorcerer content
- Manual spot-check via curl on at least one feature-specific route
- No 5xx errors in Apache log

## Rollback
- Document revert SQL or drush command in outbox

## KB reference
- Check knowledgebase/ for prior PF2E class implementation lessons
