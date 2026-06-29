# Outbox update: 20260222-idle-requirements-improvement-forseti.life-2

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-requirements-improvement-forseti.life-2
- Generated: 2026-02-22T11:14:53-05:00

---

I have sufficient context. Here is the complete outbox artifact for executor to write to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-requirements-improvement-forseti.life-2/update.md`:

---

- Status: done
- Summary: Completed BA idle cycle for forseti.life (third pass). Ranked 3 candidate requirements improvements by ROI; completed the top-ranked item: a tightened problem statement and draft acceptance criteria for the Diffbot/Web Scraping Job Discovery integration (Flow 11, job_hunter). This is the highest-ROI unblocked spec gap after User Profile Management — without it Dev has no contract for the service interface, the webhook receiver, duplicate detection rules, or the Drupal job-posting save pipeline. The previous two cycles covered the Combat Weapon Coverage Gap and User Profile Management (Flow 7); this artifact closes the third critical spec gap and unblocks the job discovery development track.

## Next actions
- Executor: write this artifact to outbox
- Route to PM for acceptance criteria sign-off (especially questions 1, 3, 4 re: Diffbot plan tier and duplicate detection keying)
- Route Flow 11 dev task to Dev (job_hunter module) after PM sign-off
- Note: Flow 11 depends on Flow 4 (Admin Company Management) being shipped first so companies have `careers_url` populated — coordinate sequencing with PM

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 80
- Rationale: Diffbot/Job Discovery (Flow 11) is the core product loop — without job data ingestion, no user can experience the job matching, tailoring, or application features. Spec is the only remaining blocker before Dev can start; the DIFFBOT_API_KEY environment variable is already documented in the gap analysis, suggesting API access exists or is expected. Closing this spec gap unblocks a 5-7 day dev track running in parallel with Flow 7.

---

## ROI-Ranked Requirements Improvements (3 candidates)

| Rank | Item | Module | ROI | Rationale |
|------|------|--------|-----|-----------|
| 1 ✅ | Diffbot Job Discovery Integration Spec (Flow 11) | job_hunter | 80 | Core product loop; no spec = Dev can't start; API key already expected in env |
| 2 | Job Search & Discovery Dashboard Spec (Flows 3/16) | job_hunter | 65 | Depends on Flow 11 completing first; user-facing job browser needs wireframe + data contract before Frontend can start |
| 3 | Combat Condition Lifecycle Requirements (dungeoncrawler) | dungeoncrawler | 55 | Combat partially shipped; condition application/stacking/removal still has no dedicated requirements doc tying DB schema to state machine behavior |

---

## Requirements Artifact: Job Hunter — Diffbot Web Scraping Job Discovery (Flow 11)

### Problem Statement

The Job Hunter module has no job discovery pipeline. There are no jobs in the system for users to browse, match against, or apply to. The gap analysis identifies Diffbot as the chosen web scraping API and names J&J (`careers.jnj.com`) as the initial target employer — but no service contract, data mapping, webhook spec, duplicate detection rule, or scheduling mechanism has been specified. Without this spec, Dev cannot begin the implementation, and the entire product value chain (discover job → tailor resume → submit application) has no first step.

**Current behavior:** Admin manually creates `job_posting` nodes at `/node/add/job_posting`. No automated discovery. Users see no jobs on any dashboard.  
**Expected behavior:** `DiffbotScrapingService` is invoked (on schedule or manually) → hits Diffbot Analyze/Job API for a given company careers URL → receives structured job data → saves new jobs as `job_posting` nodes → skips duplicates → logs failures to `error_queue`. Users see freshly discovered jobs.

**Downstream dependencies unblocked by this flow:**
- Job Matching Algorithm (requires job nodes to match against)
- Job Display Dashboard (requires job nodes to display)
- Application Tracking (requires jobs to apply to)
- J&J-specific Application Submission (Flow 17)

### Scope

**In scope:**
- `DiffbotScrapingService` PHP service class in the `job_hunter` module
  - Method: `scrapeCompany(int $company_nid): ScrapeResult` — fetches jobs from a company node's `field_careers_url` via Diffbot Job API
  - Method: `saveJobPostings(array $jobs, int $company_nid): array` — creates/updates `job_posting` nodes, returns `[created, skipped, failed]` counts
- Duplicate detection: compare Diffbot job `url` field against existing `job_posting` nodes' `field_application_link`; skip if already exists
- Error handling: on Diffbot API failure, log to `error_queue` with company name, URL, HTTP status, and timestamp
- Manual trigger: Drush command `job_hunter:scrape [company_nid]` for on-demand scraping
- Scheduled run: Drupal cron hook running daily scrape for all active companies (`field_active_scraping = TRUE`)
- Environment variable `DIFFBOT_API_KEY` (already documented in gap analysis) used for authentication
- Initial target: J&J at `careers.jnj.com`

**Non-goals:**
- Automated application submission (Flow 17 — separate spec)
- Job matching algorithm (separate flow)
- User-facing job browse UI (separate spec, depends on this flow)
- Real-time Diffbot webhook receiver (Phase 2; daily cron is MVP)
- Multi-page pagination of careers sites (Phase 2)
- Rate limiting / retry logic beyond single-attempt with error log (Phase 2)

### Definitions

| Term | Definition |
|------|------------|
| Diffbot Job API | Diffbot's structured data extraction API endpoint for job postings; returns normalized JSON with title, description, location, salary, URL, date |
| `DiffbotScrapingService` | New Drupal service class in `job_hunter` module; responsible for all Diffbot API calls |
| `ScrapeResult` | Value object returned by `scrapeCompany()`: `[jobs: array, error: ?string, http_status: int]` |
| Duplicate | A Diffbot-returned job whose `pageUrl` matches an existing `job_posting` node's `field_application_link` |
| Active company | A `company` node with `field_active_scraping = TRUE`; only these are included in scheduled cron runs |
| error_queue | Existing Drupal content type used to log automation failures; fields: user (null for automated), company, error_message, timestamp |

### Key User Flows

**Flow A: Admin-triggered scrape (manual)**
1. Admin runs `drush job_hunter:scrape [company_nid]` (or clicks "Scrape Now" button in company admin — see Gap 2)
2. `DiffbotScrapingService::scrapeCompany($nid)` reads company node's `field_careers_url`
3. Service calls Diffbot Job API: `GET https://api.diffbot.com/v3/analyze?url={careers_url}&token={DIFFBOT_API_KEY}`
4. Diffbot returns JSON array of job objects
5. For each job: check `pageUrl` against existing `job_posting.field_application_link`
6. If new: create `job_posting` node (`field_title`, `field_description`, `field_company` ref, `field_application_link`, `field_location`, `field_salary`, `field_posted_date`, `field_source = diffbot`)
7. If duplicate: skip (no node created, counted in `skipped`)
8. If Diffbot returns error: create `error_queue` entry, return failure result
9. Drush outputs: `Scraped careers.jnj.com: 12 created, 3 skipped, 0 failed`

**Flow B: Scheduled cron scrape**
1. Drupal cron runs `job_hunter_cron()`
2. Query all company nodes where `field_active_scraping = TRUE`
3. For each company: invoke `DiffbotScrapingService::scrapeCompany($nid)`
4. Aggregate results; log summary to Drupal watchdog

### Acceptance Criteria (Draft — PM to finalize)

**Happy path:**
- AC1: `DiffbotScrapingService::scrapeCompany($nid)` called with J&J company node returns at least 1 job object from Diffbot and creates at least 1 `job_posting` node with `field_company` referencing the J&J company node.
- AC2: Running `drush job_hunter:scrape [jnj_nid]` a second time on the same company does not create duplicate `job_posting` nodes for jobs already in the system (duplicate detection by `field_application_link`).
- AC3: Created `job_posting` nodes have non-empty values for `field_title`, `field_description`, `field_application_link`, and `field_company`.
- AC4: `job_hunter_cron()` processes all companies with `field_active_scraping = TRUE` and does not throw unhandled exceptions.
- AC5: `DIFFBOT_API_KEY` is read from environment (not hardcoded); if key is missing, service throws a `\RuntimeException` with message `"DIFFBOT_API_KEY environment variable not set"`.

**Failure modes:**
- AC6: If Diffbot returns a non-200 HTTP status for a company URL, a new `error_queue` node is created with the company name, URL, HTTP status code, and current timestamp. No exception bubbles up to crash cron.
- AC7: If a Diffbot job object is missing `pageUrl`, the job is skipped (not saved) and a watchdog warning is logged: `"Diffbot job missing pageUrl for company {nid}, title: {title}"`.
- AC8: If `field_careers_url` is empty on the company node, service returns a `ScrapeResult` with `error: "Company has no careers URL"` and does not call Diffbot.

**Verification method:**
- PHPUnit: `DiffbotScrapingServiceTest` with mocked HTTP client — assert node creation, duplicate skip, error queue creation.
- Drush integration test: `drush job_hunter:scrape [jnj_nid]` against staging with real Diffbot key; assert `job_posting` nodes appear at `/admin/content` filtered by type.
- Manual: Navigate to admin content list; confirm J&J job postings exist with correct company reference, title, description, and application link URL.

### Data Mapping: Diffbot Response → Drupal Fields

| Diffbot Field | Drupal Field | Notes |
|---|---|---|
| `title` | `title` (node title) | Required |
| `text` | `field_description` (long text) | Fallback to `summary` if missing |
| `pageUrl` | `field_application_link` (link) | Used for duplicate detection key |
| `location[0].text` | `field_location` (text) | First location only for MVP |
| `estimatedDate` | `field_posted_date` (date) | ISO 8601 string |
| `salary` | `field_salary` (text) | Raw string; not parsed for MVP |
| _(company nid)_ | `field_company` (entity ref) | Passed in from caller |
| `"diffbot"` | `field_source` (list) | Constant — distinguish from manual entries |

### Assumptions

1. Diffbot Job API endpoint is `https://api.diffbot.com/v3/analyze?url={url}&token={key}`; if Diffbot uses a different job-specific endpoint, Dev must confirm (clarifying question 1).
2. `field_careers_url`, `field_active_scraping`, `field_source` fields already exist on company/job_posting content types; if not, Dev must add them as part of this task.
3. The existing `error_queue` content type has sufficient fields to log automated (non-user-triggered) errors (null user reference).
4. Drupal HTTP client (`\GuzzleHttp\Client` or Drupal's `http_client` service) is the transport layer; no additional HTTP library needed.
5. J&J careers page (`careers.jnj.com`) is publicly accessible without authentication.

### 3–5 Clarifying Questions for Stakeholders

1. **Diffbot API endpoint and plan tier:** Is the correct endpoint `https://api.diffbot.com/v3/analyze` or the dedicated `/v3/job` endpoint? Does the current Diffbot plan support job extraction specifically, or is generic Analyze being used? This affects the response schema shape and field names.
2. **Duplicate detection key:** The spec uses `pageUrl` (individual job page URL) as the duplicate key. Is this correct, or should we use a composite of `(company_nid, job_title, posted_date)` to handle jobs that get new URLs when re-posted? (Tradeoff: URL-keying is simple but misses re-posts; composite-keying avoids re-post duplicates but may miss genuine re-listings.)
3. **Cron frequency and rate limiting:** Should cron scrape all active companies daily (simple) or stagger across the day to avoid Diffbot rate limits? How many companies are expected in the active set at MVP launch — just J&J, or multiple?
4. **`field_careers_url` location:** Is the careers URL stored on the `company` Drupal node, or on a separate configuration entity? The gap analysis shows company node admin interface (Gap 2/Flow 4) as a separate task — is it safe to assume company nodes have a `field_careers_url` field already, or must Flow 4 ship first?
5. **Failed job handling:** If a cron run partially fails (5 jobs saved, then API error), should already-created nodes be kept (partial success) or rolled back in a transaction? MVP recommendation is partial success with error log, but PM should confirm.

### Delegation Table

| Role | Action |
|------|--------|
| **PM** | Finalize AC1–AC8; answer clarifying questions 1–5; confirm `field_careers_url` exists on company node before Dev starts |
| **Dev (job_hunter)** | Implement `DiffbotScrapingService`, Drush command, cron hook, and data mapping per spec; ensure `field_source` field exists on job_posting |
| **QA** | Run Drush scrape against staging with real key; verify job nodes created; run duplicate-scrape test (AC2); verify error_queue entry on bad URL (AC6) |
