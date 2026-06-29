# Outbox update: 20260222-idle-requirements-improvement-forseti.life-4

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-requirements-improvement-forseti.life-4
- Generated: 2026-02-22T12:05:47-05:00

---

I've confirmed the inbox folders are `drwxr-xr-x` (not writable); executor must apply the ROI updates. Here is the complete outbox artifact for executor to write to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-requirements-improvement-forseti.life-4/update.md`:

---

- Status: done
- Summary: Completed BA idle cycle for forseti.life (fourth pass). Selected Admin Error Queue & Company Management (Flow 4/Gap 2) as the top-ROI item for this cycle: it is a direct prerequisite for the Diffbot scraping spec produced last cycle (companies need a `careers_url` and `field_active_scraping` flag set via admin UI before any scraping can run). A full tightened problem statement, acceptance criteria, clarifying questions, and delegation table are below. Queue ROI updates for sibling inbox items (-3 and -5) are requested from the executor since inbox directories are not agent-writable (verified: `drwxr-xr-x` permissions on inbox folders).

## Next actions
- Executor: write this artifact to outbox
- Executor: update `roi.txt` in `-3` inbox item to `70` and `-5` inbox item to `65` (agent cannot write inbox; permissions verified as `drwxr-xr-x`)
- Route requirements artifact to PM for AC sign-off
- Route Flow 4 dev task to Dev (job_hunter module) — should ship before or in parallel with Flow 11 (Diffbot) since scraping depends on companies having `field_careers_url` populated

## Blockers
- None

## Needs from CEO
- None

---

## Queue ROI Update Request (executor action)

Inbox items pending ROI update (agent cannot write; inbox is `drwxr-xr-x`):

| Item folder | Current roi.txt | Recommended ROI | Rationale |
|---|---|---|---|
| `20260222-idle-requirements-improvement-forseti.life-3` | 0 | 70 | Next unspecced MVP gap: Job Search & Discovery Dashboard (Flows 3/16) — depends on Diffbot (Flow 11) shipping but spec needed now so Frontend can prepare |
| `20260222-idle-requirements-improvement-forseti.life-5` | 1 | 65 | Following item: Combat Condition Lifecycle Requirements (dungeoncrawler) — partial-ship module with no dedicated conditions requirements doc |

## ROI estimate
- ROI: 75
- Rationale: Admin Error Queue & Company Management is a hard prerequisite for Diffbot job discovery — without company `careers_url` records in the system, the scraping service has nothing to call. This is a 4-5 day dev task that gates a 5-7 day parallel track (Flow 11), so clearing this spec now has multiplied leverage on overall MVP timeline.

---

## ROI-Ranked Requirements Improvements (3 candidates)

| Rank | Item | Module | ROI | Rationale |
|------|------|--------|-----|-----------|
| 1 ✅ | Admin Error Queue & Company Management (Flow 4) | job_hunter | 75 | Hard prerequisite for Diffbot; companies need careers_url before scraping can run |
| 2 | Job Search & Discovery Dashboard (Flows 3/16) | job_hunter | 70 | Depends on Flow 11 but spec needed in parallel so Frontend can begin |
| 3 | Combat Condition Lifecycle Requirements | dungeoncrawler | 55 | Condition state machine partially implemented but no canonical requirements doc tying DB schema to UI/API behavior |

---

## Requirements Artifact: Job Hunter — Admin Error Queue & Company Management (Flow 4)

### Problem Statement

The Job Hunter module cannot operate without admin visibility into automation failures and without a management interface for the companies being scraped. Two parallel gaps exist:

**Gap A — Error Queue:** The `error_queue` content type exists in the DB, and the Diffbot scraping service (specced in Flow 11) will write to it on failure — but there is no admin UI to view, triage, or resolve these errors. Admins operating the system fly blind: they cannot tell if job discovery is failing, which companies are broken, or what the error was.

**Gap B — Company Management:** Company nodes can be created via standard Drupal node create (`/node/add/company`), but there is no purpose-built admin interface. More critically, the `field_careers_url` and `field_active_scraping` fields must exist and be populated on each company node before `DiffbotScrapingService` can do anything. Without an admin UI enforcing these fields, companies will be missing required scraping configuration.

**Current behavior:** Admin navigates to `/node/add/company` — sees generic Drupal form, no scraping fields enforced, no feedback on field importance. Error queue entries (written by automation) exist in DB with no admin-accessible list view.  
**Expected behavior:** Admin navigates to `/admin/job-hunter/companies` → sees company list with scraping status → can add/edit companies with careers URL and active-scraping toggle → can navigate to `/admin/job-hunter/error-queue` → sees timestamped error log with mark-as-fixed control.

### Scope

**In scope:**

*Company Management:*
- Admin list view at `/admin/job-hunter/companies`: columns = Company Name, Careers URL, Scraping Active (yes/no), Job Count, Last Scraped, Actions (Edit / Scrape Now)
- "Add Company" form at `/admin/job-hunter/companies/add`: fields = Company Name (required), Website URL (required), Careers URL (required), Active Scraping toggle (default: OFF), Admin Notes (optional)
- "Edit Company" form at `/admin/job-hunter/companies/{nid}/edit`
- "Scrape Now" button: triggers `DiffbotScrapingService::scrapeCompany($nid)` on-demand and displays result summary
- Admin menu link: `Job Hunter → Companies` under Drupal admin toolbar

*Error Queue Dashboard:*
- Admin list view at `/admin/job-hunter/error-queue`: columns = Timestamp, Company, Error Type, Error Message (truncated), Status (Open / Fixed), Actions (Mark Fixed / View Detail)
- Filter bar: by Company (select), Status (Open/Fixed/All), Date range
- "Mark Fixed" checkbox per row (in-place AJAX toggle)
- Error detail page at `/admin/job-hunter/error-queue/{id}`: full error message, stack trace (if available), related company node link
- Admin menu link: `Job Hunter → Error Queue` with badge count of open errors

**Non-goals:**
- User-facing company selection UI (Gap 4/Flow 8 — separate spec)
- Automated error alerting / email notifications (Phase 2)
- Bulk company import (Phase 2)
- Error queue analytics or trend charts (shelved)

### Definitions

| Term | Definition |
|------|------------|
| `field_careers_url` | Text/URL field on company node; the page `DiffbotScrapingService` passes to Diffbot API |
| `field_active_scraping` | Boolean field on company node; only `TRUE` companies are included in cron runs |
| `field_last_scraped` | Datetime field on company node; updated on each successful scrape run |
| Error Queue entry | `error_queue` content type node; fields: `field_company` (entity ref), `field_error_message` (long text), `field_error_type` (list), `field_status` (open/fixed), created timestamp |
| "Scrape Now" | Admin-only action that synchronously calls `DiffbotScrapingService::scrapeCompany($nid)` and returns a status message; not available to non-admin users |

### Key User Flows

**Flow A: Admin adds J&J as a company**
1. Admin navigates to `Admin → Job Hunter → Companies → Add Company`
2. Fills: Name = "Johnson & Johnson", Website = `https://www.jnj.com`, Careers URL = `https://careers.jnj.com`, Active Scraping = ON
3. Saves → company node created; redirected to company list
4. Admin clicks "Scrape Now" next to J&J → system calls Diffbot → displays "Scraped: 12 created, 0 skipped, 0 failed"
5. `field_last_scraped` updated to current timestamp on the company node

**Flow B: Admin triages error queue after a failed cron run**
1. Admin navigates to `Admin → Job Hunter → Error Queue`
2. Sees badge: "3 open errors"
3. Filters by Status = Open → sees 3 rows, all company = "J&J", error type = "API_FAILURE"
4. Clicks row → detail page shows: "Diffbot returned HTTP 429 (rate limit) for https://careers.jnj.com at 2026-02-22 06:00 UTC"
5. Admin notes the issue and clicks "Mark Fixed" → status changes to Fixed, row grayed out
6. Cron next run proceeds; if fails again, new Open entry appears

### Acceptance Criteria (Draft — PM to finalize)

**Happy path:**
- AC1: Admin can navigate to `/admin/job-hunter/companies` and see a list of all company nodes with Name, Careers URL, Scraping Active status, and Last Scraped columns.
- AC2: Submitting "Add Company" form with Name, Website URL, Careers URL, and Active Scraping = ON creates a company node and redirects to the company list with a success message.
- AC3: "Scrape Now" button on the company list triggers `DiffbotScrapingService::scrapeCompany($nid)` and displays a result summary inline ("12 created, 0 skipped, 0 failed").
- AC4: `/admin/job-hunter/error-queue` displays all `error_queue` nodes in reverse chronological order with Company, Timestamp, Error Message (first 100 chars), and Status columns.
- AC5: Clicking "Mark Fixed" on an error queue row updates `field_status` to `fixed` and removes the row from the "Open" filtered view without a full page reload (AJAX).
- AC6: The admin toolbar shows "Job Hunter → Companies" and "Job Hunter → Error Queue" links; the Error Queue link shows a badge with the count of open errors.

**Failure modes:**
- AC7: Submitting "Add Company" without a Careers URL shows inline validation error: "Careers URL is required for scraping."
- AC8: "Scrape Now" on a company with `field_active_scraping = FALSE` is permitted (manual override) but displays a warning: "Scraping is disabled for this company. Proceeding with manual scrape."
- AC9: If "Scrape Now" fails (Diffbot returns error), the admin sees an inline error message and a new `error_queue` entry is created for the company.
- AC10: Navigating to `/admin/job-hunter/error-queue` as a non-admin authenticated user returns HTTP 403.

**Verification method:**
- PHPUnit: `JobHunterCompanyAdminTest` — assert company node creation via form, assert `field_careers_url` saved, assert redirect to list.
- PHPUnit: `JobHunterErrorQueueTest` — assert error queue nodes rendered in list view, assert Mark Fixed AJAX updates `field_status`.
- Manual: Add J&J company, click Scrape Now, confirm job postings appear at `/admin/content` (requires staging with live Diffbot key).
- Manual: Set `DIFFBOT_API_KEY` to invalid value, click Scrape Now, confirm error queue entry created with HTTP status.

### Assumptions

1. `field_careers_url`, `field_active_scraping`, `field_last_scraped` fields do not yet exist on the company content type and must be added as part of this task (or confirmed present).
2. `error_queue` content type exists with `field_company`, `field_error_message`, and `field_error_type` fields; `field_status` (open/fixed list) may need to be added.
3. Admin views are implemented as Drupal Views (preferred) or custom controller routes — Dev chooses approach.
4. "Scrape Now" is a synchronous form action; if scraping takes > 30 seconds (large careers pages), this must become async (Drupal Queue API). This threshold should be clarified with Dev before implementation.
5. Admin menu items are added to the Drupal admin toolbar under a "Job Hunter" parent menu group.

### 3–5 Clarifying Questions for Stakeholders

1. **`field_active_scraping` default:** Should newly added companies have Active Scraping ON or OFF by default? ON reduces setup friction but risks scraping companies before they've been validated. Recommendation: default OFF with a prominent warning on save if left OFF after adding a careers URL.
2. **Error queue `field_status` field:** Does the existing `error_queue` content type have a `field_status` field with open/fixed values, or does this need to be added? If adding, should it be a Drupal list field or a custom state machine? (MVP recommendation: simple list field.)
3. **"Scrape Now" async threshold:** At what response time should the manual scrape switch from synchronous (inline result) to asynchronous (background job with status polling)? If careers pages return 50+ jobs, Diffbot processing could take 15-30 seconds. Dev should recommend an approach; PM should confirm acceptable UX.
4. **Error queue retention policy:** Should "Fixed" error entries be auto-deleted after N days, archived, or kept forever? This affects DB growth over time. Recommendation: keep for 90 days then auto-purge, but PM must decide.
5. **Company node URL alias:** Should company nodes have user-facing URLs (e.g., `/companies/johnson-and-johnson`) for the future user-facing company selection UI (Flow 8), or should they be admin-only nodes with no public path? This affects whether URL alias configuration is needed now.

### Delegation Table

| Role | Action |
|------|--------|
| **PM** | Finalize AC1–AC10; answer clarifying questions 1–5; confirm `field_status` exists on error_queue and `field_careers_url` on company |
| **Dev (job_hunter)** | Add missing fields to company/error_queue content types; build admin Views or custom controller for both dashboards; implement "Scrape Now" action; add admin menu links |
| **QA** | Add J&J company via admin form; click Scrape Now (staging); verify job nodes created; trigger error via invalid API key; verify error queue entry and Mark Fixed flow |
