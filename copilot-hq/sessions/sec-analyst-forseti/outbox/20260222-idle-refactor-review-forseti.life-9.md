# Outbox update: 20260222-idle-refactor-review-forseti.life-9

- Agent: sec-analyst-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti/inbox/20260222-idle-refactor-review-forseti.life-9
- Generated: 2026-02-22T17:19:03-05:00

---

Now I have a complete picture. Let me also check whether CSRF token is required on these routes and whether the JS sends it.

I now have the complete picture. The suggested file (`QueueWorkerBaseTrait.php`) was already reviewed in cycle 10 (processed immediately prior). I pivoted to `OpportunityManagementService.php` — a higher-value unreviewed surface. Here is the outbox:

---

- Status: done
- Summary: Cycle 9 idle security review: the suggested file `QueueWorkerBaseTrait.php` was already reviewed this session (cycle 10); pivoted to `src/Service/OpportunityManagementService.php` and `src/Controller/OpportunityManagementController.php`, which are the service/controller layer for the opportunity management delete endpoints. Key findings: (1) The three AJAX delete routes (`/jobhunter/opportunity/delete-job`, `/delete-search`, `/bulk-delete`) require `POST` and `administer job application automation` permission — correctly gate-kept and POST-only. However, none of the routes require `_csrf_token` or `_csrf_request_header_mode`, and the `opportunity-management.js` AJAX calls send **no `X-CSRF-Token` header**. Any authenticated admin who visits a malicious page could have their jobs or search history deleted silently via cross-site POST. (2) `OpportunityManagementService::deleteJob()` and related methods have **no ownership check** — they delete any job by ID without verifying it belongs to the requesting user's uid. Since these are admin-only routes this is partially mitigated by the permission requirement, but if the permission is ever granted to non-admin roles, IDOR exposure is immediate. (3) `bulkDeleteAjax()` receives `$ids` directly from `$request->request->get('ids', [])` — if `ids` is a non-array value, the `is_array()` check at line 185 rejects it correctly, but there is no type-coercion guard before the `count()` call, meaning a crafted string value could skip the array check and reach `bulkDeleteJobs()` which calls `array_filter($job_ids, 'is_numeric')` — safe in practice but fragile.

**Product context:**
- Website: forseti.life
- Module: job_hunter (custom Drupal)
- Role: Security Analyst (sec-analyst-forseti, idle refactor/review cycle 9)
- Feature/work item: Opportunity management AJAX delete endpoints — `OpportunityManagementService.php` + `OpportunityManagementController.php`

## Findings

### DCC-OM-09-A — AJAX delete routes missing CSRF protection (High)
- **Surface:** `job_hunter.routing.yml` lines 73–95; `js/opportunity-management.js` lines 176–278
- **Evidence:** Routes define `methods: [POST]` and `_permission: 'administer job application automation'` but no `_csrf_token: 'TRUE'` or `_csrf_request_header_mode: 'true'`. JS AJAX calls send no `X-CSRF-Token` header.
- **Impact:** An authenticated admin who visits a malicious page can have jobs, search history, and bulk data silently deleted via cross-site POST (CSRF). The same pattern as DCC-CC-01 (company delete), now confirmed in the opportunity management controller.
- **Likelihood:** Medium — requires admin victim to visit attacker-controlled page while authenticated.
- **Mitigation option 1 (route-level):** Add `_csrf_request_header_mode: 'true'` to each of the three routes in `job_hunter.routing.yml`.
- **Mitigation option 2 (JS-level):** Add `X-CSRF-Token` header to all three AJAX call sites using `drupalSettings.ajaxPageState` / `$.ajaxSetup`:
  ```js
  // At initialization, before any AJAX calls:
  $.ajaxSetup({
    headers: { 'X-CSRF-Token': drupalSettings.job_hunter?.csrf_token ?? '' }
  });
  ```
  Both options needed together for defense in depth.
- **Verification:** Using browser DevTools, intercept a delete-job POST from a different origin (or missing header) — confirm 403 response. Confirm normal delete still works from the correct origin with header.

### DCC-OM-09-B — No ownership validation in delete service methods (Medium — IDOR latent risk)
- **Surface:** `OpportunityManagementService::deleteJob()` line 214, `deleteSearchHistory()` line 244, `bulkDeleteJobs()` line 315
- **Evidence:** All three methods delete by ID only — no `->condition('uid', $uid)` filter. Any caller with the service can delete any job/search regardless of owner.
- **Impact:** Currently safe — all callers require `administer job application automation` (admin-only). If permission is ever granted to authenticated users or the routes are reused in a user-facing context, IDOR exposure is immediate: user A can delete user B's jobs.
- **Mitigation:** Add optional `$uid` parameter to service methods and apply it as a condition when provided:
  ```php
  public function deleteJob(int $job_id, ?int $uid = NULL): bool {
    $query = $this->database->delete('jobhunter_job_requirements')
      ->condition('id', $job_id);
    if ($uid !== NULL) {
      $query->condition('uid', $uid);
    }
    $deleted = $query->execute();
    // ...
  ```
- **Verification:** Call `deleteJob($other_users_job_id, $current_uid)` — confirm 0 rows deleted. Call with correct uid — confirm deletion succeeds.

### DCC-OM-09-C — `sources` filter uses raw LIKE pattern (Low)
- **Surface:** `OpportunityManagementService::getSearchHistory()` line 138
- **Evidence:** `$query->condition('sh.sources', '%' . $this->database->escapeLike($filters['sources']) . '%', 'LIKE')` — `escapeLike()` is called, which is correct. However, `$filters['sources']` comes from `$request->query->get('sources', '')` in the controller (line 89) with no length limit. A very long sources string causes a large LIKE pattern query.
- **Impact:** Performance / minor DoS via crafted query string. Not a security issue given admin-only access.
- **Mitigation:** Add `substr($filters['sources'], 0, 255)` cap in `getSearchHistory()` or in the controller before passing to service.

## Next actions
- Executor: Create 1 follow-up inbox item in `sessions/pm-forseti/inbox/` for DCC-OM-09-A (CSRF on opportunity management routes), ROI 8. Bundle with DCC-CC-01 (company delete CSRF) — they are the same fix pattern and should be implemented together.
  - Title: `Fix missing CSRF protection on opportunity management AJAX delete routes`
  - Files: `job_hunter.routing.yml` (lines 73–95), `js/opportunity-management.js` (lines 176–278)
  - Patch: Add `_csrf_request_header_mode: 'true'` to three routes + add `X-CSRF-Token` header to AJAX calls via `$.ajaxSetup`
  - Verification: POST to delete endpoint without token → 403; with token → success
  - `roi.txt`: `8`
- DCC-OM-09-B (ownership validation) should be bundled with the existing IDOR/access control work item.

## Blockers
- None.

## ROI estimate
- ROI: 8
- Rationale: The CSRF gap on AJAX delete routes is the same attack class as DCC-CC-01 (already queued) and can be fixed in the same PR. An authenticated admin who visits a malicious page loses all saved jobs and search history silently. The ownership gap is a latent IDOR that should be addressed before permission model changes.
