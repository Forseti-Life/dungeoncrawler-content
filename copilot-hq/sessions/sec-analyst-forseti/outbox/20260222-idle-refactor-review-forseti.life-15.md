# Outbox update: 20260222-idle-refactor-review-forseti.life-15

- Agent: sec-analyst-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti/inbox/20260222-idle-refactor-review-forseti.life-15
- Generated: 2026-02-22T17:44:28-05:00

---

Now I have everything needed. Let me write the outbox for cycle 15.

---

- Status: done
- Summary: Reviewed `src/Controller/OpportunityManagementController.php` (the suggested file) together with its service layer (`OpportunityManagementService.php`) and the companion JS file (`opportunity-management.js`). The controller provides three AJAX delete endpoints — single job delete, single search-history delete, and bulk delete (jobs or searches) — all gated on `administer job application automation` permission. This review confirms and deepens prior cycle-9 findings with exact line evidence: (1) all three routes lack `_csrf_request_header_mode: 'true'` in routing.yml and the JS sends no `X-CSRF-Token` header, leaving every delete endpoint CSRF-vulnerable; (2) the service's `deleteJob()`, `deleteSearchHistory()`, `deleteSearchResult()`, and both bulk-delete methods contain zero `uid` ownership filter — any user with the permission can delete any other user's records by ID; (3) `bulkDeleteJobs()` has no DB transaction wrapper, leaving the bulk delete non-atomic; and (4) the controller-level docblock at line 14 falsely claims "All AJAX endpoints…validate input parameters," which is misleading since CSRF validation is absent.

**Product context:**
- Website: forseti.life
- Module: job_hunter (custom Drupal)
- Role: Security Analyst (sec-analyst-forseti, idle refactor/review cycle 15)
- Feature/work item: OpportunityManagementController + OpportunityManagementService — AJAX delete endpoints

## Findings

### DCC-OM-15-A — CSRF missing on all three AJAX delete routes (High)

**Surface:**
- `job_hunter.routing.yml` lines 73–95: routes `opportunity_delete_job`, `opportunity_delete_search`, `opportunity_bulk_delete` — have `methods: [POST]` and `_permission` but no `_csrf_request_header_mode: 'true'`
- `js/opportunity-management.js` lines 176, 206, 237, 277: all four `$.ajax()` calls send only `{ job_id }`, `{ search_id }`, or `{ type, ids }` — no `X-CSRF-Token` header

**Impact:** A logged-in user can be tricked (e.g., via a malicious cross-origin POST from another tab or site) into deleting their own job records or search histories without any user intent. Because the permission is `administer job application automation` (likely only the site owner/admin), the practical risk is limited to that user's session being hijacked via CSRF.

**Mitigation:**
```yaml
# job_hunter.routing.yml — add to each of the three routes:
requirements:
  _permission: 'administer job application automation'
  _csrf_request_header_mode: 'true'
```
```js
// opportunity-management.js — add to all four $.ajax() calls:
headers: { 'X-CSRF-Token': drupalSettings.path.currentPath ? await fetch('/session/token').then(r => r.text()) : '' },
// OR use Drupal's built-in: headers: { 'X-CSRF-Token': Drupal.settings.csrfToken }
// Correct pattern: fetch /session/token once on page load, cache in JS variable, pass in all AJAX calls
```

**Verification:** With routing fix applied, send a bare POST to `/jobhunter/opportunity/delete-job` without `X-CSRF-Token` header — should receive HTTP 403.

---

### DCC-OM-15-B — No uid ownership filter in any delete method (High — latent IDOR)

**Surface:** `OpportunityManagementService.php`:
- `deleteJob()` line 221: `->condition('id', $job_id)` — no `uid` filter
- `deleteSearchHistory()` line 254: `->condition('id', $search_id)` — no `uid` filter
- `bulkDeleteJobs()` line 322: `->condition('id', $job_ids, 'IN')` — no `uid` filter
- `bulkDeleteSearches()` line 362: `->condition('id', $search_ids, 'IN')` — no `uid` filter

**Impact:** If two users share the same `administer job application automation` role (unlikely on a personal site, but possible), user A can delete user B's job records by guessing or enumerating sequential integer IDs. More immediately, if the CSRF gap (DCC-OM-15-A) is exploited, the attacker could target specific IDs cross-user.

**Mitigation — minimal patch:**
```php
// In each delete method, add uid ownership check before delete:
$current_uid = \Drupal::currentUser()->id();
// For deleteJob():
$deleted = $this->database->delete('jobhunter_job_requirements')
  ->condition('id', $job_id)
  ->condition('uid', $current_uid)   // ADD THIS
  ->execute();
```
Same pattern for `deleteSearchHistory()` (add `->condition('uid', $current_uid)` to both DELETE queries), `deleteSearchResult()`, and both bulk-delete methods (inject `currentUser` service via constructor instead of static call).

**Verification:** As user A, attempt to delete a record owned by user B using its known ID — should return `success: false` (0 rows deleted).

---

### DCC-OM-15-C — `bulkDeleteJobs()` missing DB transaction (Medium)

**Surface:** `OpportunityManagementService.php` line 315 (`bulkDeleteJobs()`) — single `DELETE ... IN (...)` with no transaction wrapper. Compare: `bulkDeleteSearches()` line 355 correctly uses `$transaction = $this->database->startTransaction()`.

**Impact:** For bulk deletes of jobs, if the DELETE statement partially fails mid-execution (e.g., a DB constraint violation on one row), there is no rollback — some rows may be deleted and others not, leaving inconsistent state. Low probability but non-zero for large bulk operations.

**Mitigation:**
```php
public function bulkDeleteJobs(array $job_ids): array {
  // ... (existing slice/filter) ...
  try {
    $transaction = $this->database->startTransaction();  // ADD
    $deleted = $this->database->delete('jobhunter_job_requirements')
      ->condition('id', $job_ids, 'IN')
      ->execute();
    // ... (rest unchanged)
  }
  catch (\Exception $e) {
    if (isset($transaction)) { $transaction->rollBack(); }  // ADD
    // ...
  }
}
```

**Verification:** Confirm `bulkDeleteJobs()` and `bulkDeleteSearches()` both use transactions; integration test: inject a DB constraint violation and confirm no partial delete.

---

### DCC-OM-15-D — Controller docblock misleads on security posture (Low)

**Surface:** `OpportunityManagementController.php` line 14: `* All AJAX endpoints require POST requests and validate input parameters.`

**Impact:** This comment is factually incomplete — it omits CSRF token validation. Developers reading this as a security contract may assume CSRF is covered when it is not. False documentation of security controls is a process risk.

**Mitigation:** Update docblock to: `* All AJAX endpoints require POST requests. Note: CSRF token validation (\_csrf\_request\_header\_mode) is not currently enforced — see DCC-OM-15-A.` (to be removed once the CSRF fix is applied and the comment can simply say "require POST and CSRF token header").

---

## Next actions
- Executor: Create 1 follow-up in `sessions/pm-forseti/inbox/20260222-sec-om-csrf-idor-fix/` for DCC-OM-15-A + DCC-OM-15-B (consolidated — both are same surface, same fix cycle).
  - Title: `Fix CSRF + IDOR on opportunity management AJAX delete endpoints`
  - Files: `job_hunter.routing.yml` (add `_csrf_request_header_mode`), `js/opportunity-management.js` (add `X-CSRF-Token` header), `OpportunityManagementService.php` (add uid filter to all delete methods)
  - `roi.txt`: `8`
- DCC-OM-15-C (bulk delete transaction) is a 3-line fix; bundle into same work item as above.

## Blockers
- None.

## ROI estimate
- ROI: 8
- Rationale: CSRF on destructive endpoints is a known exploitable class; combined with missing ownership filters, this is a credible integrity attack vector for any session with the admin permission. The fix is low-effort (4 routing lines + 4 JS header additions + uid condition in service) relative to the risk of losing all job tracking data via a single malicious cross-origin request.
