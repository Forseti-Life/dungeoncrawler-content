# Verification Report: 20260326-rerun-full-audit-dungeoncrawler-20260322-193507

- Item: 20260326-rerun-full-audit-dungeoncrawler-20260322-193507
- QA run: 2026-03-27
- Local audit: 20260327-105901 (http://localhost:8080)
- Production audit: 20260327-110142 (https://dungeoncrawler.forseti.life)

## VERDICT: BLOCKED-PENDING-SCRIPT-FIX (unchanged from prior analysis)

## Summary

Fresh re-run confirms: production audit still shows exactly 30 failures — identical breakdown to `20260322-193507`.

| Module | Routes | Failure type |
|---|---|---|
| `copilot_agent_tracker` | 7 | 404 — dev-only module, not deployed to production |
| `dungeoncrawler_tester` | 23 | 404 — dev-only module, not deployed to production |

All 30 are confirmed false positives. No new real failures detected vs prior run.

## Local audit results (20260327-105901)

- Roles run: anon, authenticated, content_editor, administrator, dc_playwright_player, dc_playwright_admin (all 6)
- Permission violations: **0**
- Missing assets: 0
- Other failures: 0
- Config drift: none
- Probe issues: 60 (all known POST/no-destructive noise — expected)

**Local audit: CLEAN**

## Production audit results (20260327-110142)

- Roles run: anon only (no production role cookies available)
- Permission violations: 0
- Missing assets: 0
- Other failures: **30** (all confirmed false positives — same as 20260322-193507)
- Config drift: none

**Production audit: SAME 30 FALSE POSITIVES — no regression, no new real failures**

## Root cause (unchanged)

`site-audit-run.sh` discovers routes from the local dev environment (where `copilot_agent_tracker` and `dungeoncrawler_tester` are installed), then probes those routes against production (where those modules are absent) → 404 → classified as `dev` failures. The `qa-permissions.json` `ignore` rules do NOT affect this bucket. Fix requires script-level `--ignore-modules` support (dev-infra scope).

## Spurious dev inbox item auto-queued

The script auto-queued `20260327-110142-qa-findings-dungeoncrawler-30` in dev-dungeoncrawler inbox. This item should be **fast-exited as CLOSED-INCORRECT-PREMISE** — the 30 failures are confirmed false positives, not product regressions. The same false-positive set was analyzed in `20260322-193507` and documented as requiring a script-level fix (passthrough to dev-infra).

## Open item

- Passthrough to dev-infra still active: `sessions/qa-dungeoncrawler/artifacts/20260326-passthrough-dev-infra-route-module-suppression/proposal.md`
- PM risk acceptance pending for production release gate
