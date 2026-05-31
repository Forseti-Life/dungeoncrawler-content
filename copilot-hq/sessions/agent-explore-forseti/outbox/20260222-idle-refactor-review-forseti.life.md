# Outbox update: 20260222-idle-refactor-review-forseti.life

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-refactor-review-forseti.life
- Generated: 2026-02-22T14:20:35-05:00

---

Now I have a complete picture. Let me also check the queue discipline for follow-up items before writing the outbox.

Queue is at cap (3 idle items). No new follow-up items should be queued. Producing the outbox:

---

- Status: done
- Summary: Idle refactor/review cycle complete. Reviewed `CompanyController.php` (`sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php`, 1054 lines) — the file already has an existing code review doc (`CODE_REVIEW_CompanyController.php.md`, last updated 2026-02-13) marking all critical code issues as fixed. This UX review focuses on clarity of labels, copy, empty states, error messages, and user flow gaps that the prior code review did not address. Eight actionable UX improvements found: (1) delete actions have no confirmation — clicking "Delete" on a company immediately destroys it and all associated jobs with no dialog; (2) `queued` is a valid AI/tailoring status in code constants but has no filter option in the dropdown — jobs stuck in "queued" state are invisible to the filter UI; (3) the "Tailor Resume" view silently renders with no warning when the user has no job seeker profile configured — skills gap section is just blank; (4) `ucfirst($job->status ?: 'active')` shows raw database enum values like "applied", "archived" instead of user-friendly labels; (5) error messages on deletion failure say "Please try again" with no hint of what to do if it keeps failing; (6) the job list "AI Parsed" column header does not match the badge text "Parsed" — misaligned terminology; (7) the `#empty` message on the job list says "Add Job Requirement" but the button above it says "Add Job Posting" — inconsistent naming of the same action; (8) the job detail heading "Job Requirements" is unclear — it could mean requirements OF the job or requirements FROM the user.

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life` — CompanyController.php UX copy and label review

## File reviewed
- `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php`
- 1054 lines; code quality issues previously resolved per `CODE_REVIEW_CompanyController.php.md` (2026-02-13)
- This review focused exclusively on UX copy, labels, empty states, and user flow

## Findings

### UX-1 — HIGH: Delete actions have no confirmation step
- Lines 196–199 (company delete), 425–428 (job delete)
- Routes `job_hunter.company_delete` and `job_hunter.job_delete` are direct controller methods — no `ConfirmFormBase` interstitial
- Prior code review removed inline `onclick="confirm(...)"` JS as a fix, but did not replace it with a proper Drupal confirmation form
- User clicks "Delete" → company and ALL associated jobs are immediately and permanently deleted
- Expected: confirmation step ("Are you sure? This will delete the company and 5 associated jobs.")
- Minimal diff: route `_controller` → `_form` pointing to a new `DeleteCompanyConfirmForm extends ConfirmFormBase`

### UX-2 — MEDIUM: "queued" status exists in code but is missing from filter dropdowns
- Line 29–34: `VALID_AI_STATUSES` and `VALID_TAILORING_STATUSES` both include `'queued'`
- Lines 474–489: filter dropdowns have: Parsed / Needs Parsing / Processing / Failed — no "Queued" option
- A job in `queued` state matches none of the filter options; "All AI Statuses" is the only way to see it
- User trying to find "jobs waiting to be parsed" has no way to filter for them
- Minimal diff: add `<option value="queued"{{ filter_ai_status == "queued" ? " selected" : "" }}>{{ "Queued"|t }}</option>` after the "Needs Parsing" option in both AI status and tailoring status dropdowns

### UX-3 — MEDIUM: Tailor Resume view silently blank when job seeker profile is missing
- Lines 1037–1045: loads `jobhunter_job_seeker` for current user; if no record found, `$profile_json = []` silently
- Skills gap section (`$skills_gap`) will always be empty; profile section in template will be empty
- User navigating to "Tailor Resume" with no profile set up sees a half-empty page with no explanation
- Expected: a banner/warning: "Your job seeker profile is incomplete. [Complete your profile →] to enable resume tailoring."
- Minimal diff: after profile load, add `if (!$job_seeker_profile) { $this->messenger()->addWarning($this->t('Complete your job seeker profile to enable resume tailoring. <a href="/jobhunter/profile">Set up profile →</a>')); }`

### UX-4 — MEDIUM: Job status displayed as raw database enum values
- Line 397: `ucfirst($job->status ?: 'active')` — renders "Active", "Archived", "Applied" from raw DB enum
- No human-readable label map; if a new status value is added to the schema (e.g., `offer_received`), it displays as "Offer_received"
- Also: "Archived" is ambiguous — does it mean "no longer applying" or "saved for later"?
- Minimal diff: replace `ucfirst()` with a status label map:
  ```php
  $status_labels = ['active' => 'Tracking', 'applied' => 'Applied', 'archived' => 'Closed', 'offer_received' => 'Offer Received'];
  $status_label = $status_labels[$job->status] ?? ucfirst($job->status ?: 'active');
  ```

### UX-5 — LOW: Error messages on delete failure give no recovery path
- Line 248: `'Failed to delete company. Please try again.'`
- Line 560: `'Failed to remove job. Please try again.'`
- "Please try again" is not actionable if the failure is persistent (e.g., foreign key constraint, DB connectivity)
- Minimal diff: add a support/contact hint: `'Failed to delete company. If this persists, contact support.'` or log and display an incident reference

### UX-6 — LOW: "AI Parsed" column header vs "Parsed" badge — inconsistent terminology
- Line 331: column header `$this->t('AI Parsed')`
- Line 350: badge text `'✅ Parsed'`
- A job that is "Parsed" in the badge reads as "AI Parsed" in the header — creates mild confusion about whether they refer to the same thing
- Minimal diff: unify to either "AI Parsed" everywhere or "Parsed" everywhere; recommend "AI Parsed" in header, "✅ AI Parsed" in badge

### UX-7 — LOW: Mismatched naming — "Add Job Requirement" (empty state) vs "Add Job Posting" (button)
- Line 441: button title `$this->t('Add Job Posting')`
- Line 513: empty state `$this->t('No job requirements found. Click "Add Job Requirement" to add your first job.')`
- The empty state message tells users to click "Add Job Requirement" but the actual button says "Add Job Posting"
- Minimal diff: update empty state to `'No jobs found. Click "Add Job Posting" to add your first job.'`

### UX-8 — LOW: "Job Requirements" heading is ambiguous
- Line 437: `'#markup' => '<h2>' . $this->t('Job Requirements') . '</h2>'`
- "Job Requirements" typically means what a job requires FROM candidates (skills, experience), but here it means the list of jobs being tracked
- Expected page heading should clearly indicate this is the user's job tracking list
- Minimal diff: change to `$this->t('My Tracked Jobs')` or `$this->t('Job Applications Pipeline')`

## Next actions
- Queue cap is at 3 (inbox has 2 additional idle-refactor items already) — NOT creating new follow-up items per idle queue directive
- Supervisor/PM: triage findings UX-1 through UX-3 as P1/P2 and queue to Dev if approved
- UX-1 (missing delete confirmation) is the highest priority — data loss risk on misclick

## Blockers
- None

## Needs from Supervisor
- None — findings are complete; no decision required to deliver this review. Follow-up work items blocked by queue cap; supervisor should process existing inbox items before new ones are generated.

## ROI estimate
- ROI: 5
- Rationale: UX-1 (no delete confirmation) is a data loss risk — one misclick destroys a company and all its jobs with no recovery. UX-3 (silent missing profile) causes user confusion during a core workflow. The remaining findings are low-effort copy fixes that improve usability of the job tracking pipeline, which is a primary product feature.
