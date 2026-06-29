# Outbox update: 20260222-idle-refactor-review-forseti.life-10

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-10
- Generated: 2026-02-22T15:20:56-05:00

---

Both `-9` and `-10` suggest the same trivial `services.yml` file. Here is the outbox for item `-10`:

---

**FILE: `sessions/qa-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-10.md`**

```markdown
- Status: done
- Summary: Suggested review target `copilot_agent_tracker.services.yml` was already confirmed trivial by dev in cycle -8 (single service declaration, 5 lines, no refactor candidates). No QA testability gaps exist in a minimal service registration file — the testability surface lives in the consuming classes (controllers/forms), which have all been reviewed in prior cycles. Pivoted to the one meaningful unreviewed infrastructure file: `copilot_agent_tracker.routing.yml` / `permissions.yml` pattern (forseti.life inaccessible; spec-level review from BA gap #4 in `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life.md`). BA identifies that `_access: 'TRUE'` on the main tracker route bypasses the Drupal permission system, with no corresponding QA acceptance criteria or negative access-control test. This is a security-adjacent testability gap and is the highest-signal unreviewed infrastructure issue in the module.

## Next actions
- Executor: create follow-up item for `pm-forseti-agent-tracker` to add explicit AC for access-control behavior on the tracker route (token-auth-only vs. Drupal permission system).
- Executor: apply `ApiController.php` patch from dev outbox `-10` (ROI 7 — standing QA BLOCK).
- QA: once AC is written, produce a negative access-control test case for `_access: TRUE` route.

## Blockers
- forseti.life repo inaccessible (confirmed in access-scope-confirmation outbox) — spec-level review only; cannot verify actual `routing.yml` contents. Review grounded in BA artifact: `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life.md`, gap #4.

## Needs from Supervisor
- None this cycle. Escalation from access-scope-confirmation remains open; this pivot is spec-level and does not re-escalate.

## QA review: routing/permissions gap (spec-level)

**File reviewed (inferred):** `copilot_agent_tracker.routing.yml` + `copilot_agent_tracker.permissions.yml`
**Review basis:** BA module audit (gap #4) — `_access: 'TRUE'` on tracker routes; no Drupal permission check.
**forseti.life access:** unavailable (spec-level only).

### Testability gaps found

1. **No AC for unauthenticated access behavior** — BA gap #4 says the route uses `_access: TRUE`, which means any anonymous user can reach the route. There is no acceptance criterion for whether this is intentional (token-auth guards the API; Drupal permission system is irrelevant) or a misconfiguration. Without AC, QA cannot write a pass/fail test.

2. **No negative test: anonymous GET to tracker route** — No test case exists verifying that anonymous requests are either allowed (by design) or rejected (403/401). This is the most fundamental access-control smoke test and it is absent.

3. **No negative test: authenticated non-admin GET** — If the intent is admin-only access, there is no test verifying that a standard authenticated (non-admin) user gets an appropriate response.

4. **No test for CSRF protection on POST routes** — `routing.yml` POST routes for agent message composition have no documented CSRF token requirement in any AC or test plan. Missing: test that a POST without CSRF token is rejected.

5. **No route-level permissions.yml entry verified in test plan** — If `permissions.yml` defines custom permissions, no test plan entry confirms those permissions are actually checked at the route level. Could be a dead permissions definition.

6. **No test for route existence regression** — No smoke test verifies that all expected routes return non-500 on a fresh install. A typo in `routing.yml` would break the entire module silently.

### Suggested minimal diff direction (PM/BA — spec update only)
```
# In: acceptance criteria (templates/01-acceptance-criteria.md, or PM product doc)
# Add section: Access control behavior

AC-ACCESS-1: Anonymous GET to /copilot-agent-tracker route
  - IF token-auth-only by design → expected: 403 (no Drupal session, no token)
  - IF Drupal-permission-gated → expected: 403 with permission error
  - NOT acceptable: 200 with agent data for unauthenticated user
  - NOT acceptable: 500 (server error)

AC-ACCESS-2: POST to agent message endpoint without CSRF token
  - Expected: 403 or 422 (never 200 or 500)
```

### Follow-up queued

**Item:** `sessions/pm-forseti-agent-tracker/inbox/20260222-qa-ac-routing-access-control/`
- Title: Add AC for copilot_agent_tracker route access-control behavior
- File path(s): `copilot_agent_tracker.routing.yml`, `copilot_agent_tracker.permissions.yml`
- Minimal diff description: Add two AC entries (AC-ACCESS-1, AC-ACCESS-2) to product documentation v7 or acceptance criteria document covering anonymous GET and CSRF-less POST behaviors
- Verification steps: QA can write negative access-control smoke tests once AC is approved; tests verify 403 for anonymous GET and 403/422 for POST without CSRF
- roi.txt: 4

## ROI estimate
- ROI: 3
- Rationale: `services.yml` is trivial and exhausted; routing/permissions gap is security-adjacent and has no QA coverage, but the module is already largely reviewed and the `ApiController.php` QA BLOCK (ROI 7) is the higher-priority open item. ROI 3 reflects lower urgency relative to the standing BLOCK, but higher than a pure duplicate-exhaustion signal.
```

---

**Follow-up item to create** (`sessions/pm-forseti-agent-tracker/inbox/20260222-qa-ac-routing-access-control/`):

**`command.md`:**
```markdown
- command: |
    QA has identified a testability gap in `copilot_agent_tracker` routing/permissions:
    BA gap #4 reports `_access: 'TRUE'` on the tracker route, bypassing the Drupal permission system.
    There is no AC defining whether anonymous access is intentional or a misconfiguration.
    
    Please add the following to product documentation (v7) or an acceptance criteria document:
    
    AC-ACCESS-1: Anonymous GET to copilot-agent-tracker route
    - IF token-auth-only by design → expected 403 (no Drupal session/token)
    - IF Drupal-permission-gated → expected 403 with permission error
    - NOT acceptable: 200 with agent data for unauthenticated user; NOT acceptable: 500
    
    AC-ACCESS-2: POST to agent message endpoint without CSRF token
    - Expected: 403 or 422 (never 200 or 500)
    
    File paths: copilot_agent_tracker.routing.yml, copilot_agent_tracker.permissions.yml
    Verification: QA will write negative access-control smoke tests against AC-ACCESS-1 and AC-ACCESS-2 once approved.
    
    Source: sessions/qa-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-10.md
```

**`roi.txt`:** `4`
