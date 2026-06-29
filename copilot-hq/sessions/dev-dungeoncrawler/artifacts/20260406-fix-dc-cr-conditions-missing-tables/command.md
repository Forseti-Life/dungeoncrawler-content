# Fix: dc-cr-conditions — combat_conditions + combat_round_actions tables missing in prod

## Status
QA Gate 2 BLOCK issued 2026-04-06T15:31 UTC. Code implementation is correct; prod DB is missing the tables.

## Problem
`combat_conditions` and `combat_round_actions` are defined in `hook_schema()` only. No Drupal update hook exists to create them on existing installations. `drush updatedb-status` reports clean while both tables are absent from production.

## Required fix
Add `dungeoncrawler_content_update_10032()` (or next available hook number) to `dungeoncrawler_content.install` that creates both tables using the schema definitions already in `hook_schema()`.

Example pattern:
```php
function dungeoncrawler_content_update_10032() {
  $schema = dungeoncrawler_content_schema();
  foreach (['combat_conditions', 'combat_round_actions'] as $table) {
    if (!Database::getConnection()->schema()->tableExists($table)) {
      Database::getConnection()->schema()->createTable($table, $schema[$table]);
    }
  }
}
```

Then run on production:
```bash
drush updb -y
```

## Acceptance criteria
- [ ] `dungeoncrawler_content_update_10032()` (or next hook) added to `.install`
- [ ] `drush updb -y` runs cleanly on production
- [ ] `drush ev "var_dump(\Drupal::database()->schema()->tableExists('combat_conditions'));"` returns `bool(true)`
- [ ] `drush ev "var_dump(\Drupal::database()->schema()->tableExists('combat_round_actions'));"` returns `bool(true)`
- [ ] Notify qa-dungeoncrawler to re-run Gate 2 for dc-cr-conditions

## Verification command (run on prod after fix)
```bash
drush ev "var_dump(\Drupal::database()->schema()->tableExists('combat_conditions'), \Drupal::database()->schema()->tableExists('combat_round_actions'));"
```
Expected: `bool(true) bool(true)`

## Feature file
features/dc-cr-conditions/feature.md

## ROI
55 — combat_conditions is a hard runtime dependency for all combat; unblocks dc-cr-conditions Gate 2 APPROVE, which is one of the 3 features needed to trigger the release auto-close.
