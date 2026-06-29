# Verification Report — 20260322-193507-qa-findings-dungeoncrawler-30

- QA agent: qa-dungeoncrawler
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260322-193507-qa-findings-dungeoncrawler-30.md
- Re-verified: 2026-03-27
- Prior QA analysis: 2026-03-26 (checklist entry BLOCKED-PENDING-SCRIPT-FIX)

## Decision: BLOCKED-PENDING-SCRIPT-FIX (unchanged)

Prior analysis confirmed correct on re-verification. No new action available within QA scope until dev-infra script fix lands.

---

## Root cause (confirmed)

All 30 failures from production audit `20260322-193507` are **false positives**:
- `copilot_agent_tracker`: 7 routes under `/admin/reports/copilot-agent-tracker/langgraph-console/*`
- `dungeoncrawler_tester`: 23 routes under `/dungeoncrawler/testing/*` and sub-paths

Both modules are dev-only (not deployed to production). Routes return 404 on production — correct behavior.

## Why qa-permissions.json cannot fix this (verified 2026-03-27)

The `fix-qa-permissions-dev-only-routes` inbox item (`20260326-222717`) and the dev outbox (`8555c3127`) suggest a 2-rule qa-permissions.json update. **This will NOT fix the 30 production failures.**

Root cause verified via script inspection:

```python
# site-audit-run.sh _classify() function (lines 80–99):
def _classify(url: str, status: int, *, source: str) -> str:
    if status >= 500:
        return 'dev'
    if status == 404:
        return 'dev'   # ← ALL 404s → 'dev', never 'ignore'
    ...
```

In the route-scan section (lines 239–240):
```python
target = _classify(url, status, source='routes')
if target == 'ignore':
    continue     # only 'ignore' targets are suppressed
# else: appended to failures[]
```

`qa-permissions.json` rules are consumed **only** by `role-permissions-validate.py` (the permissions step). They have **no effect** on the route-scan `failures[]` bucket. Setting a rule to `ignore` in qa-permissions.json will not prevent 404s from appearing in `Other failures (4xx/5xx)` in findings-summary.

**Implication:** The `fix-qa-permissions-dev-only-routes` inbox item (`20260326-222717`) should be processed as a fast-exit — the proposed 2-rule update would be incorrect/misleading since it wouldn't actually fix the 30 failures.

## Required fix

Script-level change to `site-audit-run.sh` (dev-infra scope):
- Add `dev_only_modules` list to `qa-permissions.json` (or `audit-settings.json`)
- In route-scan loop: check `if module in dev_only_modules: continue` before calling `_classify()`

Passthrough proposal: `sessions/qa-dungeoncrawler/artifacts/20260326-passthrough-dev-infra-route-module-suppression/proposal.md`

## Risk assessment for release gate

- 30 failures are confirmed false positives — no actual product regression present
- Local audit 20260327-012014: 0 failures (no local manifestation of the issue)
- Risk acceptance for production gate: PM decision required

## Regression checklist status

`20260322-193507-qa-findings-dungeoncrawler-30` — already marked `[x]` BLOCKED-PENDING-SCRIPT-FIX 2026-03-26. No change needed.
