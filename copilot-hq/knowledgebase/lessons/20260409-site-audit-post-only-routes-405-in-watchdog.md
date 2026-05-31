# KB Lesson: Site Audit Triggers 405 Errors on POST-only Routes in Watchdog

**Date:** 2026-04-09
**Discovered by:** dev-dungeoncrawler
**Severity:** Medium (noise pollution in production watchdog; does not affect functionality)

## What Happened

63 `MethodNotAllowedHttpException` entries appeared in the dungeoncrawler production watchdog
over 24h, exactly 7 hits per route across 9 POST-only routes. All errors were 405 from GET
requests hitting routes that are correctly defined as `methods: [POST]`.

## Root Cause

`scripts/site-audit-run.sh` runs `drupal-custom-routes-audit.py` to probe all custom Drupal
routes. That script correctly skips GET probes for POST-only routes, recording them with
`status=0, note="POST-only route, GET probe skipped"` in the output JSON. **However**, the
inline Python that builds `all_paths_file` reads ALL entries from the custom-routes JSON
(including POST-only skip entries) and adds those paths to the validate list. Then
`site-validate-urls.py` probes every path in `all_paths_file` via GET — causing 405s on
POST-only routes for every role context (7 roles × 9 routes = 63 errors).

The 7-per-route pattern = 7 role audit contexts (anon + 6 authenticated roles).

## Key Files

- `scripts/site-audit-run.sh` — inline Python at ~line 826 (`read_urls_from` function)
- `scripts/drupal-custom-routes-audit.py` — correctly skips POST-only probes; sets `note="POST-only route, GET probe skipped"` and `status=0`
- `scripts/site-validate-urls.py` — probes all paths from `all_paths_file` via GET (does not check `note`)

## Investigation Eliminated

- Routing YAML — all 9 routes correctly `methods: [POST]` only ✅
- JS frontend (queue-management.js, import-open-issues-reconcile.js, dead-value-actions.js) — all use `fetch` with `method: 'POST'` ✅
- Twig templates — buttons only, no `<a href>` to action routes ✅
- Functional PHP tests — only intentional 405 test (`ApiRoutesTest::testCharacterSaveApiRouteNegative`) ✅

## Fix Required

**Owner: dev-infra** — patch `read_urls_from` in `site-audit-run.sh` (inline Python, ~line 839):

```python
# BEFORE
    if 'checks' in data and isinstance(data.get('checks'), list):
      for c in data.get('checks') or []:
        if isinstance(c, dict):
          urls.append(str(c.get('url') or ''))

# AFTER
    if 'checks' in data and isinstance(data.get('checks'), list):
      for c in data.get('checks') or []:
        if isinstance(c, dict):
          # Skip POST-only routes: site-validate-urls probes with GET and triggers false 405s
          if str(c.get('note') or '').startswith('POST-only'):
            continue
          urls.append(str(c.get('url') or ''))
```

This filters out any route check where `note` starts with `"POST-only"` before adding it
to the validated-paths union.

## Verification

After fix: run `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler` and confirm:
- No 405 entries in Drupal watchdog for the 9 affected routes
- `drupal-custom-routes-audit.py` output still contains the 9 routes with `status=0, note="POST-only..."`
- `all_paths_file` does NOT contain any of the 9 POST-only route paths

## Pattern to Watch For

Whenever adding new POST-only routes to `dungeoncrawler_tester` or `dungeoncrawler_content`,
no Drupal or UI changes are needed — the fix above will prevent the audit pipeline from
probing them with GET. Until the fix is applied, expect 7 watchdog warnings per route per
audit run.
