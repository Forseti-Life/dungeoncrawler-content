# Outbox update: 20260222-idle-requirements-improvement-forseti.life-6

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-requirements-improvement-forseti.life-6
- Generated: 2026-02-22T12:33:19-05:00

---

I have enough context. The highest-ROI remaining independent spec is the User-Facing Company Selection UI (Flow 8/Gap 4) — it is the user-side complement to the admin Company Management specced in cycle 4, and it gates user-personalized job matching (the system needs to know which companies a user cares about before relevance scoring can work). Here is the complete outbox artifact:

---

- Status: done
- Summary: Completed BA idle cycle for forseti.life (seventh pass). Selected User-Facing Company Selection UI (Flow 8/Gap 4, job_hunter) as the top-ROI item: it is the user-side complement to the Admin Company Management spec produced in cycle 4 and is a direct prerequisite for personalized job matching — the system needs to know which companies each user is targeting before relevance scoring or job display can be personalised. The content type already exists; this is a pure UI spec gap estimated at 3-4 days with no dependency other than admin-seeded company nodes. Full requirements artifact follows.

## Next actions
- Executor: write this artifact to outbox
- Route to PM for acceptance criteria sign-off (especially Q1 on monitoring granularity and Q3 on public company page visibility)
- Route Flow 8 dev task to Dev (job_hunter module) after PM sign-off
- Note: ideally ships after Flow 4 (Admin Company Management) so company nodes with `field_careers_url` are already seeded by admin before users select them

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 50
- Rationale: User company selection is the personalisation input for job matching — without it all users see the same undifferentiated job list and relevance scoring has no target set to compute against. It is a 3-4 day standalone UI task; the only soft dependency is admin company nodes being present (Flow 4 spec already done).

---

## ROI-Ranked Requirements Improvements (3 candidates)

| Rank | Item | Module | ROI | Rationale |
|------|------|--------|-----|-----------|
| 1 ✅ | User-Facing Company Selection UI (Flow 8) | job_hunter | 50 | Personalisation input for matching; standalone 3-4 day task; soft dep on Flow 4 admin side already specced |
| 2 | Job Search & Discovery Dashboard (Flows 3/16) | job_hunter | 45 | Depends on Diffbot (Flow 11) + company selection (Flow 8) shipping first; spec can be prepared for parallel track |
| 3 | Dungeoncrawler Level-Up Wizard Requirements | dungeoncrawler | 40 | PR-06 design doc exists but verification notes flag partial implementation; `SchemaLoader::getClassData()` is a known TODO; spec gap between target design and actual routing surface |

---

## Requirements Artifact: Job Hunter — User-Facing Company Selection UI (Flow 8)

### Problem Statement

The Job Hunter module has company nodes seeded by admin (specced in Flow 4), but users have no interface to declare which companies they are targeting. Without this selection, the job matching algorithm has no company scope to narrow relevance scoring against, and the job discovery dashboard would return an unfiltered fire-hose of all jobs across all companies.

The user-company relationship is a many-to-many: one user targets many companies; one company is targeted by many users. This relationship does not yet exist as a data entity, and there is no UI to create or manage it.

**Current behavior:** User logs in → sees no company-related UI → all jobs from all companies (once discovered) would be shown indiscriminately with no personalization signal.  
**Expected behavior:** User navigates to `/user/{uid}/companies` → browses a list of available companies → selects companies they're targeting → sees "My Companies" panel on dashboard → can pause/resume monitoring per company → job discovery and matching uses their company set as the primary scope.

### Scope

**In scope:**
- Company browse page at `/companies`: public list of all active companies with Name, Industry, Website, Job Count (# of current job_posting nodes)
- User company selection: authenticated users can click "Follow" / "Unfollow" on any company; creates/removes a `user_company` relationship record
- "My Companies" panel at `/user/{uid}/companies`: list of followed companies with columns = Company Name, Active Monitoring (toggle), Job Count, Last Scraped, Actions (Unfollow / Pause/Resume)
- Active Monitoring toggle per company: per-user boolean (`field_user_monitoring_active`); when OFF, this company's jobs are excluded from the user's job dashboard; cron scraping is unaffected (admin-controlled separately)
- Company detail page at `/companies/{company-name}`: Company name, website, industry, careers URL display, job count, link to browse open job postings for that company
- Dashboard widget on `/user/{uid}`: "My Companies: 3 followed, 2 active" with link to company list
- Search/filter on `/companies`: by name (text search), industry (select)

**Non-goals:**
- Admin company create/edit/scrape interface (specced in Flow 4 — separate task)
- Per-user careers URL override (admin owns scraping config)
- Company application statistics on the user-facing page (Phase 2)
- Company recommendations / suggested follows (Phase 2)
- Anonymous user access to company detail or job lists (MVP: authenticated only)

### Definitions

| Term | Definition |
|------|------------|
| `user_company` relationship | Junction record linking a `user` entity to a `company` node; fields: `uid`, `company_nid`, `monitoring_active` (boolean), `followed_at` (datetime) |
| Follow | User action that creates a `user_company` record for the current user and a given company |
| Unfollow | User action that deletes the `user_company` record |
| Active Monitoring | Per-user toggle (not per-company) controlling whether this company's jobs appear in the user's job dashboard; does not affect cron scraping |
| Job Count | Count of published `job_posting` nodes referencing the company via `field_company` |
| Last Scraped | Value of `field_last_scraped` on the company node (admin-owned, read-only for users) |

### Key User Flows

**Flow A: User browses and follows companies**
1. User navigates to `/companies`
2. Sees paginated list: J&J, Merck, Pfizer… with Industry, Job Count columns
3. User clicks "Follow" next to J&J → AJAX button toggles to "Following"; `user_company` record created
4. User navigates to `/user/{uid}/companies` → sees J&J listed with Active Monitoring = ON (default)
5. Dashboard widget updates: "My Companies: 1 followed, 1 active"

**Flow B: User pauses monitoring for a company**
1. User receives offer from J&J; wants to pause monitoring while negotiating
2. Navigates to `/user/{uid}/companies` → clicks toggle next to J&J: "Active: ON → OFF"
3. `field_user_monitoring_active` set to FALSE for this user-company record
4. J&J jobs no longer appear in user's job discovery dashboard (`/user/{uid}/jobs`)
5. Dashboard widget: "My Companies: 1 followed, 0 active"
6. User can re-enable any time by toggling back to ON

**Flow C: User views company detail**
1. User clicks J&J company name from list → navigates to `/companies/johnson-johnson`
2. Sees: Company name, website link, industry, "12 open positions" count
3. Sees "Follow" button (if not already following) or "Following ✓" badge
4. Sees link: "Browse 12 open jobs at J&J" → links to `/user/{uid}/jobs?company=jnj_nid`

### Acceptance Criteria (Draft — PM to finalize)

**Happy path:**
- AC1: `/companies` displays all company nodes with `field_active_scraping = TRUE` (admin-enabled), with Name, Industry, and Job Count columns; paginated at 20 per page.
- AC2: Authenticated user clicking "Follow" on a company creates a `user_company` record and updates the button to "Following"; no page reload required (AJAX).
- AC3: `/user/{uid}/companies` lists all companies the current user is following, with Active Monitoring toggle, Job Count, and Last Scraped columns.
- AC4: Toggling Active Monitoring OFF for a company updates `field_user_monitoring_active = FALSE` inline (AJAX); the company no longer appears in the user's job discovery results at `/user/{uid}/jobs`.
- AC5: Company detail at `/companies/{slug}` displays company name, website, industry, job count, and Follow/Following button.
- AC6: Dashboard at `/user/{uid}` shows "My Companies" widget with followed count and active count, linking to `/user/{uid}/companies`.

**Failure modes:**
- AC7: Anonymous users navigating to `/companies` see the public list but the "Follow" button is replaced by "Log in to follow"; clicking redirects to `/user/login`.
- AC8: Authenticated user navigating to `/user/{other_uid}/companies` receives HTTP 403.
- AC9: A company with 0 job postings shows "0 open positions" on both the list and detail page — not a blank or missing field.
- AC10: If a user unfollows a company and re-follows it, monitoring is re-enabled by default (Active Monitoring = ON on new follow).

**Verification method:**
- PHPUnit: `UserCompanySelectionTest` — assert follow creates `user_company` record; assert unfollow removes it; assert monitoring toggle updates `field_user_monitoring_active`.
- PHPUnit: `CompanyAccessTest` — assert 403 on cross-user company list route.
- Manual: Follow J&J as test user; navigate to `/user/{uid}/companies`; confirm J&J appears; toggle monitoring OFF; navigate to `/user/{uid}/jobs` (once job dashboard exists); confirm J&J jobs absent; toggle back ON; confirm reappearance.
- Manual: Confirm anonymous user sees "Log in to follow" at `/companies`.

### Data Design: `user_company` Relationship

The junction between users and companies does not currently exist. Options:

| Option | Implementation | Tradeoff |
|---|---|---|
| A: Custom DB table `job_hunter_user_companies` (uid, company_nid, monitoring_active, followed_at) | Custom Drupal schema | Fast queries, explicit control, requires migration |
| B: Entity Reference field `field_followed_companies` on user entity (multi-value) | Drupal core field | Simpler, but `monitoring_active` per-company is awkward to store per-value |
| C: Flag module (Drupal contrib) | Community pattern for bookmarking | Adds contrib dependency; handles AJAX/UI automatically |

**Recommendation:** Option A (custom table) for MVP — clean schema, explicit `monitoring_active` boolean per row, easy to query for job matching algorithm. Dev should confirm before starting.

### Assumptions

1. Company nodes have `field_industry`, `field_website` fields (or equivalent) for display on the browse page; if missing, Dev adds them as part of this task.
2. A URL alias system (Pathauto or manual) generates `/companies/{slug}` from the company node title.
3. The job matching algorithm (future) will query `job_hunter_user_companies` filtered by `monitoring_active = TRUE` for the current user to scope job discovery results.
4. `Last Scraped` is read from the company node's `field_last_scraped` (set by `DiffbotScrapingService` — Flow 11); if Flow 11 hasn't shipped yet, this column shows "Never".

### 3–5 Clarifying Questions for Stakeholders

1. **Monitoring granularity — user vs. admin:** The current spec has both an admin `field_active_scraping` toggle (controls whether cron scrapes the company at all) and a per-user `monitoring_active` toggle (controls whether the company appears in that user's dashboard). Is this two-tier design correct, or should user monitoring toggle also influence scraping priority? (Recommendation: keep them independent — admin controls data collection; user controls personalization.)
2. **Maximum companies per user:** Should there be a limit on how many companies a user can follow (e.g., max 10 at MVP to constrain matching algorithm load), or is it unlimited? This affects UI (warning when approaching limit vs. none) and backend query performance.
3. **Public vs. authenticated company page:** Should `/companies` be publicly browsable by anonymous users (SEO benefit, discovery), or authenticated-only? The spec currently shows public list with gated Follow. PM should confirm.
4. **`user_company` storage approach:** Confirm Option A (custom table) vs. B (entity reference field) vs. C (Flag module) — Dev preference matters here but PM should be aware of the tradeoff. Custom table is cleanest for querying in the matching algorithm.
5. **Company slug/URL:** Should company detail pages use the node NID in the URL (`/companies/42`) or a human-readable slug (`/companies/johnson-johnson`)? Recommendation: human-readable slug via Pathauto, but requires Pathauto module to be enabled.

### Delegation Table

| Role | Action |
|------|--------|
| **PM** | Finalize AC1–AC10; answer clarifying questions 1–5; confirm company fields (industry, website) exist; confirm public vs. authenticated company list |
| **Dev (job_hunter)** | Create `job_hunter_user_companies` table + schema; build `/companies` browse page and AJAX follow/unfollow; build `/user/{uid}/companies` management page with monitoring toggle; add dashboard widget |
| **QA** | Follow/unfollow J&J as test user; verify database record created/deleted; toggle monitoring; verify 403 on cross-user route; verify anonymous "Log in to follow" behavior |
