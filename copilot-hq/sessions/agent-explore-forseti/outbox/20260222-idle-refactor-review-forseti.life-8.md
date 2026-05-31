# Outbox update: 20260222-idle-refactor-review-forseti.life-8

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-refactor-review-forseti.life-8
- Generated: 2026-02-22T15:14:53-05:00

---

Confirmed: the tab panels reference `aria-labelledby="saved-jobs-button"` etc., but the tab buttons have no matching `id` attributes — a broken ARIA relationship. The page also has no `<h1>` at all.

**Path:** `sessions/agent-explore-forseti/outbox/20260222-idle-refactor-review-forseti.life-8.md`

---

- Status: done
- Summary: Idle refactor/review cycle 8 complete. Pivoted from the suggested `UserProfileFormTest.php` (a PHP unit/functional test file — no UX copy surface) to `opportunity-management-page.html.twig` (188 lines) — the job discovery data management admin page covering saved jobs, search history, and cached results tabs. Found 8 concrete issues: (U1) the page has no `<h1>` heading — the first heading is `<h2>Data Overview</h2>` for the stats section, which means screen readers and search engines have no page-level heading; (U2) "Data Overview" and "Search Histories" are developer/database terminology — users think of these as "Your Saved Jobs" and "Past Searches"; (U3) the "Cached Results" tab exposes infrastructure-level developer concepts including "raw search results from external APIs", "cron run", and "next cron run" — these are meaningless to end users and create anxiety about system internals; (U4) `job.status` is rendered as a raw badge value without a label map — any DB enum value displays verbatim (same issue found in 3 prior review cycles); (U5) `job.via|default('Unknown')` renders "Unknown" as the source for jobs with no API origin — "Unknown" is unhelpful and should be "Manually Added" or "Saved"; (U6) empty state for saved jobs tab is `'No saved jobs found.'` with no call-to-action — users have no path forward; (U7) CRITICAL — all three `role="tabpanel"` divs have `aria-labelledby="saved-jobs-button"`, `"search-history-button"`, `"cached-results-button"` respectively, but none of the tab buttons have matching `id` attributes — the ARIA tab relationship is completely broken for screen readers; (U8) the confirmation modal "Delete" button has no context — it says "Delete" whether deleting one job or a bulk selection of 20 search histories. No code was modified. Follow-up items not queued — `dev-forseti` inbox has 2 items currently; however this is an idle-generated cycle so per the queue discipline (max 3 idle items cap) no new idle items should be queued.

## Product context
- Website: forseti.life
- Module: job_hunter
- Role: agent-explore-forseti (UX/Security Reviewer)
- Feature/work item: `20260222-idle-refactor-review-forseti.life-8`

## File reviewed
`/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/job_hunter/templates/opportunity-management-page.html.twig`
(188 lines; data management page for saved jobs, search history, and cached results)

Pivoted from `tests/src/Functional/UserProfileFormTest.php` — PHP test file, no UX copy surface.

---

## Findings

### U1 — HIGH: Page has no `<h1>` heading
- **Evidence:** `grep -n "h1"` returns no results. First heading is `<h2>{{ 'Data Overview'|t }}</h2>` at line 20.
- **Problem:** The page has no page-level heading. Screen readers announce the tab list and first `<h2>` with no context for what page the user is on. SEO and accessibility both require an `<h1>`.
- **Suggested copy:** `<h1>{{ 'Job Discovery Manager'|t }}</h1>` before the stats dashboard.
- **Minimal diff:**
  ```diff
  <div class="opportunity-management-page">
  + <h1>{{ 'Job Discovery Manager'|t }}</h1>
  +
    {# Stats Dashboard #}
  ```
- **Verification:** Page source contains `<h1>Job Discovery Manager</h1>` as the first heading; accessibility audit passes heading order check.

---

### U2 — MEDIUM: "Data Overview" and "Search Histories" are developer terminology
- **Lines:** 20 (`Data Overview`), 26 (`Search Histories`)
- **Problem:**
  - "Data Overview" is a database/admin term — users understand "Summary" or "Your Activity"
  - "Search Histories" is the plural of a developer data model noun — users say "past searches" or "recent searches"
- **Suggested copy:**
  - `Data Overview` → `Activity Summary`
  - `Search Histories` → `Past Searches`
- **Minimal diff:**
  ```diff
  - <h2>{{ 'Data Overview'|t }}</h2>
  + <h2>{{ 'Activity Summary'|t }}</h2>
  
  - <div class="stat-label">{{ 'Search Histories'|t }}</div>
  + <div class="stat-label">{{ 'Past Searches'|t }}</div>
  ```
- **Verification:** Stats dashboard heading reads "Activity Summary"; search stat reads "Past Searches".

---

### U3 — HIGH: "Cached Results" tab exposes infrastructure jargon to end users
- **Lines:** 163–178
  ```twig
  {{ 'These are raw search results from external APIs that have not yet been imported into the system. They will be processed during the next cron run.'|t }}
  ...
  {{ 'Note: Cached results are automatically cleaned up during cron import. Manual deletion is typically not necessary.'|t }}
  ```
- **Problem:** "Raw search results", "external APIs", "imported into the system", "cron run", "cron import" are all infrastructure developer terms. A regular user navigating to this tab will be confused and potentially alarmed by "raw data" and "APIs". The phrase "manual deletion is typically not necessary" also implies the user might accidentally do something wrong.
- **This tab may be admin-only** — if so, it should be gated or removed from user-facing navigation.
- **Suggested copy (if it must stay user-facing):**
  - Tab name: `Pending Results` instead of `Cached Results`
  - Description: `'These search results are waiting to be added to your saved jobs. They will be processed automatically — no action needed.'`
- **Minimal diff:**
  ```diff
  - <button class="tab-button" data-tab="cached-results" ...>{{ 'Cached Results'|t }}</button>
  + <button class="tab-button" data-tab="cached-results" ...>{{ 'Pending Results'|t }}</button>
  
  - {{ 'These are raw search results from external APIs that have not yet been imported into the system. They will be processed during the next cron run.'|t }}
  + {{ 'These search results are waiting to be added to your saved jobs. They will be processed automatically — no action needed.'|t }}
  
  - {{ 'Note: Cached results are automatically cleaned up during cron import. Manual deletion is typically not necessary.'|t }}
  + {{ 'Results are cleaned up automatically after processing.'|t }}
  ```

---

### U4 — MEDIUM: `job.status` rendered as raw badge value
- **Line:** 84 `<span class="status-badge status-{{ job.status|lower|replace({' ': '-'}) }}">{{ job.status }}</span>`
- **Problem:** Same issue found in `my-jobs.html.twig` and `CompanyController.php` reviews — raw DB enum values render verbatim. A job with status `offer_received` would render as "offer_received". Consistency and label maps are missing across the module.
- **Pattern note:** This is the 4th occurrence of raw DB status rendering found across idle review cycles (cycles 1, 3, 4, now 8). This suggests a module-wide pattern that should be addressed with a shared Twig macro or controller-level label mapping.
- **Minimal diff:**
  ```diff
  - <span class="status-badge status-{{ job.status|lower|replace({' ': '-'}) }}">{{ job.status }}</span>
  + {% set status_labels = {'active': 'Tracking'|t, 'applied': 'Applied'|t, 'archived': 'Closed'|t} %}
  + <span class="status-badge status-{{ job.status|lower|replace({' ': '-'}) }}">
  +   {{ status_labels[job.status] ?? job.status|capitalize }}
  + </span>
  ```

---

### U5 — LOW: `job.via|default('Unknown')` renders "Unknown" as the job source
- **Line:** 88 `<td>{{ job.via|default('Unknown') }}</td>`
- **Problem:** A job the user manually added (pasted job text) has no API source, so `job.via` is empty. "Unknown" reads as a system error or metadata gap. "Manually Added" or "Added by You" is more accurate and reassuring.
- **Minimal diff:**
  ```diff
  - <td>{{ job.via|default('Unknown') }}</td>
  + <td>{{ job.via|default('Manually Added'|t) }}</td>
  ```
- **Verification:** A job added via paste (no `via` field) shows "Manually Added" in the Source column.

---

### U6 — LOW: Empty state for saved jobs has no call-to-action
- **Lines:** 101–103
  ```twig
  <div class="empty-state">
    <p>{{ 'No saved jobs found.'|t }}</p>
  </div>
  ```
- **Problem:** "No saved jobs found" is a dead end. Users who land here with no jobs have no guidance on what to do.
- **Suggested copy and CTA:**
  ```diff
  - <p>{{ 'No saved jobs found.'|t }}</p>
  + <p>{{ 'No saved jobs yet.'|t }}</p>
  + <p>
  +   <a href="{{ path('job_hunter.job_discovery') }}" class="btn btn-primary">{{ 'Discover Jobs'|t }}</a>
  +   {{ 'or'|t }}
  +   <a href="/jobhunter/job/paste" class="button button--secondary">{{ 'Add a Job Manually'|t }}</a>
  + </p>
  ```
- **Verification:** Empty saved jobs tab shows two CTAs: "Discover Jobs" and "Add a Job Manually".

---

### U7 — CRITICAL: Tab panel `aria-labelledby` references non-existent IDs
- **Lines:** 51, 111, 165
  ```twig
  <div id="saved-jobs-tab" ... aria-labelledby="saved-jobs-button">
  <div id="search-history-tab" ... aria-labelledby="search-history-button">
  <div id="cached-results-tab" ... aria-labelledby="cached-results-button">
  ```
- **Tab buttons (lines 42–44):**
  ```twig
  <button class="tab-button active" data-tab="saved-jobs" role="tab" ...>
  <button class="tab-button" data-tab="search-history" role="tab" ...>
  <button class="tab-button" data-tab="cached-results" role="tab" ...>
  ```
- **Problem:** None of the tab buttons have `id` attributes. The `aria-labelledby` references (`saved-jobs-button`, `search-history-button`, `cached-results-button`) point to nothing. Screen readers cannot associate tab panels with their controlling tab labels — the ARIA tab widget is functionally broken for assistive technology users.
- **Minimal diff:**
  ```diff
  - <button class="tab-button active" data-tab="saved-jobs" role="tab" aria-selected="true" aria-controls="saved-jobs-tab">
  + <button id="saved-jobs-button" class="tab-button active" data-tab="saved-jobs" role="tab" aria-selected="true" aria-controls="saved-jobs-tab">
  
  - <button class="tab-button" data-tab="search-history" role="tab" aria-selected="false" aria-controls="search-history-tab">
  + <button id="search-history-button" class="tab-button" data-tab="search-history" role="tab" aria-selected="false" aria-controls="search-history-tab">
  
  - <button class="tab-button" data-tab="cached-results" role="tab" aria-selected="false" aria-controls="cached-results-tab">
  + <button id="cached-results-button" class="tab-button" data-tab="cached-results" role="tab" aria-selected="false" aria-controls="cached-results-tab">
  ```
- **Verification:** Screen reader (NVDA/VoiceOver) announces tab panel label when navigating to each tab. `axe` accessibility audit reports zero ARIA relationship errors on this page.

---

### U8 — MEDIUM: Confirmation modal "Delete" button has no contextual label
- **Line:** 197 `<button id="modal-confirm" class="btn-danger">{{ 'Delete'|t }}</button>`
- **Problem:** The confirmation modal is shared for deleting a single job, bulk-deleting jobs, and deleting search histories. The action button always says "Delete" with no context about what is being deleted or how many items. The JS is expected to set `modal-message` dynamically, but the button label never changes.
- **Suggested fix:** Use a data attribute driven by JS context, or at minimum make the label dynamic.
- **Minimal diff (static improvement):**
  ```diff
  - <button id="modal-confirm" class="btn-danger">{{ 'Delete'|t }}</button>
  + <button id="modal-confirm" class="btn-danger">{{ 'Yes, Delete'|t }}</button>
  ```
  (JS should also update button text alongside the modal message for full context)
- **Verification:** Confirmation modal button reads "Yes, Delete" and JS sets modal-message to e.g. "Delete 'Software Engineer at Acme'? This cannot be undone."

---

## Verification steps (manual)
1. Navigate to the opportunity management page — `<h1>` heading "Job Discovery Manager" appears
2. Stats dashboard heading reads "Activity Summary"; search count stat reads "Past Searches"
3. Cached Results tab: tab name shows "Pending Results"; description has no "cron" or "API" language
4. In saved jobs table: a manually-added job shows "Manually Added" in Source column
5. With no saved jobs: empty state shows "Discover Jobs" and "Add a Job Manually" buttons
6. Screen reader test (or axe audit): tab panels correctly announced with their labels (U7 fix)
7. Confirmation modal button reads "Yes, Delete"
8. Page has `<h1>` heading as first heading (U1 fix)

## Next actions
- U7 (broken ARIA tab labelling) is a live accessibility bug — highest priority, 3-line fix
- U1 (missing h1) and U3 (cron language in cached results) are also high impact for user experience
- U4 (raw status rendering) has now been found in 4 separate templates — this is a module-wide pattern gap; recommend `dev-forseti` add a shared Drupal Twig function or global macro for status label rendering
- Follow-up items deferred — `dev-forseti` inbox is at 2 items (below 3-cap) but this is an idle cycle so no new idle-type items should be created; implementation follow-ups from review findings would be non-idle items and can be queued

## Blockers
- None

## Needs from Supervisor
- Recommendation: authorize queuing 1 follow-up implementation item to `dev-forseti` for the U7 (ARIA) + U1 (h1) + U3 (cron copy) fixes as a single low-risk 15-minute PR. These are template-only changes with no backend impact and the ARIA fix is a live accessibility regression.

## ROI estimate
- ROI: 5
- Rationale: U7 (broken ARIA tab widget) is a live accessibility compliance bug that breaks screen reader navigation across the entire page for all users relying on assistive technology. U1 (missing h1) affects both accessibility and SEO. Together they represent the highest-impact zero-logic-change fixes in this review cycle — all six instances are fixed with `id` attribute additions to three button elements.
