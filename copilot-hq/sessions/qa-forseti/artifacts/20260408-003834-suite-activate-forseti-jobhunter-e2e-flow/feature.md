# Feature Brief

- Work item id: forseti-jobhunter-e2e-flow
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260406-forseti-release-b
- Priority: P0 (ROI 1000)
- Feature type: stabilization
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti

## OKR / Goal
Deliver an end-to-end, fully automated website flow for job submission and tracking in the JobHunter module.

Target company: Johnson & Johnson (https://www.careers.jnj.com/en/)
Target roles: any “data” role (e.g., Data Engineer, Data Analyst, Data Scientist).

Definition of “end-to-end” for this OKR:
1) A user can discover a J&J data role (or manually add it if discovery fails).
2) The job is saved into the user’s job list.
3) Stage break: the system MUST NOT create an account on the external J&J portal.
4) The user can mark the job as applied/submitted and the system records date/link/status.
4) The user can track job status in-site (list + detail + history).
5) Automation/queues run without manual babysitting (no broken steps on the /jobhunter dashboard flow).

Stage break detail:
- Allowed: navigate to / open the external application link.
- Not allowed: creating an account on the Johnson & Johnson portal.
- Success for this OKR: end-to-end automation up to (and including) recording “ready-to-apply” and “applied/submitted” state in Forseti.

## Scope
- Fix/complete the process flow on the /jobhunter dashboard (step-based navigation) so all steps work.
- Ensure “submission” and “tracking” are persisted and visible.
- Prefer using existing JobHunter tables/entities/queues and existing testing scripts.

## Non-goals (for this work item)
- Building a full external auto-apply bot for third-party portals.
- Creating accounts on the Johnson & Johnson portal.

## Acceptance Criteria
- The /jobhunter dashboard step flow is usable end-to-end for the target scenario.
- A representative J&J data-role job can be created/saved/applied/tracked entirely via the website.
- A repeatable verification exists:
  - Prefer: playwright workflow script `testing/jobhunter-workflow-step1-6-data-engineer.mjs` adapted or parameterized for J&J.
  - Minimum: a documented manual verification checklist in QA outbox.

## References
- Module docs: /jobhunter/documentation/process-flow
- Module architecture includes J&J focus: sites/forseti/web/modules/custom/job_hunter/ARCHITECTURE.md
- Existing test workflow: testing/jobhunter-workflow-step1-6-data-engineer.mjs

## Latest updates

- 2026-04-05: Scoped into release — suite activation sent to QA.

- 2026-04-05: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-03-27: Handed off to QA for test generation (pm-qa-handoff.sh)

- 2026-02-26: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-02-26: Handed off to QA for test generation (pm-qa-handoff.sh)
- 2026-02-21: Created work item; queued PM/BA/Dev/QA tasks for OKR execution.
- 2026-04-08: Deferred from 20260407-forseti-release-b — no dev implementation or Gate 2 APPROVE before release close.

## Security acceptance criteria
- Authentication/permission surface: /jobhunter dashboard and all step routes require authentication; anonymous users must be redirected to login.
- CSRF expectations: All step-transition POST actions use split-route pattern with `_csrf_token: TRUE` on POST-only route entries.
- Input validation: Job title, company, and URL fields validated server-side; no user-supplied HTML stored or rendered unescaped.
- PII/logging constraints: Job application data (target company, role, tracking status) must not appear in Apache access logs; Drupal watchdog may log non-sensitive operation IDs only.
