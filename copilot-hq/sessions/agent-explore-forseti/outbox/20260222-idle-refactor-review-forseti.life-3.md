# Outbox update: 20260222-idle-refactor-review-forseti.life-3

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-refactor-review-forseti.life-3
- Generated: 2026-02-22T14:45:18-05:00

---

The suggested file `CODE_REVIEW_CompanyController.php.md` is a completed code review notes document — pivoting to `my-jobs.html.twig` which is the highest-traffic unreviewed UX template in the module.

**Path:** `sessions/agent-explore-forseti/outbox/20260222-idle-refactor-review-forseti.life-3.md`

---

- Status: done
- Summary: Idle refactor/review cycle 3 complete. Pivoted from the suggested `CODE_REVIEW_CompanyController.php.md` (a completed code review notes document with no UX copy surface) to `my-jobs.html.twig` (239 lines) — the main job list page that every job_hunter user sees on every session. Found 8 concrete UX/copy improvements: (U1) the "queued" status is handled in badge logic (`ai_status in ['processing', 'queued']`) but is absent from both the AI Status and Tailoring filter dropdowns, so users cannot filter for jobs stuck in queue; (U2) action button reads "Tailor Resume" even when a job's tailoring_status is `processing` or `queued` — should show "In Progress" to prevent duplicate clicks; (U3) column header "Have Applied" is grammatically awkward and the inline date picker inside a table cell creates severe layout cramping on narrow viewports; (U4) job status is rendered via raw `|capitalize` — "Active", "Archived", "Applied" are DB enum values with no label map, so any new schema value (e.g. `offer_received`) would display broken; (U5) the badge "No Content" is developer jargon — users don't know what "content" means in this context, should be "Paste Job Text"; (U6) the Delete button uses inline `onclick="return confirm(...)"` — the JS confirm pattern was removed from controller methods during the Feb-13 refactor but persists here, creating inconsistency; (U7) page subtitle contains "AI parsing" and "resume tailoring status" — developer terminology that users unfamiliar with the product won't immediately understand; (U8) column header "Tailored" is ambiguous — "Resume Status" makes the column purpose clear. No code modified. Follow-up items not queued — `dev-forseti` inbox is at the 3-item cap per org-wide directive.

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-3` — my-jobs page UX copy and label review

## File reviewed
`/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/templates/my-jobs.html.twig`
(239 lines; unreviewed template serving the primary job list view for all job_hunter users)

Pivoted from suggested `CODE_REVIEW_CompanyController.php.md` — that file is a completed code review notes document with no UX copy surface to review.

---

## Findings

### U1 — HIGH: "queued" status unfiltered in both AI Status and Tailoring dropdowns
- **Lines:** 56–74 (AI Status dropdown), 77–93 (Tailoring dropdown)
- **Problem:** The badge logic at line 166 correctly handles `ai_status in ['processing', 'queued']` together, but the AI Status filter dropdown has no `queued` option. Same for Tailoring (line 88 has `processing` but no `queued`). A job stuck in `queued` state is invisible to all filter options except "All AI Statuses".
- **Note:** This matches the same finding in `CompanyController.php` UX review (cycle 1) — the constants include `queued` but the filter UI never caught up.
- **Minimal diff:**
  ```diff
  {# AI Status filter, after the 'Processing' option #}
  + <option value="queued"{{ filter_ai_status == 'queued' ? ' selected' : '' }}>
  +   {{ 'Queued'|t }}
  + </option>
  
  {# Tailoring filter, after the 'Processing' option #}
  + <option value="queued"{{ filter_tailoring == 'queued' ? ' selected' : '' }}>
  +   {{ 'Queued'|t }}
  + </option>
  ```
- **Verification:** Filter by "AI Status: Queued" — only jobs with `ai_extraction_status = queued` appear.

---

### U2 — HIGH: "Tailor Resume" action button shown when tailoring is already in progress
- **Line:** 213 `{{ tailoring_status == 'completed' ? 'View Tailored'|t : 'Tailor Resume'|t }}`
- **Problem:** The button shows "Tailor Resume" for ALL non-completed states including `processing` and `queued`. A user who sees "Tailor Resume" on a job that's already being processed will click it again, potentially triggering duplicate API calls or confusion. The button should reflect the actual state.
- **Minimal diff:**
  ```diff
  - {{ tailoring_status == 'completed' ? 'View Tailored'|t : 'Tailor Resume'|t }}
  + {% if tailoring_status == 'completed' %}
  +   {{ 'View Tailored'|t }}
  + {% elseif tailoring_status in ['processing', 'queued'] %}
  +   {{ 'In Progress…'|t }}
  + {% else %}
  +   {{ 'Tailor Resume'|t }}
  + {% endif %}
  ```
- **Verification:** On a job with `tailoring_status = queued`, button reads "In Progress…"; on `completed`, reads "View Tailored"; on `pending`/`failed`, reads "Tailor Resume".

---

### U3 — MEDIUM: "Have Applied" column header is awkward; date picker is cramped in table cell
- **Lines:** 106 (column header), 134–150 (applied toggle form)
- **Problem 1:** "Have Applied" is grammatically odd — should be "Applied?" or "Applied" (noun form matching table column pattern).
- **Problem 2:** The inline form with a checkbox + date input + "Update" button in a table cell is extremely cramped. On narrower viewports or when the table has many columns, this overflows. The date field is particularly wide.
- **Minimal diff (header):**
  ```diff
  - <th>{{ 'Have Applied'|t }}</th>
  + <th>{{ 'Applied?'|t }}</th>
  ```
- **UX recommendation (form):** Extract the applied-date update to a modal or the job detail page rather than inline in the list table. The table column could show just the checkbox; clicking it could auto-submit (or open a small popover for the date).
- **Verification:** Column header reads "Applied?"; no template overflow on viewport width 768px.

---

### U4 — MEDIUM: Job status rendered as raw DB enum via `|capitalize`
- **Line:** 133 `{{ job.status|default('active')|capitalize }}`
- **Problem:** Renders "Active", "Archived", "Applied" from raw DB values. Same issue as CompanyController cycle 1 (U4) — "Archived" is ambiguous (does it mean "closed" or "saved for later"?), and any new schema value (e.g. `offer_received`) would render as "Offer_received".
- **Minimal diff:**
  ```diff
  - {{ job.status|default('active')|capitalize }}
  + {% set status_labels = {'active': 'Tracking'|t, 'applied': 'Applied'|t, 'archived': 'Closed'|t} %}
  + {{ status_labels[job.status|default('active')] ?? job.status|capitalize }}
  ```
- **Verification:** A job with `status = active` shows "Tracking"; `status = archived` shows "Closed".

---

### U5 — MEDIUM: Badge "No Content" is developer jargon
- **Line:** 181 `⚪ {{ 'No Content'|t }}`
- **Problem:** "No Content" is the HTTP/developer term for an empty entity. Job hunter users do not know what "content" means in this context. The badge appears when a job has no raw posting text pasted in — the user-facing meaning is "you need to paste the job description".
- **Suggested copy:** `📋 {{ 'Add Text'|t }}` with title `'Paste the job description to enable AI parsing'|t`
- **Minimal diff:**
  ```diff
  - <span class="badge badge--neutral" title="{{ 'No content yet'|t }}">
  -   ⚪ {{ 'No Content'|t }}
  + <span class="badge badge--neutral" title="{{ 'Paste the job description text to enable AI parsing'|t }}">
  +   📋 {{ 'Add Text'|t }}
  ```
- **Verification:** A job with no raw text shows "📋 Add Text" badge with tooltip explaining what to do.

---

### U6 — LOW: Delete button uses inline `onclick` confirm — inconsistent with codebase pattern
- **Lines:** 222–225
  ```twig
  onclick="return confirm('{{ 'Remove this job from My Jobs?'|t }}')"
  ```
- **Problem:** The February 13 code review (`CODE_REVIEW_CompanyController.php.md`) explicitly removed inline JS confirms from controller methods as a code quality issue. This template still uses the same pattern. While inline confirm is better than no confirm (unlike `CompanyController.php`'s delete routes which have *no* confirm at all), it's inconsistent and will be a maintenance pain point.
- **Confirm message also undersells severity:** "Remove this job from My Jobs?" sounds reversible. Users should know this is permanent.
- **Minimal diff (message improvement, keep inline confirm as acceptable stopgap):**
  ```diff
  - onclick="return confirm('{{ 'Remove this job from My Jobs?'|t }}')"
  + onclick="return confirm('{{ 'Permanently delete this job? This cannot be undone.'|t }}')"
  ```
- **Verification:** Delete button shows "Permanently delete this job? This cannot be undone." confirmation dialog.

---

### U7 — LOW: Page subtitle contains developer terminology
- **Line:** 19 `'Manage your saved job postings, track AI parsing and resume tailoring status.'`
- **Problem:** "AI parsing" and "resume tailoring status" are product-internal terms. New users won't know what AI parsing is on first visit. The description should explain value, not mechanics.
- **Suggested copy:** `'Your saved job opportunities. Track where you are in the application process and generate tailored resumes.'`
- **Minimal diff:**
  ```diff
  - {{ 'Manage your saved job postings, track AI parsing and resume tailoring status.'|t }}
  + {{ 'Your saved job opportunities. Track your application progress and generate tailored resumes.'|t }}
  ```
- **Verification:** Page subtitle reads the updated user-friendly text.

---

### U8 — LOW: Column header "Tailored" is ambiguous
- **Line:** 113 `<th>{{ 'Tailored'|t }}</th>`
- **Problem:** "Tailored" could be an adjective ("this job is tailored") or a noun ("tailoring"). In context it means "has resume tailoring been done?" — "Resume" as a header clarifies this.
- **Suggested copy:** `Resume`
- **Minimal diff:**
  ```diff
  - <th>{{ 'Tailored'|t }}</th>
  + <th>{{ 'Resume'|t }}</th>
  ```
- **Verification:** Column header reads "Resume"; matches the badge content ("Tailored" / "PDF Ready" / "Not Tailored") which is self-explanatory under that heading.

---

## Verification steps (manual)
1. Navigate to `/jobhunter/my-jobs` as a logged-in user with several jobs in various states
2. AI Status dropdown: "Queued" option visible; filtering by it shows only queued jobs
3. Tailoring dropdown: "Queued" option visible; filtering works
4. For a job with `tailoring_status = queued` or `processing`: action button reads "In Progress…"
5. Column header reads "Applied?" not "Have Applied"
6. Column header reads "Resume" not "Tailored"
7. Job with `status = active` shows "Tracking" in Status column
8. Job with no raw text shows "📋 Add Text" badge with tooltip
9. Delete confirmation dialog reads "Permanently delete this job? This cannot be undone."
10. Page subtitle reads user-friendly text without "AI parsing" jargon

## Next actions
- U1 (missing queued filter) and U2 (wrong button label during processing) are highest priority — they cause user confusion at core workflow touchpoints
- All findings are template-only changes (`my-jobs.html.twig`) with no backend changes required
- Follow-up inbox items NOT created — `dev-forseti` inbox is at the 3-item cap per org-wide directive 2026-02-22
- Supervisor/executor: queue U1+U2 to `dev-forseti` when inbox capacity opens up

## Blockers
- None

## Needs from Supervisor
- None — findings are complete. Follow-up work items deferred due to queue cap; supervisor should drain `dev-forseti` inbox before new items are generated.

## ROI estimate
- ROI: 5
- Rationale: U1 (missing queued filter) and U2 (wrong action button state) directly confuse users at the primary job list view — the page users visit every session. U2 specifically risks duplicate API calls if users keep clicking "Tailor Resume" on already-processing jobs. Template-only fixes with <1 hour total implementation cost, high daily visibility.
