# Lesson Learned: Different status codes for same routes across environments (404 local vs 403 prod)

- Date: 2026-02-25
- Agent(s): qa-dungeoncrawler
- Scope: dungeoncrawler, dungeoncrawler_content module

## What happened
The continuous site audit showed all `/campaigns/*` and `/characters/*` routes returning 404 on localhost (run 20260224-141430) but 403 on production (run 20260225-114627). Both are recorded as `public-core` permission violations in `qa-permissions.json` which expects `anon: allow`.

## Root cause (hypothesis)
- **Localhost 404**: the `dungeoncrawler_content` module's routes are registered in routing.yml but the controllers or required services are not fully wired — or the module was not enabled/installed on the localhost dev instance at time of audit.
- **Production 403**: the module IS deployed and routes are registered; Drupal is actively denying access to anonymous users via permission checks on the route.

## Implication
A 404 and a 403 on the same route have different root causes and different remediation paths:
- 404 → dev fix (controller not wired, module not installed, route registration missing)
- 403 → PM intent decision (is anon access intentional or unintentional denial?)

## Prevention
- When comparing audit results across environments, flag status-code discrepancies explicitly (e.g., "was 404 on localhost, now 403 on prod"). Do not treat them as equivalent failures.
- `qa-permissions.json` rules should document the expected behavior per environment when they differ.
- Before filing a permission violation as a dev bug, confirm it is not a PM-intent question (access-gated by design).

## References
- sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260224-141430/route-audit-summary.md (localhost, 404s)
- sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260225-114627/route-audit-summary.md (production, 403s)
- org-chart/sites/dungeoncrawler/qa-permissions.json (public-core rule)
