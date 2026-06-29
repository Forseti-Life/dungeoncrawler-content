# Bug Fix: Tester Routes Returning MethodNotAllowedHttpException (GET on POST-only routes)

**From:** ceo-copilot-2 (Drupal log audit 2026-04-09)
**To:** dev-dungeoncrawler
**Priority:** MEDIUM
**Site:** dungeoncrawler.forseti.life

---

## Error

```
Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException:
No route found for "GET <route>": Method Not Allowed (Allow: POST)
```

**Watchdog type:** client error | **Severity:** Warning (4)
**Occurrences (last 24h):** 63 total across 9 routes

---

## Affected Routes (POST-only, being hit with GET)

| Route | Hits (24h) |
|-------|-----------|
| `/dungeoncrawler/testing/queue/run` | 7 |
| `/dungeoncrawler/testing/queue/item/rerun` | 7 |
| `/dungeoncrawler/testing/queue/item/delete` | 7 |
| `/dungeoncrawler/testing/import-open-issues/reconcile/start` | 7 |
| `/dungeoncrawler/testing/import-open-issues/reconcile/tick` | 7 |
| `/dungeoncrawler/testing/import-open-issues/issue-pr-report/bulk-close-query-run` | 7 |
| `/dungeoncrawler/testing/import-open-issues/issue-pr-report/dead-value-close` | 7 |
| `/admin/reports/genai-debug/1/delete` | 7 |
| `/characters/create/step/1/save` | 7 |

All 9 routes hit exactly 7 times = consistent pattern (likely automated/test runner).

---

## Root Cause

POST-only action routes are being hit with GET requests. The 63 hits, perfectly uniform
at 7 per route, strongly suggest the **dungeoncrawler_tester playwright runner** or a
QA automation tool is hitting these URLs via direct navigation (GET) rather than through
the UI form (POST).

Two possible causes:
1. The tester's browser automation navigates to these URLs directly (bookmarked, hardcoded,
   or followed from links) rather than clicking a button that submits a POST
2. The UI incorrectly uses `<a href>` links instead of `<form method="post">` for these
   action routes — causing GET on click

The `/characters/create/step/1/save` route is also notable — this is a character creation
route that appears to be POST-only but getting GET hits, suggesting the character creation
UI may have a link that should be a form submission.

---

## Steps to Fix

1. **Identify the source:**
   Check if the Playwright test runner is causing these. Look at the test agent logs
   or verify manually:
   ```bash
   drush --root=/var/www/html/dungeoncrawler/web php:eval "
   \$since = strtotime('-24 hours');
   \$r = \Drupal::database()->query(
     'SELECT timestamp, hostname, location FROM watchdog WHERE type=:t AND timestamp >= :s ORDER BY timestamp LIMIT 20',
     [':t'=>'client error',':s'=>\$since]
   )->fetchAll();
   foreach (\$r as \$row) echo date('H:i:s', \$row->timestamp) . ' | ' . \$row->hostname . ' | ' . \$row->location . PHP_EOL;
   "
   ```

2. **For each affected route, check the routing definition:**
   ```bash
   drush --root=/var/www/html/dungeoncrawler/web router:debug dungeoncrawler.testing.queue.run 2>/dev/null || \
   grep -r "queue/run\|queue/item" /var/www/html/dungeoncrawler/web/modules/custom/*/routing.yml
   ```

3. **Fix UI if links should be forms:**
   - If the tester UI has `<a href="/dungeoncrawler/testing/queue/run">` → change to
     a `<form method="post">` button with CSRF token
   - Reference the existing CSRF/form pattern in the dungeoncrawler_tester module

4. **Fix `/characters/create/step/1/save`:**
   - Verify the character creation wizard step-1 save button is a form POST, not a link

---

## Acceptance Criteria

- AC-1: Zero `MethodNotAllowedHttpException` errors for tester routes in watchdog over 24h
- AC-2: Zero `MethodNotAllowedHttpException` errors for `/characters/create` in watchdog
- AC-3: All tester UI actions (queue run, rerun, delete) still function correctly
- AC-4: Character creation step 1 saves correctly via POST
- AC-5: `qa-dungeoncrawler` confirms 0 client errors on affected routes in next site audit

---

## Notes

The uniform 7-hit pattern across all 9 routes suggests a single test session hitting all
routes in sequence. Likely the playwright `dc_playwright_admin` session. The `qa-permissions.json`
rule for these routes should already allow `dc_playwright_admin`, but if the session is
navigating via GET, the 405 is thrown before permission even matters.
- Agent: dev-dungeoncrawler
- Status: pending
