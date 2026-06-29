# Security Findings — Forseti job_hunter Phase 2 (2026-02-28)
# Scope: forseti.life — job_hunter credential management + Playwright bridge

---

## FINDING-1 — MEDIUM: Missing CSRF protection on credential management POST routes

**Surface**: `job_hunter.routing.yml` — routes `job_hunter.credentials_delete` and `job_hunter.credentials_test`

**Affected lines**:
- Line 915: `job_hunter.credentials_delete` — POST `/jobhunter/settings/credentials/{credential_id}/delete`
- Line 924: `job_hunter.credentials_test` — POST `/jobhunter/settings/credentials/{credential_id}/test`

**Problem**: Both routes have `methods: [POST]` and `_permission: 'access job hunter'` but **no `_csrf_token: 'TRUE'`**. These are AJAX controller routes (not Drupal forms), so they do NOT receive automatic form-token CSRF protection. A cross-origin POST from an attacker-controlled page can trigger these endpoints on a logged-in victim.

**Impact**: An attacker who tricks a logged-in user into visiting a page with a hidden form can:
- Delete the user's stored ATS credentials (disables Phase 2 automation for that user)
- Queue spurious credential tests (minor noise/log spam)

Cross-user credential deletion is blocked by the ownership check in `CredentialController::deleteCredential()` — the attack is limited to the victim's own credentials.

**Likelihood**: Low. Requires victim to be logged in to forseti.life and visit attacker-controlled page simultaneously.

**Severity**: Medium (tangible user data loss for active job-seeker; credentials can be re-entered but automation is disrupted).

**Mitigation patch** (ready-to-apply — routing YAML only):

```yaml
# job_hunter.routing.yml — patch for credentials_delete
job_hunter.credentials_delete:
  path: '/jobhunter/settings/credentials/{credential_id}/delete'
  defaults:
    _controller: '\Drupal\job_hunter\Controller\CredentialController::deleteCredential'
  methods: [POST]
  requirements:
    _permission: 'access job hunter'
    _csrf_token: 'TRUE'
    credential_id: '\d+'

# job_hunter.routing.yml — patch for credentials_test
job_hunter.credentials_test:
  path: '/jobhunter/settings/credentials/{credential_id}/test'
  defaults:
    _controller: '\Drupal\job_hunter\Controller\CredentialController::testCredential'
  methods: [POST]
  requirements:
    _permission: 'access job hunter'
    _csrf_token: 'TRUE'
    credential_id: '\d+'
```

**Frontend note**: After adding `_csrf_token: 'TRUE'`, the JS callers must append `?token=<csrf_token>` to the POST URL. In Drupal, generate the per-route token with `\Drupal::csrfToken()->get('route:<route_name>')` and pass it in the Twig template as a data attribute or drupalSettings entry.

**Verification**: After patch, send a POST to `/jobhunter/settings/credentials/1/delete` without a valid CSRF token — Drupal should return 403. With valid token, confirm deletion succeeds.

---

## FINDING-2 — LOW: No exception guard on Playwright credential tempfile

**Surface**: `BrowserAutomationService::runPlaywrightBridge()` — lines ~510–580

**Problem**: Credentials (decrypted username/password or token) are written to a temp file (`chmod 0600`) and cleaned up with `@unlink($tmp_file)`. The unlink call is in the main execution path and timeout path, but there is no `try/finally` block. If an unexpected exception fires between `file_put_contents` and `proc_close`, the credential temp file persists on disk at `sys_get_temp_dir()`.

**Impact**: Decrypted ATS credentials persist in the system temp directory until the next system reboot or temp purge. File is 0600 (www-data-owned), so exploitability requires local file access — Low exploitability in practice.

**Mitigation**: Wrap the `proc_open` block with `try/finally { @unlink($tmp_file); }` to guarantee cleanup.

**Verification**: Add a unit test or review confirming `@unlink($tmp_file)` runs in all code paths.

---

## FINDING-3 — LOW: Playwright stderr logged to watchdog (potential credential leak if apply.js logs credential fields)

**Surface**: `BrowserAutomationService::runPlaywrightBridge()` — logger call with `'@err' => substr($stderr, 0, 500)`

**Problem**: On Playwright failure, up to 500 chars of `apply.js` stderr are persisted to Drupal watchdog. If `apply.js` were to output credential values (username, password, token) during a debug/error path, they would appear in the DB watchdog table and potentially in log exports.

**Likelihood**: Hypothetical — depends on `apply.js` debug output. Flag for review.

**Mitigation**: 
1. Audit `apply.js` to confirm it never logs `username`, `password`, or `token` fields to stderr.
2. Add a comment to `runPlaywrightBridge()` documenting this constraint so future maintainers know to avoid it.

**Verification**: Review all `console.error` / `process.stderr.write` calls in `apply.js` and confirm no credential fields are referenced.

---

## Summary table

| # | Severity | Finding | Mitigation ready? |
|---|---|---|---|
| 1 | Medium | Missing `_csrf_token: 'TRUE'` on credential delete/test routes | Yes — patch in finding |
| 2 | Low | No try/finally on Playwright credential tempfile | Yes — try/finally wrap |
| 3 | Low | Playwright stderr potentially logs credentials (hypothetical) | Audit apply.js |

---

## Process improvement: Automated CSRF route sweep script

**Proposal**: Add `scripts/csrf-route-sweep.py` to the infra automation layer.

**SMART outcome**:
- **Specific**: Python script that parses any Drupal `*.routing.yml` file, identifies POST/PATCH/PUT/DELETE routes, and flags those missing `_csrf_token: 'TRUE'`. Callable as `python3 scripts/csrf-route-sweep.py <path/to/routing.yml>` and exits 1 when flags found.
- **Measurable**: Zero CSRF routing gaps reach Gate 2 in future releases; script result included in pre-flight checklist (PASS/FLAG per route).
- **Achievable**: ~60 LOC Python using PyYAML. No new dependencies beyond `python3` + `pyyaml` (already available per infra environment).
- **Relevant**: This exact pattern (POST route without CSRF token) has now appeared in two consecutive release cycles (FLAG-2 prior cycle, FINDING-1 this cycle). Automating detection eliminates the manual discovery step.
- **Time-bound**: Target: available before `20260228-forseti-release-next` Gate 1.

**Delegation note**: Script authorship belongs to `dev-infra` (scripts/ ownership). Escalating with this finding as the concrete trigger.

---
Generated: 2026-02-28T14:24:25Z
Analyst: sec-analyst-infra
