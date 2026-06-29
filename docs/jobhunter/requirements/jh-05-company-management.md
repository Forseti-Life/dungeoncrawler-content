# JobHunter Requirements — JH-05: Company Management & Research Automation

**Status summary:** 3 of 6 requirements COMPLETED, 0 PARTIAL, 3 GAP  
**Last updated:** 2026-02-27  
**Module:** `job_hunter` (forseti.life)

## Overview

This subsystem stores company records as Drupal nodes, allows the user to designate target companies, and holds the design for a planned 8-step company research automation pipeline (careers page discovery → ATS detection → authentication analysis). The company node structure and target-company UI are implemented; all three research automation services are fully designed but have no code written.

## Requirements

### REQ-05.1 — Company node storage

**Status:** COMPLETED  
**Code path:** `company` content type, `src/Form/CompanyForm.php`, `jobhunter_companies` table

- Companies must be stored as Drupal nodes of type `company`.
- The company node must include the following fields: name, website URL, careers page URL, description, industry, size, active (boolean), location, and logo (file reference).
- The `jobhunter_companies` custom table may store supplemental scraping metadata not suited to the node field model.
- Companies must be manageable via the company form at the standard Drupal node edit path and via the company list view.

---

### REQ-05.2 — Target company designation

**Status:** COMPLETED  
**Code path:** `src/Controller/CompanyController.php`, target-companies view

- The user must be able to designate one or more companies as "target companies" for prioritized job discovery.
- Target company status must be stored per user.
- The target companies interface must be accessible within the JobHunter navigation.

---

### REQ-05.3 — Company-specific scraping configuration

**Status:** COMPLETED  
**Code path:** `src/Service/AbbVieJobScrapingService.php`, `jobhunter_companies` table

- The system must store per-company scraping configuration (e.g., careers page URL, ATS type, request headers) to enable employer-specific job discovery.
- Configuration must be editable by administrators.
- AbbVie is the reference implementation demonstrating this configuration pattern.

---

### REQ-05.4 — Careers page discovery automation

**Status:** GAP  
**Code path:** N/A (design in `docs/COMPANY_RESEARCH_PATH_DESIGN.md`)

- The system must be able to determine a company's careers page URL from the company name alone, without manual entry, using a `CareersPageDiscoveryService`.
- The discovery process must use web search and/or the company's primary domain to locate the careers URL.
- Discovery results must be stored back to the company record.
- **GAP:** This service is fully designed (step 1–2 of the 8-step research pipeline) but no code has been written.

---

### REQ-05.5 — ATS detection automation

**Status:** GAP  
**Code path:** N/A (design in `docs/COMPANY_RESEARCH_PATH_DESIGN.md`)

- The system must automatically detect which Applicant Tracking System (ATS) a company uses (e.g., Workday, Greenhouse, Taleo, Lever, iCIMS) by inspecting the careers page.
- The detected ATS type must be stored on the company record.
- ATS detection must support an extensible list of known ATS fingerprints.
- **GAP:** This service is fully designed (step 3–4 of the 8-step research pipeline) but no code has been written.

---

### REQ-05.6 — Authentication analysis automation

**Status:** GAP  
**Code path:** N/A (design in `docs/COMPANY_RESEARCH_PATH_DESIGN.md`)

- The system must analyze the authentication requirements for submitting an application at a company (e.g., account required, OAuth provider, guest application supported).
- Authentication analysis results must be stored on the company record and used by `ApplicationSubmissionService` to select the correct submission strategy.
- **GAP:** This service is fully designed (step 5–8 of the 8-step research pipeline) but no code has been written.

---

## Known Gaps

- `CareersPageDiscoveryService` — designed, no code written (REQ-05.4). HIGH implementation priority.
- `ATSDetectionService` — designed, no code written (REQ-05.5). Required before application submission automation can proceed.
- `AuthenticationAnalysisService` — designed, no code written (REQ-05.6). Required before portal-specific submission can proceed.
- Bulk company import (`BulkCompanyImportForm`) exists but its integration with the research pipeline is not defined.
- No company research UI exists; `CompanyResearchController.php` is a stub.

## Test Coverage

No unit tests exist for company management or the planned research automation services. Estimated coverage: **0%** for this subsystem. Once `CareersPageDiscoveryService` and `ATSDetectionService` are implemented, they should be unit-tested with mocked HTTP responses before any live web requests are made.
