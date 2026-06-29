# JobHunter Requirements — JH-02: Job Discovery & Multi-Source Search

**Status summary:** 6 of 9 requirements COMPLETED, 2 PARTIAL, 1 GAP  
**Last updated:** 2026-02-27  
**Module:** `job_hunter` (forseti.life)

## Overview

This subsystem enables the user to discover job postings from multiple external sources — SerpAPI (Google Jobs), Adzuna, USAJobs, and Google Cloud Talent Solution — and aggregate the results into a unified list. It also supports per-employer job scraping (using AbbVie as the reference implementation) and allows the user to save discovered jobs for further processing.

## Requirements

### REQ-02.1 — SerpAPI / Google Jobs search

**Status:** COMPLETED  
**Code path:** `src/Service/SerpApiService.php`, `src/Service/JobDiscoveryService.php`

- The system must support job search via SerpAPI using the Google Jobs engine.
- Queries must accept at minimum: keywords, location, and date-posted filters.
- Raw results from SerpAPI must be normalized before storage or display.
- The SerpAPI key must be retrieved via `CredentialManagementService` (not hard-coded).

---

### REQ-02.2 — Adzuna API search

**Status:** COMPLETED  
**Code path:** `src/Service/AdzunaApiService.php`

- The system must support job search via the Adzuna API.
- Queries must accept at minimum: keywords and location.
- Adzuna credentials (app ID and API key) must be retrieved via `CredentialManagementService`.
- Results must be normalized to the common job schema before aggregation.

---

### REQ-02.3 — USAJobs API search

**Status:** COMPLETED  
**Code path:** `src/Service/UsaJobsApiService.php`

- The system must support job search via the USAJobs API (federal positions).
- Queries must accept at minimum: keywords and location/agency filters.
- The USAJobs API key must be retrieved via `CredentialManagementService`.
- Results must be normalized to the common job schema before aggregation.

---

### REQ-02.4 — Google Cloud Talent Solution search

**Status:** COMPLETED  
**Code path:** `src/Service/CloudTalentSolutionService.php`

- The system must support job search via Google Cloud Talent Solution.
- Queries must accept at minimum: keywords and location.
- Google Cloud credentials must be retrieved via `CredentialManagementService`.
- Results must be normalized to the common job schema before aggregation.

---

### REQ-02.5 — Result normalization and aggregation

**Status:** COMPLETED  
**Code path:** `src/Service/SearchAggregatorService.php`

- `SearchAggregatorService` must normalize results from all configured sources into a common schema, including at minimum: job title, employer name, location, description, posting URL, source identifier, and posted date.
- The aggregator must be callable from both the AJAX search endpoint (`/jobhunter/job-discovery/search`) and company-specific search (`/jobhunter/job-discovery/company/{company}`).
- Results must be renderable in a unified list regardless of source.

---

### REQ-02.6 — Save a discovered job

**Status:** COMPLETED  
**Code path:** `src/Controller/JobApplicationController.php`, route `job_hunter.save_job`

- The user must be able to save a discovered job posting to their job list via the save endpoint (`/jobhunter/job-discovery/save`).
- Saving a job must create a `job_posting` node associated with the authenticated user.
- The save action must be triggerable from the search results UI without a full page reload (AJAX).
- **Security note:** The save route currently lacks CSRF protection (see JH-08 REQ-08.1).

---

### REQ-02.7 — Job deduplication

**Status:** PARTIAL  
**Code path:** `src/Service/SearchAggregatorService.php`

- The system must detect and suppress duplicate job postings when aggregating results from multiple sources.
- Deduplication must be based on a combination of employer name, job title, and posting URL.
- **GAP (partial):** Deduplication logic exists in the aggregator but has not been validated against real cross-source duplicates; edge cases (e.g., same job listed on multiple boards with different URLs) are not handled.

---

### REQ-02.8 — Per-employer scraping framework

**Status:** PARTIAL  
**Code path:** `src/Service/AbbVieJobScrapingService.php`

- The system must provide an extensible per-employer job scraping framework.
- `AbbVieJobScrapingService` serves as the reference implementation; additional employer scrapers must follow the same interface pattern.
- Company-specific searches must be accessible at `/jobhunter/job-discovery/company/{company}`.
- **GAP (partial):** Only AbbVie is implemented; no abstract base class or documented interface contract exists to guide future employer implementations.

---

### REQ-02.9 — Search history persistence

**Status:** GAP  
**Code path:** N/A

- The system must store a history of searches performed by the user (query terms, source, timestamp, result count).
- Search history must be viewable by the user and by administrators.
- **GAP:** No search history table or persistence logic has been implemented.

---

## Known Gaps

- Search history is not persisted (REQ-02.9).
- Job deduplication handles only simple cases; cross-source deduplication by canonical job URL is not implemented (REQ-02.7).
- The per-employer scraping framework lacks a formal interface/abstract class; AbbVie is the only implementation (REQ-02.8).
- The save-job route (`job_hunter.save_job`) is missing CSRF protection (security gap tracked in JH-08).
- `JobPostingParsingWorker` exists in the queue directory but its integration with the discovery pipeline is not documented.

## Test Coverage

No unit tests exist for `SerpApiService`, `AdzunaApiService`, `UsaJobsApiService`, `CloudTalentSolutionService`, `SearchAggregatorService`, or `AbbVieJobScrapingService`. Estimated coverage: **0%** for this subsystem. Priority test targets: `SearchAggregatorService` normalization and deduplication logic.

---

### REQ-02.10 — remote_preference enum canonicalization

**Status:** GAP
**Code path:** `job_hunter.install`, `src/Service/CloudTalentSolutionService.php`, `src/Service/JobSeekerService.php`, `src/Service/JobDiscoveryService.php`, `ARCHITECTURE.md`

- A single canonical set of `remote_preference` values must be defined and used consistently across all service, schema, template, and documentation files.
- Canonical values (proposed): `remote`, `hybrid`, `onsite`, `any` — matching the database schema in `job_hunter.install`.
- A `JobHunterConstants` class (or equivalent) must define these values as named constants; no string literal spellings of remote preference values are permitted outside this class.
- `CloudTalentSolutionService` must map all 4 canonical values to Google Cloud Talent API equivalents; the `any` value must not be silently excluded from search queries.
- `ARCHITECTURE.md`, all templates, and all service methods must use the canonical spellings.

**Acceptance criteria:**
1. A `JobHunterConstants::REMOTE_PREFERENCE_*` set of constants exists and is used in all call sites.
2. A search with `remote_preference = any` returns results from Google Cloud Talent (not an empty set).
3. `grep -r '"remote"\|"hybrid"\|"onsite"\|"any"' web/modules/custom/job_hunter/src/` returns only the constants definition file.
4. QA verifies by selecting "No Preference" in the job discovery UI and confirming results are returned.

**Added by:** ba-forseti (2026-02-27) — gap candidate from CC-002
