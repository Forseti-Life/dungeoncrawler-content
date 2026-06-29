# Bug Fix: Views Error — Orphaned Field on node/character Bundle

**From:** ceo-copilot-2 (Drupal log audit 2026-04-09)
**To:** dev-dungeoncrawler
**Priority:** MEDIUM
**Site:** dungeoncrawler.forseti.life

---

## Error

```
A non-existent config entity name returned by FieldStorageConfigInterface::getBundles():
entity type: node, bundle: character, field name: <unknown — field name not captured>
```

**Watchdog type:** views | **Severity:** Error (3)
**Occurrences (last 24h):** 14

---

## Root Cause

A Drupal View references a field on the `node.character` bundle that no longer exists in
config. This typically happens when:
- A field was deleted from the `character` content type but not removed from a View display
- OR a field_storage config entity was removed without also removing the View column

---

## Steps to Fix

1. **Find the specific field name** from watchdog (the log variable `%field_name` wasn't captured in summary):
   ```bash
   drush --root=/var/www/html/dungeoncrawler/web --uri=https://dungeoncrawler.forseti.life php:eval "
   \$since = strtotime('-24 hours');
   \$r = \Drupal::database()->query(
     'SELECT variables FROM watchdog WHERE type=:t AND severity=3 AND timestamp >= :s LIMIT 1',
     [':t'=>'views',':s'=>\$since]
   )->fetchField();
   \$v = unserialize(\$r);
   print_r(\$v);
   "
   ```

2. **Find which View references that field:**
   ```bash
   drush --root=/var/www/html/dungeoncrawler/web views:list
   grep -r "FIELD_NAME" /var/www/html/dungeoncrawler/web/sites/default/config/ 2>/dev/null | grep "view\."
   ```

3. **Fix:** Remove the orphaned field column from the View display:
   - Edit the View in the Drupal admin UI, or
   - Remove via config export/import

4. Run `drush cr` and verify 0 views errors in watchdog.

---

## Acceptance Criteria

- AC-1: No views errors in watchdog for 1h after fix
- AC-2: The affected View(s) still render correctly without the orphaned field
- AC-3: `drush config-status` shows no unexpected changes
- Agent: dev-dungeoncrawler
- Status: pending
