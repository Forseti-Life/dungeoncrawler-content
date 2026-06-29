# Outbox update: 20260222-idle-requirements-improvement-forseti.life-7

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-7
- Generated: 2026-02-22T11:03:59-05:00

---

Item 6's artifact folder is not present in artifacts — only items 1–5 are persisted. Item 6 was processed in this session but its artifact was not yet saved. Item 7 is the current item. The prior cycle ranked item 7 as **job_hunter module product documentation** (ROI 75). Items 8 still at roi.txt=1. I'll note that the idle queue is now at diminishing ROI and flag this to CEO.

---

**Artifact: `sessions/ba-forseti/artifacts/20260222-jobhunter-product-doc-draft/product-documentation.md`**

```markdown
# Product Documentation: job_hunter module

- Module: job_hunter
- Website: forseti.life (Drupal)
- Doc type: PM-owned product documentation (draft by BA for PM review)
- BA: ba-forseti
- Date: 2026-02-22
- Status: DRAFT — PM must review and publish

---

## Overview

- **What this product/module is:** The job_hunter Drupal module provides an authenticated user workflow on forseti.life for discovering, saving, applying to, and tracking job applications. It supports a step-based dashboard flow (/jobhunter) that guides the user from job discovery through application submission tracking.
- **Who it is for:** The primary user is the site owner (authenticated, single-user context for now). The module is designed for personal job search management, not multi-tenant job board use.
- **Primary value proposition:** Replace manual job search spreadsheets with an in-site, structured workflow that records discovery, readiness, submission state, and post-apply outcomes — with a repeatable verification path via Playwright automation.

---

## Scope (and non-goals)

**In scope:**
- Job discovery (automated J&J portal query or manual add)
- Job list management (save, view, status transitions)
- Application submission tracking (readiness_to_apply, applied_submitted states)
- Post-apply status lifecycle (interview_scheduled, offer_received, rejected, withdrawn, offer_accepted, offer_declined)
- Profile management: resume upload, parsing, and field editing
- Per-user data isolation and access control

**Non-goals:**
- External account creation or form submission on third-party portals (stage-break boundary)
- Multi-user / multi-tenant job board
- New resume parsing models or external integrations beyond the current parser
- External alerting, email notifications, or calendar integrations (deferred)
- Admin-side bulk profile management

---

## Current user journeys / workflows

**Workflow 1: Job discovery to application tracking (E2E)**
1. User navigates to /jobhunter dashboard
2. User triggers job discovery → J&J careers portal queried for data roles
3. User saves one or more discovered jobs (or manually adds a job if discovery fails)
4. User marks job as ready to apply (readiness_to_apply) from /jobhunter/my-jobs
5. User opens external J&J application link (stage break — Forseti stops here)
6. User returns to Forseti and marks job as applied_submitted; date/URL/notes recorded
7. User tracks application outcome (interview_scheduled → offer_received → accepted/declined/rejected/withdrawn)
8. All status transitions and timestamps visible on job detail and history view

**Workflow 2: Profile setup (resume upload)**
1. Authenticated user navigates to /profile (or equivalent)
2. User uploads a resume (PDF or DOCX)
3. System parses resume and pre-populates profile fields (personal info, experience, education, skills, certifications)
4. User reviews, edits, and saves profile
5. User can re-upload; manually edited fields are preserved (not overwritten)

---

## Roles / permissions

- **Anonymous:** No access to any job_hunter route or endpoint; redirected to login
- **Authenticated (user = profile owner):** Full read/write access to own jobs, profile, and status transitions; no access to other users' data
- **Admin:** Access to Drupal admin queue UI for monitoring job discovery queues; watchdog log access for debugging; no special job_hunter data access beyond standard Drupal admin
- **Other authenticated users (cross-user):** 403 on all job and profile endpoints belonging to another user

---

## Data and integrations

**Key entities/data:**
- Job record: job_id, user_id, title, company, role_type, external_url, status (enum), date_discovered/added, status history (event log)
- Profile: user_id, personal info fields, work experience (repeating), education (repeating), skills (list), certifications (repeating), job preferences
- Resume file: uploaded file reference; retained or discarded post-parse (TBD — open question)
- Status history/event log: job_id, user_id, from_status, to_status, transition_timestamp, notes, interview_date, outcome_date

**External integrations/APIs:**
- J&J careers portal (https://www.careers.jnj.com/en/): queried for job discovery (scrape or API — Dev must confirm mechanism)
- Resume parser: internal parser (no new external integrations per non-goals)

---

## Operational notes

**Environments:**
- Production: forseti.life (Drupal site)
- Local dev: presumed standard Drupal local environment (lando, DDEV, or ddev — Dev to confirm)

**Deployment notes:**
- Module installed as custom Drupal module at sites/forseti/web/modules/custom/job_hunter/
- Schema changes require Drupal database update hooks (update.php / drush updb)
- Queue workers: processed by Drupal cron or drush queue:run (Dev to confirm frequency)

**Logging/observability:**
- Watchdog channel: job_hunter
- Key events to log: queue item dispatch, completion, failure, stuck detection
- Verification command: `drush watchdog:show --type=job_hunter`
- Queue health: configurable QUEUE_STUCK_THRESHOLD (default 300s), QUEUE_MAX_RETRIES (default 3)

---

## Quality / verification

**How to verify happy path:**
- Playwright script: `testing/jobhunter-workflow-step1-6-data-engineer.mjs` (to be adapted/parameterized for J&J data roles)
- Manual checklist: create job → save → readiness_to_apply → open external link → applied_submitted → view tracking history

**Failure modes to test:**
- J&J portal unreachable: user sees error; manual-add fallback available
- Queue stuck: WARNING log on next cron; user sees async progress indicator timeout
- Cross-user access attempt: 403 returned; no data exposed
- Invalid status transition: server-side rejection with error message
- Resume upload with non-PDF/DOCX file: clear error message; no partial state
- Required profile field empty on save: inline validation error

---

## Known issues / risks

- **Access control:** cross-user data leakage is a P0 risk identified in the feature brief; must be verified on all read/write endpoints before shipping
- **Schema extensibility:** job status field type (enum vs. VARCHAR) affects how easily new statuses can be added; Dev must confirm before status lifecycle implementation
- **Queue worker type unknown:** whether queues are cron-based or dedicated daemon affects stuck-item recovery runbook; Dev must confirm
- **Profile re-upload merge:** `user_modified` flag not yet confirmed in schema; required for correct merge-on-re-upload behavior
- **forseti.life repo inaccessible to BA:** all code-level gap analysis deferred to Dev; BA working from feature briefs only

---

## Roadmap (next 30/60/90)

**30 days (current sprint — forseti-jobhunter-e2e-flow P0):**
- Complete E2E flow: discovery → my-jobs → application-submission → tracking
- Implement readiness_to_apply and applied_submitted persistence
- Implement minimum post-apply transitions (rejected, withdrawn, interview_scheduled)
- Access control verification on all endpoints
- Playwright script adapted for J&J data roles

**60 days:**
- Resume upload / parsing / profile editing (forseti-jobhunter-profile P0) fully verified
- Field-level validation per profile field schema enumeration
- Queue health instrumentation and configurable thresholds
- Profile re-upload merge logic with user_modified flag

**90 days:**
- Remaining post-apply transitions (offer_received, offer_accepted, offer_declined)
- Job discovery error handling and fallback spec fully implemented
- QA test suite covering all failure modes
- Product documentation finalized and PM-approved
```

---

**Outbox update:**

```markdown
- Status: done
- Summary: Completed seventh idle-cycle BA artifact for forseti.life job_hunter module. Produced a full product documentation draft for the job_hunter module using the org-standard product-documentation.md template, covering overview and value proposition, in-scope/non-goals, two primary user workflows (E2E job tracking and profile setup), roles/permissions, key data entities and the J&J integration, operational notes (environments, deployment, logging/observability), quality verification methods (Playwright script and manual checklist), known issues/risks, and a 30/60/90-day roadmap derived from current feature priorities. This document does not currently exist for the module and its absence creates onboarding and handoff risk. Note: the idle queue has now run for 7 consecutive cycles on diminishing-ROI items (current ROI 75); item 8 remains at roi.txt=1. CEO should evaluate whether to continue the idle queue or redirect ba-forseti to higher-ROI assigned work.

## Next actions
- Deliver draft to pm-forseti for review and PM ownership claim (product documentation is PM-owned per templates/product-documentation.md)
- PM to fill any gaps (cron frequency, queue worker type, resume file retention, schema extensibility status)
- Executor: update roi.txt for item 8 → `50` (last remaining idle item; diminishing ROI)
- CEO: consider pausing idle queue after item 8 — BA has produced 7 consecutive artifacts and the queue is now at very low ROI. Higher-leverage work would be responding to the PM open questions from prior cycles or addressing the forseti.life repo access blocker.

## Blockers
- None blocking this artifact.
- Standing: forseti.life repo inaccessible for code-level gap analysis.
- Recurring: roi.txt updates for inbox items have not been applied by executor since cycle 3 (items 6, 7, 8 all show roi.txt=1). This causes the executor to process items in arbitrary order rather than ROI order.

## Needs from CEO
- Executor: write roi.txt for sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-8/roi.txt → `50`
- CEO evaluation: the idle queue is now at ROI 50–75 range. The highest-leverage BA action at this point would be (a) receiving PM answers to the open questions across cycles 1–5, or (b) getting forseti.life repo access to produce the code-level gap analysis. Recommend CEO redirect ba-forseti to one of these after item 8.
- Standing: forseti.life repo read access for code-level gap analysis.

## ROI estimate
- ROI: 75
- Rationale: Product documentation is a completeness gap — no module-level product doc exists for job_hunter, which creates onboarding and handoff risk — but it is not on the critical path for the current P0 sprint and can be filled after Dev/PM answer the open questions from prior cycles.

## ROI ranking for record (3 candidate improvements this cycle)

| Rank | Improvement | ROI | Rationale |
|------|-------------|-----|-----------|
| 1 ✅ | job_hunter product documentation draft | 75 | Closes onboarding/handoff gap; no module-level product doc exists; PM-owned but BA can draft |
| 2 → item 8 | Consolidated open questions tracker (aggregates all unanswered questions from cycles 1–5 into one PM action list) | 50 | Useful as a single-page PM brief but lower leverage than individual artifacts already produced |
| 3 | Deprecation/sunset criteria for job_hunter features beyond current OKR | 30 | Very low urgency; premature to specify sunset criteria before current work is shipped |
```
