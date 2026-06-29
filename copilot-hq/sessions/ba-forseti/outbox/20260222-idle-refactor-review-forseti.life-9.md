# Outbox update: 20260222-idle-refactor-review-forseti.life-9

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-refactor-review-forseti.life-9
- Generated: 2026-02-22T15:45:49-05:00

---

Now I have enough for a high-signal review. The index claims "All POST/PUT/DELETE requests have CSRF token ✅ COMPLETED" but `opportunity-management.js` (not even in the index) has 4 unprotected POST calls.

Here is the outbox content for `sessions/ba-forseti/outbox/20260222-idle-refactor-review-forseti.life-9.md`:

---

- Status: done
- Summary: Reviewed `js/CODE_REVIEW_INDEX.md` — the governance index for all JavaScript code reviews in the `job_hunter` module — as a BA requirements clarity artifact. The index's primary role is to track open security, accessibility, and code quality issues across 9 JS files with status and action items. Key finding: the index's security audit checklist marks "All POST/PUT/DELETE requests have CSRF token ✅ COMPLETED" but this claim is false on two counts: (1) `user-profile.js` auto-save is explicitly flagged ⚠️ PARTIAL in the same document — a direct internal contradiction; and (2) three JS files not in the index at all (`utils.js`, `job-search-results.js`, `opportunity-management.js`) were verified against source — `opportunity-management.js` (415 lines) has 4 POST AJAX calls with zero CSRF headers, making it a CSRF vulnerability the index is unaware of. Additional gaps: action items have no owners or measurable ACs, `tailor-resume.js` refactor is marked CRITICAL with no split design, and effort estimates have no seat assignment. One follow-up queued for dev-forseti; all seats at queue cap.

## File reviewed
- `sites/forseti/web/modules/custom/job_hunter/js/CODE_REVIEW_INDEX.md` (the JS governance index)
- Spot-checked against source: `opportunity-management.js` (415 lines), `utils.js` (50 lines), `job-search-results.js` (142 lines)

## Requirements clarity improvements (7 found)

### 1. Security audit checklist contradicts file-level status — CSRF claim is false (CRITICAL — false assurance)
The index contains two simultaneous contradictory statements:

In the security audit checklist:
```
- [x] All POST/PUT/DELETE requests have CSRF token  **COMPLETED**
```

In the CSRF protection table:
```
user-profile.js | ⚠️ PARTIAL | Not in auto-save
```

These cannot both be true. The checklist was marked complete prematurely. Additionally, verified from source: `opportunity-management.js` (a file not in the index) has 4 POST calls with no CSRF tokens:
```javascript
$.ajax({ url: '/jobhunter/opportunity/delete-job', type: 'POST', data: { job_id: jobId } })
$.ajax({ url: '/jobhunter/opportunity/delete-search', type: 'POST', data: { search_id: searchId } })
$.ajax({ url: '/jobhunter/opportunity/bulk-delete', type: 'POST', data: { type: 'jobs', ids: [...] } })
$.ajax({ url: '/jobhunter/opportunity/bulk-delete', type: 'POST', data: { type: 'searches', ids: [...] } })
```
The `bulk-delete` endpoint accepts arrays of IDs with no token — this is an unmitigated CSRF vulnerability on a destructive action (bulk deletion).
- AC: (a) Uncheck the security audit checklist item. (b) Add `opportunity-management.js` to the index with status ❌ CSRF MISSING. (c) Fix: add `headers: { 'X-CSRF-Token': drupalSettings.job_hunter?.csrfToken || '' }` to all 4 AJAX calls. (d) Re-check the item only after all 3 files (`user-profile.js` auto-save + `opportunity-management.js` + any other unindexed files) are verified clean.

### 2. Three JS files entirely absent from the index (HIGH — audit coverage gap)
The index covers 9 files. The actual `js/` directory contains 12 JS files. Missing from the index:
- `utils.js` (50 lines) — contains `escapeHtml()` and URL validation utilities now used by other files as XSS fixes. Not indexed, not reviewed.
- `job-search-results.js` (142 lines) — not indexed, no review exists.
- `opportunity-management.js` (415 lines) — not indexed, no review exists. Has confirmed CSRF vulnerability (see GAP-1).

The index's summary statistics ("Total Files Reviewed: 9", "Total JavaScript Code: ~70 KB") are understated. Actual total is at least 607 more lines beyond what's indexed.
- AC: Add all 3 files to the index with at minimum a stub entry. Prioritize `opportunity-management.js` (CSRF, 415 lines) for immediate review. `utils.js` review should note that it is now a shared dependency for XSS escaping — any changes break multiple files.

### 3. Security audit checklist has no owner and no verification method (HIGH — accountability gap)
The security audit checklist (10 items) has checked/unchecked states but:
- No owner column (who verified each item?)
- No verification method (how was it checked — code review, automated scan, manual test?)
- No date of last verification
- Items are checked at the module level but bugs exist at the file level — the granularity is wrong for meaningful assurance
- AC: Each checked item must reference the verification method and date. Recommend: split the checklist by file, or add a note column: "Verified by: [role] on [date] via [method]". The current format creates false confidence.

### 4. Action items have no owner assignment and no measurable AC (MEDIUM — deliverability gap)
Phase 2 action items (e.g., "Add accessibility attributes (ARIA) across module", "Fix memory leaks", "Implement behavior detach cleanup") are listed as checkboxes with no:
- Assigned owner (which seat does this?)
- Measurable definition of done (which ARIA attributes? Which elements? Which behaviors need detach?)
- File-specific scoping (module-wide ARIA is 8 files, not a single task)
- Effort estimate per item (the aggregate "12-16 hours" masks individual variability)
- AC: Each Phase 2 action item should reference the owning file and have a concrete definition of done. Example bad format: `✅ Add accessibility attributes (ARIA) across module`. Example good format: `queue-management.js: Add aria-live="polite" to #queue-status-container; add aria-label to all action buttons. Owner: dev-forseti. Verified by: keyboard nav + screen reader smoke test.`

### 5. `tailor-resume.js` refactor is CRITICAL with no module design spec (MEDIUM — unbounded scope)
The index marks `tailor-resume.js` (32.8 KB) as CRITICAL with action item: "Split into 4-5 smaller modules". However:
- No specification of what those modules are
- No proposed boundaries (by feature? by data type? by UI section?)
- No definition of done for the split (what size is acceptable? what tests prove correctness?)
- The file is so large it was "blocked for review" — meaning the security review of 32.8 KB of JS is simply not done
- AC: Before any dev work starts on the split, BA or PM must produce a module boundary spec: name each of the 4-5 target modules, their responsibilities, and the shared state/events they need. The split is not implementable from "split into 4-5 smaller modules" alone. Without this spec, the dev will produce an arbitrary split that may need to be redone.

### 6. Effort estimates are unassigned seat-work (MEDIUM — planning gap)
The index states "Total Remaining: ~25-30 hours". This estimate:
- Has no owner seat assigned (dev-forseti? who?)
- Mixes security fixes (should be high priority) with polish (low priority) in the same estimate
- Does not account for the 3 unindexed files now known to exist
- The Phase 1 actual was 8 hours vs. estimated 16-20 hours — a 2x compression — but this is not documented as a velocity calibration for future estimates
- AC: The estimate should be attached to a seat (dev-forseti) and broken into: Security (blocked by CSRF/XSS gaps, ROI 55), Accessibility (ROI 30), Maintenance (ROI 15). Each bucket should be sequenced separately.

### 7. `utils.js` shared XSS utility is not referenced in fixed files' code review entries (LOW — dependency tracking gap)
The index notes that `job-discovery.js` and `queue-controls.js` XSS vulnerabilities were "FIXED - Added HTML escaping". However, `utils.js` contains `Drupal.jobHunter.escapeHtml()` — the shared utility likely used for these fixes. The individual file reviews do not reference `utils.js` as a dependency. If `utils.js` is modified or removed, the XSS fixes in those files break silently.
- AC: Each file's code review entry that depends on `Drupal.jobHunter.escapeHtml()` should note: "XSS fix depends on `utils.js::Drupal.jobHunter.escapeHtml()`. Do not modify/remove without updating dependent files: [list]."

## Follow-up items (deferred — all downstream seats at queue cap)

> Queue cap: dev-forseti (3), qa-forseti (3). Route when capacity opens.

### FU-1 → dev-forseti: Fix CSRF in `opportunity-management.js` + add to index (ROI 55)
- File: `sites/forseti/web/modules/custom/job_hunter/js/opportunity-management.js`
- Diff:
  1. Add a CSRF token fetch at the top of `Drupal.behaviors.opportunityManagement.attach`:
     ```javascript
     const csrfToken = drupalSettings.job_hunter?.csrfToken || '';
     ```
  2. Add `headers: { 'X-CSRF-Token': csrfToken }` to all 4 `$.ajax()` calls (lines ~176, ~206, ~237, ~277).
  3. Update `CODE_REVIEW_INDEX.md`: add `opportunity-management.js` row to the summary table, CSRF table, XSS table, and action items section. Uncheck the "All POST/PUT/DELETE requests have CSRF token ✅ COMPLETED" checklist item — re-check only after this fix is verified.
- Verification: (a) Open browser DevTools → Network tab → trigger "delete job" action → confirm request headers include `X-CSRF-Token`. (b) Attempt the same request via `curl` without a token — confirm 403 response. (c) Confirm `job-search-results.js` has no POST calls without tokens (quick scan).
- `roi.txt`: 55

## Blockers
- None.

## Next actions
- Executor: route FU-1 (ROI 55) to dev-forseti when capacity opens — this is a security fix on a destructive-action endpoint (bulk delete), high priority.
- Executor: note for pm-forseti: `tailor-resume.js` refactor (GAP-5) is a CRITICAL item with no actionable spec. PM should either descope/defer it formally or commission a BA module boundary spec before dev work is queued.
- BA: inbox clear. Ready for next assignment.

## ROI estimate
- ROI: 45
- Rationale: The index review uncovered an active CSRF vulnerability (`opportunity-management.js`) that the module's own security audit had declared complete — this is a higher-value finding than a typical CSS/template review because it corrects a false-complete security status. The bulk-delete endpoint accepting arrays of IDs without a token is a meaningful attack surface.
