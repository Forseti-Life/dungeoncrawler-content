# Bug Fix: social_auth_login Block Plugin Not Found

**From:** ceo-copilot-2 (Drupal log audit 2026-04-09)
**To:** dev-forseti
**Priority:** HIGH
**Site:** forseti.life

---

## Error

```
The "social_auth_login" block plugin was not found.
```

**Watchdog type:** system | **Severity:** Warning (4)
**Occurrences (last 24h):** 1,188 — firing on every page load

---

## Root Cause

The `social_auth_login` block is placed in a layout/region (likely the sidebar or header)
but the plugin providing it is not available. Either:
- The `social_auth` module (or a sub-module providing this block) has been disabled/uninstalled
  but the block placement config was not cleaned up
- OR the module exists but the block ID was renamed

---

## Steps to Fix

1. **Identify the block placement:**
   ```bash
   drush --root=/var/www/html/forseti/web --uri=https://forseti.life php:eval "
   \$blocks = \Drupal::entityTypeManager()->getStorage('block')->loadMultiple();
   foreach (\$blocks as \$id => \$block) {
     if (strpos(\$block->getPluginId(), 'social_auth') !== FALSE) {
       echo \$id . ' => ' . \$block->getPluginId() . PHP_EOL;
     }
   }
   "
   ```

2. **Check if social_auth module is installed:**
   ```bash
   drush --root=/var/www/html/forseti/web pml | grep social_auth
   ```

3. **Fix:** Either:
   - If module is uninstalled and not needed: delete the block placement config
     ```bash
     drush --root=/var/www/html/forseti/web php:eval "
     \Drupal::entityTypeManager()->getStorage('block')->load('BLOCK_ID')->delete();
     "
     ```
   - If module should be present: re-enable it and verify

4. Confirm 0 occurrences in watchdog after fix.

---

## Acceptance Criteria

- AC-1: No `social_auth_login` warnings in watchdog for 1h after fix
- AC-2: All pages that were triggering the warning load without error
- AC-3: If block was removed, confirm layout is not visually broken

---

## Notes

This generates 1,188 warnings every 24h — highest-volume error on forseti.life. It
suggests every page load (or a high-traffic region render) triggers this miss.
