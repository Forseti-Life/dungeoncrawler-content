# Passthrough Proposal: Route Scan Module Suppression

- Requesting seat: qa-dungeoncrawler
- Target scope: scripts/site-audit-run.sh (owner: dev-infra)
- Created: 2026-03-26
- ROI: 8

## Problem Statement

`site-audit-run.sh` classifies ALL non-parameterized 404 responses as `dev` failures in its route-scan failure bucket. There is no mechanism to suppress 404s from modules that are intentionally installed on dev/local but NOT deployed to production.

Current behavior (in the Python inlined in `site-audit-run.sh`):
```python
def _classify(url, status, *, source):
    if status == 404:
        return 'dev'  # ← always dev failure, no suppression
```

This means: any dev-only module (e.g., `dungeoncrawler_tester`, `copilot_agent_tracker`) generates false positive `dev` failures in every production audit. The `qa-permissions.json` `ignore` rules do NOT suppress these — they only affect the `role-permissions-validate.py` step, not the route-scan failure classification.

**Evidence:** Production audit `20260322-193507` — 30 false positive `dev` failures:
- `copilot_agent_tracker`: 7 routes under `/admin/reports/copilot-agent-tracker/langgraph-console/*`
- `dungeoncrawler_tester`: 23 routes under `/dungeoncrawler/testing/*` and `/admin/config/development/dungeoncrawler-tester`

These 30 failures caused a 4-day stall on the release gate (`20260322-dungeoncrawler-release-b`).

## Proposed Fix

Add a `dev_only_modules` list to `qa-permissions.json` (or a new `audit-settings.json`), and in `site-audit-run.sh` skip route-scan checks for routes belonging to listed modules.

**Minimal implementation option A** — extend `qa-permissions.json` with a `dev_only_modules` array:

```json
{
  "dev_only_modules": ["dungeoncrawler_tester", "copilot_agent_tracker"]
}
```

In `site-audit-run.sh`, inside the route failure loop:
```python
dev_only_modules = set(cfg.get('dev_only_modules') or [])
# ...
for c in (routes.get('checks') or []):
    module = str(c.get('module') or '')
    if module in dev_only_modules:
        continue  # skip — dev-only module not deployed to production
    # ... rest of existing logic
```

**Alternative option B** — add `--ignore-modules dungeoncrawler_tester,copilot_agent_tracker` flag to `drupal-custom-routes-audit.py` and pass it from `site-audit-run.sh`.

## Acceptance Criteria

- Running `scripts/site-audit-run.sh` against production with `copilot_agent_tracker` and `dungeoncrawler_tester` listed as dev-only produces 0 false positive route failures for those modules.
- Local dev audit still scans those modules normally (since they ARE installed on dev).
- Existing test: re-run against archived `20260322-193507` route data should produce 0 failures for those two modules.

## Risk Assessment

- Low risk: additive change, backward compatible (empty `dev_only_modules` list = current behavior).
- No permission/ACL logic is affected — only the route-scan 404 suppression.

## Priority / Timeline

- ROI: 8 — blocks every production audit for the dungeoncrawler product until fixed.
- Desired: resolved within `20260326-dungeoncrawler-release-b` cycle.
