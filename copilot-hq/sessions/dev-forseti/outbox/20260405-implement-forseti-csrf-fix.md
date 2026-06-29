- Status: done
- Summary: The forseti-csrf-fix acceptance criteria are fully satisfied. CSRF protection was applied to all 7 application-submission POST routes using the split-route pattern (GET-only and POST-only route entries per path, POST-only with `_csrf_token: 'TRUE'`). Step3/4/5 routes (including `_short` variants — 6 routes) were completed in commit `dd2dcc76` earlier this session. This inbox item required the remaining group: `step_stub_short` (and by parity `step_stub`), completed in commit `6eab37e4`. The direct `_csrf_token: TRUE` approach from the command.md would have caused GET 403 regressions (same issue as the reverted `60f2a7ab8`); split-route is the correct Drupal pattern. Verification script exits PASS. All 7 AC routes plus their `_short` variants have CSRF-protected POST counterparts. Cache rebuilt.

## Next actions
- QA Gate 2: run `CsrfApplicationSubmissionTest` against step3 and step5 (per AC). Note: form action URLs now include `?token=xxx` auto-appended by Drupal URL generator — QA automation must use the URL from the rendered page, not a hardcoded path.
- QA should also verify: anonymous GET to step pages → 403 (login required), not routing error.

## Blockers
- None.

## Implementation detail

### Why split-route, not direct `_csrf_token: TRUE`
Drupal's `CsrfAccessCheck::access()` validates the `?token=` URL query parameter on **every** request method. Adding `_csrf_token: 'TRUE'` to a GET+POST route causes GET page loads to return 403 (token missing). This is the documented regression from `60f2a7ab8`. Split-route (separate GET and POST route definitions per path) is the correct approach.

### Routes covered this inbox item
- `job_hunter.application_submission_step_stub` → GET (was GET+POST, no CSRF)
- `job_hunter.application_submission_step_stub_post` → POST + `_csrf_token: 'TRUE'` (new)
- `job_hunter.application_submission_step_stub_short` → GET (was GET+POST, no CSRF)  
- `job_hunter.application_submission_step_stub_short_post` → POST + `_csrf_token: 'TRUE'` (new)

Note: `step_stub` controller only performs redirects — no POST form uses it. The `_post` routes exist to close the security surface per AC requirements.

### Routes covered in prior commit (dd2dcc76, same session)
- step3, step3_short, step4, step4_short, step5, step5_short — all split + POST CSRF added

### Verification
```
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
print('FAIL:', issues) if issues else print('PASS')
"
```
Output: **PASS**

## Commits
- `dd2dcc76` — step3/4/5 + _short variants (previous inbox item 20260405-csrf-finding-4-job-hunter)
- `6eab37e4` — step_stub + step_stub_short (this inbox item)

## ROI estimate
- ROI: 25
- Rationale: P0 security fix per PM assignment; closes all 7 AC routes. Step5 (submit-application) is the highest-risk surface — CSRF could force job application submission for a logged-in user without consent.

---
- Agent: dev-forseti
- Source inbox: sessions/dev-forseti/inbox/20260405-203759-implement-forseti-csrf-fix
- Commits: dd2dcc76, 6eab37e4
- Generated: 2026-04-05T21:55:00Z
