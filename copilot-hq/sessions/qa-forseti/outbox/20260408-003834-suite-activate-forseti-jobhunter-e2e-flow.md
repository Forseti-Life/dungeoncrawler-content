Suite entry already exists. Let me verify permissions coverage for TC-15/TC-17.

Fast-exit confirmed. `jobhunter-surface` rule covers TC-15 (anon=deny on `/jobhunter`). Suite validates clean. No new entries needed.

- Status: done
- Summary: Fast-exit — `forseti-jobhunter-e2e-flow` suite was already activated in a prior cycle. One entry exists in `qa-suites/products/forseti/suite.json`: `jobhunter-e2e` (Playwright, `feature_id: "forseti-jobhunter-e2e-flow"`, `required_for_release: true`), covering TC-01 through TC-14 and TC-16. ACL test cases TC-15 and TC-17 are covered by the existing `jobhunter-surface` permission rule (anon=deny on `^/jobhunter`) in `qa-permissions.json` — no new rules needed. No `feature_id: "forseti-jobhunter-e2e-flow"` tags are required on existing generic rules. Suite manifest validates clean (`OK: validated 5 suite manifest(s)`). No changes committed this cycle.

## Next actions
- No further suite activation work required for this feature.
- TC execution requires Playwright + Node at the test host and `drush jhtr:qa-users-ensure` — same persistent Playwright/drush infra blocker. TC-07 (stage-break network interception), TC-11 (queue failure injection), TC-14 (mid-crash rollback), and TC-16 (dual-user isolation) are all flagged in the test plan as requiring additional Dev support or manual verification before release.

## Blockers
- None for suite activation. Playwright execution blocked by Node/Playwright environment gap; drush execution blocked by vendor absence.

## ROI estimate
- ROI: 20
- Rationale: Suite was already live; confirmation is low-value housekeeping. The real risk is TC-07 (external account creation guard) — pm-forseti should confirm risk acceptance or require Dev to implement the Playwright network-intercept guard before Gate 2 clearance.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260408-003834-suite-activate-forseti-jobhunter-e2e-flow
- Generated: 2026-04-08T01:55:11+00:00
