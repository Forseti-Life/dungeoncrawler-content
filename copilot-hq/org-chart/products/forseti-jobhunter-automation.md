# Forseti Jobhunter & Application Automation

## Status
- **State:** active product slice on `forseti.life`
- **Primary seats:** `pm-forseti`, `ba-forseti`, `dev-forseti`, `qa-forseti`
- **Primary modules:** `job_hunter`, `jobhunter_tester`, `company_research`

## Product summary
This project covers Forseti's jobseeker workflow and application automation system. It is already well beyond concept stage: resume ingestion, AI parsing, consolidated profile storage, job discovery, company research, resume tailoring, application tracking, and tester dashboards are all present in the current system.

The core product intent is to help a job seeker move from resume/profile setup to discovery, matching, tailoring, and ultimately application submission with as much safe automation as the employer platform allows.

## Existing system in place

### Canonical implementation references
- `../sites/forseti/web/modules/custom/job_hunter/README.md`
- `../sites/forseti/web/modules/custom/jobhunter_tester/README.md`
- `../sites/forseti/web/modules/custom/company_research/company_research.info.yml`

### Existing user-facing scope
- Resume upload, extraction, JSON parsing, and consolidated profile workflow
- Job discovery and external search integrations
- Company research and ATS/platform readiness analysis
- Resume tailoring and application-preparation flows
- Application status tracking, saved jobs, and dashboard views
- Admin/error-queue support for automation failure handling
- Tester dashboards for route and unit-test visibility

### Existing route/domain footprint
- `/jobhunter/settings`
- `/jobhunter/job-discovery`
- `/jobhunter/companyresearch`
- `/jobhunter/tailor-resume/{job_id}`
- `/jobhunter/status`
- `/jobhunter/deadlines`
- `/jobhunter_testing`
- `/jobhunter_testing/unit-tests`

## High-level requirements
1. **Profile and resume intelligence**
   - Users can upload and maintain source resumes.
   - The system produces structured job seeker data that can be reused across discovery, tailoring, and submission.
   - Consolidated profile data remains the durable source for workflow decisions and prefill.

2. **Discovery and matching**
   - Users can search across Forseti and external job sources from a unified interface.
   - Jobs can be filtered, saved, and revisited without losing source provenance.
   - Matching, urgency, and recommendation logic should remain explainable to the user.

3. **Application automation**
   - The platform should automate as much of the application workflow as safely possible.
   - Automation readiness must be explicit per employer/platform.
   - When full submission cannot be automated, the user still gets a tailored resume/application package and a clear manual next step.

4. **Operational reliability**
   - Queue workers, API integrations, and AI parsing/tailoring flows must fail visibly, not silently.
   - Admins need actionable diagnostics for failed automation runs.
   - Existing tester coverage and route validation remain part of the product, not an optional add-on.

5. **User trust and data safety**
   - Resume/profile/application data remains preserved and recoverable.
   - Credential handling, AI calls, and application submission steps must stay security-reviewed and auditable.
   - The product should never hide whether an outcome was automated, partially automated, or manual.

## Near-term planning posture
- Treat this as a **mature product stream**, not a greenfield effort.
- Prioritize workflow completion, automation reliability, observability, and UX continuity over broad new-surface invention.
- New work should build from the existing routes, data model, queue workers, and tester dashboards already in place.
