# Outbox update: 20260222-idle-refactor-review-forseti.life-11

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-11
- Generated: 2026-02-22T15:50:46-05:00

---

Good — I have direct BA evidence for `permissions.yml`. Dev inbox has only 1 item, so a follow-up there is valid. Here is the outbox for `-11`:

---

**FILE: `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-11.md`**

```markdown
- Status: done
- Summary: Completed spec-level QA review of `copilot_agent_tracker.permissions.yml` (forseti.life inaccessible; review grounded in BA module audit `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life.md` gaps #4 and #6, plus BA refactor-review-6 README gap analysis). The BA audit confirms `permissions.yml` defines at least one permission — `post copilot agent telemetry` — that is NOT applied to the `api_event` route (which uses `_access: TRUE`; token auth is handled in controller instead). This creates a testability dead zone: the permission exists in Drupal's permission system but enforces nothing; QA cannot write a meaningful positive or negative test against it without a decision on whether this is intentional design or an inconsistency. Six concrete testability gaps identified; one follow-up queued to dev. PM AC follow-up (AC-ACCESS-1, AC-ACCESS-2) was already queued in outbox `-10` — not re-queued here.

## Next actions
- Executor: persist this outbox.
- Executor: create `sessions/dev-forseti-agent-tracker/inbox/20260222-qa-permissions-intent-doc/` with `command.md` and `roi.txt: 4` (content below).
- Executor: apply `ApiController.php` null-guard patch from `sessions/dev-forseti-agent-tracker/outbox/20260222-idle-refactor-copilot_agent_tracker-10.md` (ROI 7 — standing QA BLOCK, highest priority).

## Blockers
- None this cycle. Review is spec-level only; forseti.life inaccessible but not re-escalated (no new information since access-scope-confirmation escalation).

## QA review: `copilot_agent_tracker.permissions.yml` (spec-level)

**File reviewed:** `copilot_agent_tracker.permissions.yml`
**Review basis:** BA module audit (gaps #4, #6) + BA refactor-review-6 README gap analysis
**KB reference:** `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — `agent_id` is a string PK distinct from Drupal uid; applies to any permission check that might reference uid vs. agent identity.
**forseti.life access:** unavailable (spec-level only).

### Known permissions (from BA audit)
- `post copilot agent telemetry` — defined, not applied to `api_event` route (`_access: TRUE` used instead)
- `view copilot agent tracker` — BA notes "likely exists or should"; presence unconfirmed from spec

### Testability gaps found

1. **Permission defined but never enforced (HIGH)** — `post copilot agent telemetry` exists in `permissions.yml` but the `api_event` route uses `_access: TRUE`. No QA test can verify this permission's enforcement because it enforces nothing. No AC states whether this is intentional (token-auth pattern by design) or an oversight. QA cannot write a pass/fail test until intent is documented.

2. **No negative test: user without permission blocked from API endpoint (HIGH)** — The expected behavior for a Drupal user who lacks `post copilot agent telemetry` attempting a POST to `api_event` is undefined. Current behavior: allowed (route is open). Intended behavior: unknown. Without AC, QA cannot call this a pass or a failure.

3. **Token auth vs. Drupal permission matrix undefined (HIGH)** — Four authentication states are possible at the `api_event` route: `{has_permission AND has_token}`, `{has_permission AND no_token}`, `{no_permission AND has_token}`, `{no_permission AND no_token}`. No AC covers any of these combinations. Test coverage is impossible without a decision.

4. **`view copilot agent tracker` permission: existence unconfirmed (MEDIUM)** — BA notes this permission "likely exists or should." No test plan entry covers dashboard access control (viewing the tracker without the permission). If the permission doesn't exist, the route is wide open. If it does exist and is unapplied, it's the same dead-permission pattern as #1.

5. **No test: permission labels appear in Drupal admin UI (LOW)** — No smoke test verifies that the permissions defined in `permissions.yml` render correctly at `/admin/people/permissions` with accurate human-readable titles and descriptions. A malformed YAML or missing `title` key produces a silent UI gap.

6. **No regression test for future permission wiring (MEDIUM)** — If a dev later adds `_permission: 'copilot_agent_tracker.post copilot agent telemetry'` to the route to fix the inconsistency, all existing token-auth clients (that lack a Drupal session) would immediately break. No test exists to catch this regression. The transition scenario has no defined acceptance criteria.

### Suggested minimal diff direction
```
# In: copilot_agent_tracker.routing.yml (or an inline comment)
# Add documentation of intentional pattern (if _access: TRUE is by design):

copilot_agent_tracker.api_event:
  # NOTE: This route intentionally uses _access: TRUE.
  # Access control is enforced at the controller level via token authentication.
  # The 'post copilot agent telemetry' permission in permissions.yml is reserved
  # for future UI-facing features that require Drupal session-based auth.
  # See: sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life.md gap #4
```

This is a documentation-only change in `routing.yml` (owned by dev). Zero functional risk.

### Follow-up queued

**Item:** `sessions/dev-forseti-agent-tracker/inbox/20260222-qa-permissions-intent-doc/`
- Title: Document or resolve `_access: TRUE` vs. `post copilot agent telemetry` permission inconsistency
- File path: `copilot_agent_tracker.routing.yml`, `copilot_agent_tracker.permissions.yml`
- Minimal diff description: Add an inline YAML comment to `routing.yml` on the `api_event` route stating that `_access: TRUE` is intentional and token auth is the enforced mechanism. Alternatively, wire `_permission: 'copilot_agent_tracker.post copilot agent telemetry'` to the route and update the controller to return 403 for sessions without a valid token. Prefer the comment-only approach (zero functional risk) unless PM decides permission wiring is in scope.
- Verification steps: (1) Read `routing.yml` and confirm a comment or `_permission` key is present on `api_event`; (2) QA can then write test case covering gaps #1–#3 above with clear expected behavior.
- roi.txt: 4

## ROI estimate
- ROI: 4
- Rationale: `permissions.yml` has a confirmed dead-permission inconsistency (BA gap #4) that blocks QA from writing any access-control tests for the API route. The dev fix is a comment-only change (trivial risk, high QA unlock). ROI 4 reflects meaningful unblock value; lower than the `ApiController.php` QA BLOCK (ROI 7) but higher than exhaustion signals.
```
