# Outbox update: 20260222-idle-explore-forseti.life-2

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-explore-forseti.life-2
- Generated: 2026-02-22T10:49:03-05:00

---

Now I have a clear picture. The `dashboard.companies_url` is never set in the controller (`#dashboard` only has `main_url`), so clicking "View Companies" on the home page will render a broken/empty URL. Let me check a few more things.

Now I have a thorough picture of the Job Hunter product. Let me write the outbox.

Here is the outbox for this session. Per org policy, the executor/CEO writes the file to `sessions/agent-explore-forseti/outbox/20260222-idle-explore-forseti.life-2.md`.

---

- Status: done
- Summary: Explored the forseti.life Job Hunter product as a simulated authenticated user, walking the flow: home (`/jobhunter/home`) → My Profile → Job Discovery → My Jobs → Job Tailoring → Analytics → Application Submission → Interview Follow-up → Documentation → Workflow page. Found 7 distinct issues across broken UI state, stub pages presented as real features, a data-binding bug that breaks the "View Companies" CTA, a permission mismatch on Job Discovery, and a hard Twig null reference from a missing controller variable. No code was modified.

## Next actions
- PM should triage and prioritize the 7 issues below.
- Highest-ROI fix: supply `companies_url` in `JobHunterHomeController::home()` (#1) — one-line fix, breaks a visible CTA on every home page load.
- Second: Suppress or label stub pages (#3) so users don't click into dead ends.
- Third: Fix `WorkflowController::manage()` variable name bug (#6) — current_status and workflow_actions are built into `$build` but never returned, resulting in a partial page.
- Fourth: Align the Job Discovery nav visibility with its route permission (#4).

## Blockers
- None. All findings are from static code/template review.

## Needs from CEO
- None. All issues are actionable with existing scope.

---

## Findings: Confusion Points and Broken Flows

### 1. Home page "View Companies" CTA has a null URL — `dashboard.companies_url` never set
**Path:** `/jobhunter/home` → Quick Actions → "Companies" card → "View Companies" button
**Expected:** Button links to `/jobhunter/companies/list`.
**Actual:** `JobHunterHomeController::home()` sets `#dashboard` with only `main_url`; `companies_url` is never assigned. The Twig template renders `{{ dashboard.companies_url }}` which resolves to empty string, producing a broken `href=""` link. Clicking "View Companies" reloads the current page.
**Fix:** Add `'companies_url' => Url::fromRoute('job_hunter.companies_list')->toString()` to the `#dashboard` array.
**File:** `sites/forseti/web/modules/custom/job_hunter/src/Controller/JobHunterHomeController.php`

---

### 2. "View Profile" link always redirects to the Edit form — no read-only profile view
**Path:** `/jobhunter/home` → "My Profile" card → "View Profile"
**Expected:** Opens a read-only summary of the user's profile (contact info, experience, skills).
**Actual:** `UserProfileController::redirectToEdit()` immediately 302-redirects to `/jobhunter/profile/edit`. The user lands on an edit form instead of a summary. The home page template explicitly labels the two buttons "View Profile" and "Edit Profile" as separate actions, but both go to the same edit destination. Users who want to review their profile without editing are given no option.
**Note:** A read-only `profileDashboard()` controller and `/jobhunter/profile/summary` route exist but are not wired to the "View Profile" CTA.
**File:** `sites/forseti/web/modules/custom/job_hunter/src/Controller/UserProfileController.php`

---

### 3. Three navigable pages are stub placeholders with visible TODO banners
**Paths and stubs:**
- `/jobhunter/analytics` — `<strong>TODO:</strong> Implement analytics dashboard with success metrics.`
- `/jobhunter/application-submission` — `<strong>TODO:</strong> Implement automated application submission.`
- `/jobhunter/interview-followup` — `<strong>TODO:</strong> Implement interview tracking and follow-up management.`

**Expected:** Either real content, or a "Coming Soon" label so users understand the feature is in-progress.
**Actual:** All three render with a yellow Bootstrap `alert-warning` banner reading "TODO:" inline in the page. These are accessible to all authenticated users via the nav and direct URL. A user clicking through the nav will hit dead-end stub pages with developer notes visible.
**File:** `sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`

---

### 4. "Job Discovery" is hidden from sidebar nav for non-admin users but the home page CTA points there for all users
**Path:** `/jobhunter/home` → "Start Discovery" button (visible to all `access job hunter` users)
**Expected:** Navigation sidebar also shows "Job Discovery" for all users who can access the page, or the home page CTA is also restricted.
**Actual:** `JobHunterNavigationBlock::build()` gates "Job Discovery" behind `administer job application automation`. The home page always renders the "Start Discovery" CTA pointing to `/jobhunter/job-discovery` (which requires only `access job hunter`). Non-admin users can click the CTA and reach the page, but the feature is invisible in the sidebar nav. The inconsistency makes discovery feel like a hidden/broken link rather than a first-class workflow step.
**Files:** `src/Plugin/Block/JobHunterNavigationBlock.php`, `templates/job-hunter-home.html.twig`

---

### 5. All four Documentation pages are admin-only — regular users see "Access Denied" if they try
**Path:** `/jobhunter/documentation` (and all sub-pages)
**Expected:** FAQ and process documentation are accessible to all authenticated users (`access job hunter`), since they describe how to use the product.
**Actual:** Every documentation route requires `administer job application automation`. A regular authenticated user who clicks "Module Settings → Documentation" or navigates directly gets Access Denied. The FAQ in particular is user-facing help content that should be readable by anyone using the system.
**File:** `sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml` — all `job_hunter.documentation.*` routes

---

### 6. `WorkflowController::manage()` has a variable name bug — page renders without status and action sections
**Path:** `/jobhunter/applications/{id}/workflow`
**Expected:** Page shows current status, available workflow actions (Move to Interview, Mark as Reviewed, etc.), and activity timeline.
**Actual:** The controller sets `$content = []` and populates `$content['header']` and `$content['timeline']`, but sets `$build['current_status']` and `$build['workflow_actions']` — a different variable that is never passed to the template. The method returns `$this->wrapWithNavigation($content)` so the status badge and action buttons are silently dropped. Users navigating to a workflow page see only the header and timeline with no actions available.
**File:** `sites/forseti/web/modules/custom/job_hunter/src/Controller/WorkflowController.php` — lines 36–75

---

### 7. `/jobhunter/jobs` redirects to Job Discovery instead of a jobs list
**Path:** My Jobs page → "← Back to Jobs" link (in job tailoring template uses `job_hunter.jobs_list`) → resolves to `/jobhunter/jobs`
**Expected:** Navigate back to the user's list of saved jobs.
**Actual:** `JobApplicationController::listJobsRedirect()` redirects `/jobhunter/jobs` to `/jobhunter/job-discovery`. After tailoring a resume and clicking "Back to Jobs", the user lands on the Job Discovery search page instead of their saved jobs list. The intended destination is `/jobhunter/my-jobs`. The route `job_hunter.jobs_list` is used in the tailoring template as a back-link but resolves to the wrong destination.
**Files:** `src/Controller/JobApplicationController.php` (`listJobsRedirect()`), `templates/job-tailoring-combined.html.twig`

---

## ROI estimate
- ROI: 8
- Rationale: Issues #1 (broken CTA), #6 (missing workflow actions), and #7 (wrong back-navigation) are functional breakages that directly disrupt core user tasks. Issues #3 and #5 create confusing dead ends for authenticated users. Combined, they significantly degrade the first-use and daily-use experience of the primary job application workflow. Fixes #1 and #7 are one-liners; #6 is a two-line variable rename.
