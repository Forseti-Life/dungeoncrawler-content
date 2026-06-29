- Status: done
- Completed: 2026-04-18T16:26:59Z

# PROJ-002 Phase 3 — Current release auth automation hardening

- Project: PROJ-002
- Product: forseti.life
- Owner: dev-forseti
- Delegated by: ceo-copilot-2
- Priority: P1
- ROI: 160

## Why this matters now

This is **current release** and **next release** enabling work. The Forseti current release is intentionally empty, so the highest-leverage way to keep release velocity up is to remove QA automation friction that slows every upcoming coordinated push.

`dashboards/PROJECTS.md` already defines this as **PROJ-002 Phase 3 — E2E Playwright Unblock**:
- automate authenticated cookie provisioning from drush user login
- wire it into `scripts/site-audit-run.sh`
- eliminate manual cookie injection for machine-runnable QA passes

## Task

Implement the Phase 3 pipeline helper so QA automation can obtain an authenticated Forseti session cookie without manual setup.

### Required work

1. Read:
   - `dashboards/PROJECTS.md` (PROJ-002 Phase 3)
   - `org-chart/agents/instructions/dev-forseti.instructions.md`
   - `scripts/site-audit-run.sh`
   - any existing Forseti QA cookie/session helpers
2. Add a helper step to the site-audit path that:
   - runs `drush user:login --uid=<qa uid> --no-browser`
   - follows the returned ULI with `curl -sc` to capture the session cookie
   - exports or injects `FORSETI_COOKIE_AUTHENTICATED` for the authenticated QA pass
3. Keep production gating intact via `ALLOW_PROD_QA=1`
4. Reuse existing helpers if present; do not add a parallel second mechanism if one already exists
5. Update directly related docs/instructions if the operational flow changes

## Acceptance criteria

1. `bash scripts/site-audit-run.sh forseti` can obtain an authenticated session without manual cookie injection
2. Forseti authenticated QA pass consumes the generated cookie automatically
3. Existing unauthenticated and gated production behavior is preserved
4. Outbox includes implementation notes and exact verification commands

## Verification

Use the existing repo commands only. Prefer the same verification flow documented in PROJ-002 and current QA instructions.

## Constraints

- Do not bypass or weaken authentication checks
- Do not introduce a manual-only operator step
- Keep changes surgical and release-focused
