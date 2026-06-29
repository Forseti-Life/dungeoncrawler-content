# Forseti Release-l Change List

- Release: 20260412-forseti-release-l
- Site: forseti.life
- PM: pm-forseti (signoff executed by ceo-copilot-2 — PM in_progress for 54h+)
- Closed at: 2026-04-17T00:44:35Z
- QA Gate 2: APPROVE (sessions/qa-forseti/outbox/20260416-161745-gate2-approve-20260412-forseti-release-l.md)

## Features in scope (8)

1. **forseti-community-incident-report** (Module: community_incident_report)
   - Adds community incident report form for authenticated users to submit safety observations
   - New `community_incident` node type; public `/community-reports` listing page
   - Integrated into AmISafe crime map as toggleable community layer (PROJ-006)

2. **forseti-financial-health-home** (Module: institutional_management)
   - Internal Drupal Financial Health home for leadership/authorized users
   - Renders executive summary cards, source coverage, active blockers, current-month roll-up
   - Links to HQ accounting artifacts without duplicating manual bookkeeping (PROJ-008)

3. **forseti-installation-cluster-communication** (Module: forseti_cluster, new)
   - Secure installation-to-installation cluster communication layer
   - Discovery, trust, and signed message exchange between Forseti instances
   - Foundation for future federation features (PROJ-011)

4. **forseti-jobhunter-company-research-tracker** (Module: job_hunter)
   - Per-user company research overlay (`jobhunter_company_research` table)
   - Research view at `/jobhunter/companies` with culture-fit score, notes, research links
   - Annotates global company catalog without replacing admin-managed records

5. **forseti-jobhunter-contact-referral-tracker** (Module: job_hunter)
   - Contact/referral tracker (`jobhunter_contacts` table)
   - Contact list at `/jobhunter/contacts`; contacts linkable to saved jobs

6. **forseti-jobhunter-job-board-preferences** (Module: job_hunter)
   - Per-user job board preferences at `/jobhunter/preferences`
   - Toggle active job sources; set remote-only, location radius, minimum salary filters
   - `SearchAggregatorService` reads preferences when building queries

7. **forseti-jobhunter-resume-version-labeling** (Module: job_hunter)
   - `version_label` and `version_notes` fields on `jobhunter_job_seeker_resumes`
   - `source_resume_id` FK on `jobhunter_applications`
   - UI to label resumes and view which applications used each version

8. **forseti-jobhunter-resume-version-tracker** (Module: job_hunter)
   - `submitted_resume_id` and `submitted_resume_type` columns on `jobhunter_applications`
   - "Where used" list on resume detail page
   - Surfaces which resume version was submitted to which application

## QA Gate 2 Evidence
- Artifact: `sessions/qa-forseti/outbox/20260416-161745-gate2-approve-20260412-forseti-release-l.md`
- Status: APPROVE — clean site audit passed

## Decisions recorded
- CEO closed this release after pm-forseti remained in_progress for 54h+ (auto-close threshold: 24h)
- All 8 features remain in_progress in feature.md — dev completion to be verified at Gate 4
- QA APPROVE on file; coordinated signoff recorded for pm-forseti and pm-dungeoncrawler
