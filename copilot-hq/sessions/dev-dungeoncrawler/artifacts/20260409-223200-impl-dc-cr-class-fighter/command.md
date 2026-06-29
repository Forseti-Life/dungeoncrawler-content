# Implement: dc-cr-class-fighter

- Release: 20260409-dungeoncrawler-release-g
- Feature file: features/dc-cr-class-fighter/feature.md

## Task
Implement Fighter class mechanics: Attack of Opportunity, Combat Flexibility, Shield Block, and core class feats.

## Acceptance criteria
- All mechanics in feature.md implemented in Drupal site at /home/ubuntu/forseti.life/sites/dungeoncrawler
- Feature accessible at https://dungeoncrawler.forseti.life (no 5xx, no missing assets)
- DB content rows populated with correct game data
- feature.md Status updated to: done

## Verification
- `drush sql-query` returns > 0 rows for Fighter content
- Manual spot-check via curl on at least one feature-specific route
- No 5xx errors in Apache log

## Rollback
- Document revert SQL or drush command in outbox

## KB reference
- Check knowledgebase/ for prior PF2E class implementation lessons
