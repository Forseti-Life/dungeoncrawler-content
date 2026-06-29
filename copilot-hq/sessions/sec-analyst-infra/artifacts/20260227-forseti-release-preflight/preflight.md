# Security Pre-Flight Checklist: forseti release (2026-02-27)

Analyst: sec-analyst-infra (WRAITH)
Date: 2026-02-27
Scope: Changed files in unmerged commits on `main` vs `origin/main`
Branch HEAD: `fe467cbd6` (2 commits ahead of origin/main)
Changed files scoped:
- `sites/forseti/web/modules/custom/job_hunter/job_hunter.services.yml`
- `sites/forseti/web/modules/custom/job_hunter/playwright/apply.js`
- `sites/forseti/web/modules/custom/job_hunter/playwright/platforms/greenhouse.js`
- `sites/forseti/web/modules/custom/job_hunter/playwright/platforms/lever.js`
- `sites/forseti/web/modules/custom/job_hunter/src/Plugin/Block/JobHunterNavigationBlock.php`
- `sites/forseti/web/modules/custom/job_hunter/src/Service/ApplicationSubmissionService.php`

Additional surface audited (new routes introduced in recent release history):
- `job_hunter.credentials_delete` and `job_hunter.credentials_test` (added in Phase 2)

---

## Checklist: Access Control (Routes/Permissions)

| Surface | Status | Notes |
|---|---|---|
| Previously CSRF-disabled routes (save_job, job_discovery_search_ajax, tailor_resume_ajax, add_skill_to_profile_ajax, refresh_skills_gap_ajax) | PASS | All now use `_csrf_request_header_mode: 'TRUE'` per prior security fix (87a16072). |
| `job_hunter.credentials` GET `/jobhunter/settings/credentials` | PASS | Read-only, `access job hunter` permission. |
| `job_hunter.credentials_delete` POST `/jobhunter/settings/credentials/{credential_id}/delete` | FLAG | No `_csrf_token`. Permission is `access job hunter` (all authenticated users), not admin-only. Any authenticated user visiting an attacker-controlled page can have their ATS credentials deleted. Higher risk than admin-only CSRF because attack surface is wider. |
| `job_hunter.credentials_test` POST `/jobhunter/settings/credentials/{credential_id}/test` | FLAG | No `_csrf_token`. Same permission class. A CSRF attack could trigger credential testing against attacker-controlled endpoints (potential SSRF vector if test endpoint is attacker-supplied). |
| Queue management POST routes (27 total) | PASS (scope-limited) | Pre-existing routes; not changed in this release. `administer job application automation` permission is admin-equivalent. Not in scope for this diff-based review. |

---

## Checklist: Playwright Bridge / Automation Surface

| Surface | Status | Notes |
|---|---|---|
| `apply.js` payload file handling | PASS | Payload file is read and immediately deleted (`fs.unlinkSync`) before processing. Credentials do not persist on disk. |
| `apply.js` subprocess invocation | PASS (pattern) | Called as `node apply.js --payload-file=/tmp/jh_apply_XXXX`. Temp file path appears to use a generated name. |
| `/tmp` payload file race window | LOW | Brief window between write and unlink. In a shared server environment, `/tmp` files are world-readable by default. If `/tmp` permissions are not restricted, another local process could read credentials during the window. Hypothetical — assumes shared hosting or multi-user server. |
| Playwright platform handlers (greenhouse.js, lever.js) | NOT REVIEWED | Platform-specific automation logic not in scope for routing/access review. Recommend a follow-on controller-level review. |

---

## Checklist: Storage Safety

| Surface | Status | Notes |
|---|---|---|
| DCC-0331 (hardcoded credentials in forseti settings.php) | UNVERIFIED | Remediation status for this release is unknown. pm-infra to confirm. |
| Credential storage for ATS credentials (new) | NOT REVIEWED | How `CredentialManagementService` stores ATS credentials (DB column type, encryption at rest) is not reviewable from routing/services.yml alone. Recommend controller-level review. |

---

## Checklist: Input Validation

| Surface | Status | Notes |
|---|---|---|
| `{credential_id}` path parameter | PASS | `credential_id: '\d+'` pattern constraint enforced in routing. |

---

## Summary

**FLAGs requiring action before Gate 2:**

### FLAG-1 (High): Missing CSRF on credentials_delete
- **File:** `sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml` line 915
- **Route:** `job_hunter.credentials_delete` POST `/jobhunter/settings/credentials/{credential_id}/delete`
- **Impact:** Any authenticated job_hunter user visiting an attacker-controlled page can have their ATS credentials silently deleted — no user interaction beyond page load.
- **Likelihood:** Medium (any authenticated user is a target, not just admins; social engineering bar is lower).
- **Severity:** HIGH — credential data loss + wider attack surface than admin-only.
- **Mitigation:** Add `_csrf_token: 'TRUE'` to requirements block.
- **Patch:**
```diff
   requirements:
     _permission: 'access job hunter'
+    _csrf_token: 'TRUE'
     credential_id: '\d+'
```
- **Verification:** POST `/jobhunter/settings/credentials/1/delete` without valid CSRF token while authenticated → expect 403.

### FLAG-2 (High): Missing CSRF on credentials_test + potential SSRF
- **File:** `sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml` line 924
- **Route:** `job_hunter.credentials_test` POST `/jobhunter/settings/credentials/{credential_id}/test`
- **Impact:** CSRF can force the server to test a credential against an attacker-chosen endpoint (if `credential_id` resolves to attacker-modified data), creating a potential SSRF vector. At minimum, leaks whether credentials are valid.
- **Likelihood:** Medium (same reasoning as FLAG-1).
- **Severity:** HIGH — SSRF potential elevates this above simple data loss.
- **Mitigation:** Add `_csrf_token: 'TRUE'` to requirements block. Separately, verify `testCredential` controller validates the target endpoint is a known/allowlisted ATS URL.
- **Patch:**
```diff
   requirements:
     _permission: 'access job hunter'
+    _csrf_token: 'TRUE'
     credential_id: '\d+'
```
- **Verification:** POST `/jobhunter/settings/credentials/1/test` without valid CSRF token while authenticated → expect 403.

### LOW: /tmp payload file race window (Playwright bridge)
- Hypothetical only. Assumed: server is single-user or `/tmp` is mode 1777 (sticky). Recommend verifying `/tmp` permissions or using `mktemp` with a mode-600 file. Not a Gate 2 blocker.

### UNVERIFIED: DCC-0331
- Prior Critical finding. pm-infra to confirm remediation status.

---

## Overall pre-flight result: FLAG (2 HIGH findings, 1 LOW hypothetical, 1 unverified prior)
Recommend: Gate 2 HOLD until FLAG-1 and FLAG-2 are patched and verified. pm-infra to confirm DCC-0331.
