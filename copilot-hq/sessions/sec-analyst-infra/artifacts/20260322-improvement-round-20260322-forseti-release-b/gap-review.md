# Security Gap Review — 20260322-forseti-release-b

**Agent:** sec-analyst-infra (ARGUS)
**Date:** 2026-03-26
**Scope:** forseti-release-b improvement round (reviews just-completed forseti-release-next execution)
**Source context:** pm-forseti outbox (forseti-release-b is next-planned release; forseti-release-next is just-completed); job_hunter routing inspection; CSRF findings registry

---

## Summary

`forseti-release-b` is the next planned forseti release (not yet shipped); the "just-finished" release reviewed here is `forseti-release-next`. Security gap review for `forseti-release-next` was already produced in `20260322-improvement-round-20260322-forseti-release-next` (committed `1c9711ce3`). This round contributes one new finding: the recent job_hunter CSRF GAP-002 patch (`694fc424f`) left 7 multi-step application workflow controller routes unprotected — these are pre-release-b scope. FINDING-2a and FINDING-2c (MISPLACED) remain open. Net: one new MEDIUM finding (FINDING-4), two ongoing open findings carried forward.

---

## GAP-1 — New CSRF MISSING: job_hunter application submission routes (MEDIUM)

**Background:** The GAP-002 CSRF patch (`694fc424f`, 2026-03-01) added `_csrf_token: TRUE` to 6 routes. A regression was found on `addposting` (GET/POST route — adding CSRF to a GET causes 403 on token-less requests) and was reverted (`60f2a7ab8`). However, the following 7 controller POST routes remain without CSRF protection:

**File:** `sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`

| Route | Path | Auth |
|---|---|---|
| `job_hunter.addposting` | `/jobhunter/addposting` | `_user_is_logged_in` |
| `job_hunter.application_submission_step3` | `/jobhunter/application-submission/{id}/identify-auth-path` | `_permission` |
| `job_hunter.application_submission_step3_short` | `/application-submission/{id}/identify-auth-path` | `_permission` |
| `job_hunter.application_submission_step4` | `/jobhunter/application-submission/{id}/create-account` | `_permission` |
| `job_hunter.application_submission_step4_short` | `/application-submission/{id}/create-account` | `_permission` |
| `job_hunter.application_submission_step5` | `/jobhunter/application-submission/{id}/submit-application` | `_permission` |
| `job_hunter.application_submission_step5_short` | `/application-submission/{id}/submit-application` | `_permission` |

**Impact:** A CSRF attack on `application_submission_step5` (`submit-application`) could force a logged-in user to submit a job application on their behalf. Step3 (`identify-auth-path`) and step4 (`create-account`) expose account-linking workflow to CSRF. All require authentication, so severity is MEDIUM (not HIGH).

**`addposting` note:** This is a GET/POST combo route — applying `_csrf_token: TRUE` directly to the route requirement will break GET requests (as shown by the revert). The correct fix is either: (a) split into separate GET and POST routes, or (b) apply CSRF at the form/controller level for the POST handler. This requires dev judgment — flagging as MEDIUM, not prescribing the exact YAML fix.

**Fix for application_submission steps 3/4/5:**
```yaml
# Add to requirements: block of each step route
_csrf_token: 'TRUE'
```

These are browser-form controller routes (not JSON API), so `_csrf_token: 'TRUE'` under `requirements:` is appropriate (not `_csrf_request_header_mode`).

**Verification:**
```bash
python3 -c "
import re
with open('sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml') as f:
    content = f.read()
blocks = re.split(r'\n(?=[a-zA-Z][a-zA-Z0-9_.]+:\s*\n)', content)
issues = []
for block in blocks:
    is_controller = '_controller:' in block and '_form:' not in block
    has_post = bool(re.search(r'methods:.*\[.*POST', block))
    has_csrf = '_csrf_token' in block or '_csrf_request_header_mode' in block
    if has_post and is_controller and not has_csrf:
        route = block.split('\n')[0].rstrip(':')
        issues.append(route)
if issues:
    print('FAIL:', issues)
else:
    print('PASS')
"
```

**Owner:** dev-forseti (fix), sec-analyst-infra (verify)
**ROI:** 10
**Time-bound:** before forseti-release-b production push

---

## GAP-2 — CSRF MISPLACED FINDING-2a/2c: confirmed STILL OPEN (escalated, carried)

**Finding-2a:** forseti `ai_conversation.send_message` — `_csrf_token: TRUE` under `options:` line 115 — STILL OPEN 2026-03-26
**Finding-2c:** forseti `agent_evaluation.send_message` — `_csrf_token: TRUE` under `options:` line 66 — STILL OPEN 2026-03-26

**Status:** CEO escalation written in `20260322-improvement-round-20260322-forseti-release-next.md`. 5th consecutive cycle if unresolved. Patches remain ready — no new action from sec-analyst-infra until CEO delegation is confirmed.

**See:** `sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md`

---

## GAP-3 — GAP-002 patch process: partial coverage creates false confidence

**Description:** The CSRF GAP-002 patch process (`694fc424f`) targeted 6 routes and shipped, but the patch scope was not verified against a full controller-POST audit. The regression (addposting revert) was caught by QA, but the 6 unprotected application_submission routes were not caught before merge. This suggests the patch author scoped by "known missing" rather than running a full sweep.

**SMART follow-through:**
- Owner: sec-analyst-infra (checklist) + dev-forseti (process)
- AC: Any future CSRF patch must include a full `python3` controller-POST sweep (not just manual route inspection) as part of the PR verification, and the result must be pasted into the commit message or PR description.
- Verification: next CSRF-related commit includes sweep output in commit message
- ROI: 7

**In-cycle action:** No instruction update needed — the route access-gating checklist step was already added to sec-analyst-infra seat instructions in `1419b9f00`.

---

## Spot-check verification (2026-03-26)

| Finding | File | Status |
|---|---|---|
| FINDING-2a | forseti `ai_conversation.routing.yml` line 115 | STILL OPEN |
| FINDING-2c | forseti `agent_evaluation.routing.yml` line 66 | STILL OPEN |
| FINDING-4 (new) | forseti `job_hunter.routing.yml` (7 routes) | NEW OPEN — MEDIUM |
| FINDING-1a/1b | forseti `job_hunter` credentials routes | CLOSED (2026-03-01) |

**See:** `sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md` (will be updated this cycle with FINDING-4).
