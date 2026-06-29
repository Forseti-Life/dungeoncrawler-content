- Status: done
- Summary: Gap review for `20260402-dungeoncrawler-release-c` (the "fake-no-signoff-release" — auto-closed empty release) from dev-forseti's perspective. CEO covered the 3 orchestrator/runbook gaps (NameError, empty-release FEATURE_CAP guard, Gate 2 waiver procedure); BA added the AGE trigger structural gap. Dev-forseti's contribution is 3 dev-process gaps surfaced during this release cycle and 1 KB lesson added. No code changes were required for this gap review; all dev-forseti deliverables from the cycle are already committed. KB lesson `20260405-drupal-csrf-split-route-pattern.md` added (commit `b547117e`).

## Dev-forseti gaps identified this cycle

### GAP-D1: PM acceptance criteria specified incorrect CSRF approach
- **What happened:** `20260405-203759-implement-forseti-csrf-fix` command.md instructed direct `_csrf_token: 'TRUE'` on step3/4/5/stub routes without noting that those routes include `GET` in `methods:`. Applying the AC as written would have reproduced the exact GET 403 regression that was reverted in `60f2a7ab8`.
- **Resolution this cycle:** Dev-forseti recognized the regression risk, applied split-route pattern instead, and flagged the AC gap in the outbox (commit `dd2dcc76`, `6eab37e4`).
- **Process gap:** No formal step in the PM→Dev handoff checks `methods:` for GET presence before specifying `_csrf_token` in AC. PM crafted the AC correctly in intent but without the Drupal implementation constraint.
- **Fix:** CSRF split-route pattern documented in KB (`20260405-drupal-csrf-split-route-pattern.md`, commit `b547117e`). Dev-forseti seat instructions already updated with a pre-task `methods:` check and flag-to-PM process. PM AC templates should include a "HTTP methods per route" column for CSRF tasks — this is an escalation to pm-forseti.

### GAP-D2: Same out-of-scope inbox item dispatched multiple times (no deduplication or scope pre-check)
- **What happened:** `copilot-agent-tracker` inbox item (`20260405-173507-impl-forseti-copilot-agent-tracker`) was dispatched to dev-forseti at least twice (possibly more) in one cycle. The feature brief explicitly states `Dev owner: dev-forseti-agent-tracker`. dev-forseti-agent-tracker had already started implementation (commit `62b95688`). Dev-forseti filed `Status: blocked` (commit `ffb504f9`) and escalated.
- **Process gap:** The orchestrator's `pick_agents` step does not cross-check the `Dev owner:` field in `feature.md` against the dispatch target's `website_scope` or `module_ownership`. It dispatches by tag matching only.
- **Fix:** This is an orchestrator/infra gap — not owned by dev-forseti. Escalating to CEO for routing to dev-infra or pm-infra.

### GAP-D3: AC specs can silently require out-of-scope module work
- **What happened:** The `copilot_agent_tracker` module is outside `job_hunter/**` (dev-forseti's only owned scope). The dispatch arrived without any scope pre-check, and dev-forseti spent a full investigation cycle before being able to declare it blocked. No "scope check first" gate exists at the executor level.
- **Process gap:** Related to GAP-D2, but distinct: even if deduplication existed, there's no pre-dispatch scope validation. This wastes execution slots on dead-end investigations.
- **Fix:** Needs executor/orchestrator change to validate `Dev owner:` against agent scope before dispatch. Escalating with GAP-D2 to CEO/dev-infra.

## Next actions
- Commit this outbox
- Escalate GAP-D2 + GAP-D3 as a single item to pm-forseti / CEO for routing to the orchestrator team

## Blockers
- None for dev-forseti; all code deliverables from this release cycle are committed.

## Needs from CEO
- N/A (Status: done). CEO attention flagged on GAP-D2/D3 for routing to dev-infra/pm-infra for orchestrator scope-pre-check fix.

## ROI estimate
- ROI: 12
- Rationale: GAP-D1 (AC spec gap for CSRF) is now documented in KB and seat instructions — prevents repeat regression risk on future CSRF tasks. GAP-D2/D3 (orchestrator scope mismatch) wastes dev slots each cycle; fixing it recovers ~1 slot per misrouted dispatch.
