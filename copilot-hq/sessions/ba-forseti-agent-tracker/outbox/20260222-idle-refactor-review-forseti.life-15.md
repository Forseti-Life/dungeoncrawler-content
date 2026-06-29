# Outbox update: 20260222-idle-refactor-review-forseti.life-15

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-15
- Generated: 2026-02-22T16:40:18-05:00

---

I have everything needed. Here is the outbox update:

---

**OUTBOX FILE:** `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-15.md`

```markdown
- Status: done
- Summary: Refactor/review cycle 15 was directed at `DashboardController.php`, reviewed in cycles 3, 4, 5, 8, and 14 with 14+ gaps documented and multiple dev follow-up items already queued. All 13 copilot_agent_tracker module files are fully swept. All major dungeoncrawler requirements gaps are now specced (cycles 1–14: Combat, Conditions, Level-Up, Spellcasting, XP Award, Focus Spells, Character Creation). This cycle pivots to the last major unspecced item in scope: job_hunter Flow 17 — Automated Application Submission. Per `JOB_HUNTER_GAP_ANALYSIS.md`, Flow 17 is explicitly marked `❌ TODO - MVP PRIORITY` with 0 implementation started: no Playwright framework, no form-completion logic, no queue worker, no credential handling. This is the highest-complexity remaining MVP gap (8–10 days estimated effort). The artifact below provides problem statement, scope, architecture, user flows, 14 draft ACs, 5 clarifying questions, and a delegation table. One follow-up queued to pm-forseti-agent-tracker (supervisor) for AC finalization and sequencing decision.

## Next actions
- Executor: write this outbox file to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-15.md`
- Executor: create follow-up inbox item in `sessions/pm-forseti-agent-tracker/inbox/` (content below)
- BA: all major requirements gaps across copilot_agent_tracker, dungeoncrawler, and job_hunter are now specced; remaining work is PM AC finalization and Dev implementation for all queued items

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 20
- Rationale: Flow 17 is the core value proposition of job_hunter — without automated application submission, users must apply manually and the product is a job tracker, not an automation tool. It is the last unspecced MVP gap and the most complex; speccing it now unblocks Dev for the final sprint. The 8–10 day estimate makes early clarity on architecture (Playwright vs Puppeteer, credential encryption, MFA strategy) essential before Dev starts.

---

# Requirements Artifact: job_hunter Flow 17 — Automated Application Submission

**Artifact type:** Requirements summary + draft acceptance criteria
**Website:** forseti.life
**Module:** job_hunter
**Source:** JOB_HUNTER_GAP_ANALYSIS.md, Gap 7 (lines 246–344)
**Date:** 2026-02-22

---

## Problem statement

job_hunter's core value proposition is automated job application submission. All other flows (profile management, company tracking, job discovery, resume tailoring) exist to feed this pipeline. Flow 17 is explicitly the last unimplemented MVP gap: no browser automation framework, no form-completion logic, no queue worker, no credential handling. Without it, users must manually apply to every job — defeating the product's purpose.

The goal is a Playwright-based Node.js microservice (or Drupal-integrated service) that: receives an application task from a queue, launches a headless browser, authenticates to the job portal, fills and submits the application form, captures a confirmation screenshot, and reports success/failure back to the Drupal application record. The initial target portal is Johnson & Johnson (J&J) only, with a manual fallback for portals that cannot be automated.

---

## Scope

**In scope:**
- Queue worker: processes application tasks from `ApplicationSubmitterQueueWorker`
- Browser automation: Playwright (Node.js) to fill and submit J&J application forms
- Authentication: handle J&J portal login (username/password); MFA fallback documented
- Form field mapping: J&J application field set identified and mapped to user profile fields
- Resume upload: attach tailored resume PDF to application form
- Cover letter attach: if J&J form accepts one, attach tailored cover letter
- Confirmation capture: screenshot on success; store as evidence
- Status update: mark `jobhunter_applications` record as `submitted` or `failed`
- Error queue: on failure, insert a row in error queue with error type + screenshot + stack trace
- Manual fallback workflow: if automation fails 3× for a job, notify admin and mark for manual submission
- Credential storage: encrypted storage of portal credentials (username/password per user per company)

**Non-goals:**
- Multi-portal support beyond J&J at MVP (generic form-filler deferred)
- CAPTCHA solving (flag as requires-manual and stop)
- Full MFA automation (flag as requires-manual and stop)
- Proxy rotation / anti-bot evasion beyond standard headless browser configuration
- Browser extension / desktop client
- Real-time streaming of automation progress to user UI

---

## Architecture overview

```
Drupal job_hunter module
  → ApplicationSubmitterQueueWorker (Drupal queue)
    → [queue item: {application_id, job_url, user_credentials_ref, resume_path}]
      → Node.js Playwright microservice (REST or Drupal subprocess)
        → launch headless Chromium
        → authenticate to J&J portal
        → fill application form fields
        → upload resume PDF
        → submit form
        → capture confirmation screenshot
        → return {status, confirmation_number, screenshot_path}
  → ApplicationSubmissionService (Drupal)
    → receives result
    → updates jobhunter_applications.status
    → on failure: inserts error_queue row
    → on 3× failure: notifies admin + marks for manual
```

**Key architectural decision (Q1):** Is the Playwright runner a separate Node.js microservice (HTTP API), a Drupal module subprocess, or a Docker container triggered via shell exec? This affects deployment complexity, credential isolation, and error handling surface.

---

## Credential handling design

User portal credentials (J&J username/password) must be stored encrypted:
- Storage: new table `jobhunter_portal_credentials` (user_id, company_id, encrypted_username, encrypted_password, created_at, updated_at)
- Encryption: AES-256 via Drupal's `EncryptionProfileInterface` or Sodium PHP extension
- Key management: encryption key stored in Drupal state (not DB); separate from data
- Access: credentials only decrypted at automation runtime; never logged; never returned in API responses
- Credential entry UI: dedicated form, not part of profile (separate route, HTTPS-only enforcement)

---

## J&J application form field mapping (MVP target)

| Form field (J&J portal) | Source (user profile field) |
|---|---|
| First name | `jobhunter_job_seeker.first_name` |
| Last name | `jobhunter_job_seeker.last_name` |
| Email | Drupal user email |
| Phone | `jobhunter_job_seeker.phone` |
| LinkedIn URL | `jobhunter_job_seeker.linkedin_url` |
| Resume upload | `jobhunter_tailored_resumes.file_path` (PDF) |
| Cover letter | `jobhunter_tailored_resumes.cover_letter_text` (if field exists) |
| Work authorization | `jobhunter_job_seeker.work_authorization` |
| Veteran status | `jobhunter_job_seeker.veteran_status` |
| Disability status | `jobhunter_job_seeker.disability_status` |

Note: Exact J&J field names must be confirmed via a live form inspection session (Q3).

---

## User flows

### Flow A: Automated application (happy path)

1. User reviews a tailored resume for a job and clicks "Submit Application."
2. System creates an application record in `jobhunter_applications` (`status = 'queued'`).
3. System enqueues an item in `ApplicationSubmitterQueueWorker` with: `application_id`, `job_url`, `user_id`, `tailored_resume_id`.
4. Queue worker dequeues the item; calls `ApplicationSubmissionService` which decrypts portal credentials.
5. Service spawns Playwright process; browser navigates to J&J portal.
6. Browser authenticates (username/password). If MFA is triggered: abort this attempt, set status `requires_mfa_manual`, notify admin → end.
7. Browser fills all form fields from field mapping table; attaches resume PDF.
8. Browser submits form.
9. Browser waits for confirmation page; captures screenshot; extracts confirmation number if present.
10. Service returns `{status: 'success', confirmation_number: 'JNJ-12345', screenshot_path: 'path/to/file.png'}`.
11. Drupal updates `jobhunter_applications.status = 'submitted'`, stores `confirmation_number` and `screenshot_path`.
12. User receives in-app notification: "Application submitted to [Job Title] at J&J."

### Flow B: Automation failure → error queue → retry

1. Browser automation fails (form structure changed, timeout, network error).
2. Service returns `{status: 'failed', error_type: 'form_structure_changed', screenshot_path: '...', stack_trace: '...'}`.
3. Drupal inserts a row in `error_queue` with error details.
4. Application status set to `failed_attempt_1`.
5. System schedules retry (up to 3 attempts with exponential backoff).
6. After 3 failures: `status = 'failed_max_retries'`; admin notified; job marked for manual submission.

### Flow C: CAPTCHA or MFA encountered

1. Browser detects CAPTCHA or MFA challenge.
2. Automation immediately aborts (do not attempt to solve).
3. Status set to `requires_manual`; admin notified with screenshot of the challenge screen.
4. Admin manually completes the application; marks status as `submitted_manually`.

### Flow D: User enters portal credentials

1. User navigates to credential management form (`/job-hunter/credentials/[company_id]`).
2. User enters portal username and password.
3. System encrypts credentials using AES-256; stores in `jobhunter_portal_credentials`.
4. System does NOT confirm credentials are valid at entry time (validation happens at first automation run).

---

## Draft acceptance criteria (candidates for PM to finalize)

**Queue and submission flow:**

1. Given a user clicks "Submit Application" for a queued job, then a `jobhunter_applications` row is created with `status = 'queued'` and an item is added to `ApplicationSubmitterQueueWorker`.
2. Given the queue worker processes an item successfully, then `jobhunter_applications.status` is updated to `submitted`, `confirmation_number` is stored, and a confirmation screenshot file path is stored.
3. Given the queue worker processes an item, then no portal credentials are written to any Drupal log file (watchdog, PHP error log, or application log).

**Browser automation:**

4. Given the Playwright process is invoked for a J&J application, then all fields in the J&J field mapping table are filled before form submission.
5. Given the Playwright process successfully attaches a resume PDF, then the PDF is the character's tailored resume for that specific job (not the master resume).
6. Given the form is submitted and a confirmation page is reached, then a screenshot is captured and stored in a predictable, retrievable file path.

**Error handling:**

7. Given automation fails on first attempt, then the system retries automatically (up to 3 attempts) before escalating to the error queue.
8. Given automation fails 3× for the same application, then `status = 'failed_max_retries'`, an admin notification is sent, and no further automated attempts are made for that application.
9. Given a CAPTCHA is detected during automation, then the browser immediately aborts, status is set to `requires_manual`, a screenshot of the CAPTCHA screen is captured, and admin is notified.
10. Given MFA is triggered at the portal login, then automation aborts, status is set to `requires_mfa_manual`, and admin is notified with the specific step at which MFA appeared.

**Credential storage:**

11. Given a user saves portal credentials, then the password is stored AES-256 encrypted and the plaintext password is never logged or returned in any API response.
12. Given a user's portal credentials are retrieved for automation, then decryption happens only within the submission service at runtime and the plaintext credential is not persisted to any variable beyond the Playwright invocation scope.

**Manual fallback:**

13. Given an application is in `requires_manual` status, then an admin can view the application detail, see the error screenshot, and mark it as `submitted_manually` with a confirmation reference number.

**Field validation:**

14. Given a user attempts to queue an application but has no portal credentials stored for that company, then the application is not queued and the user sees an error: "No credentials stored for [Company]. Please add them in Settings → Job Portal Credentials."

---

## Open questions (for PM)

**Q1 — Playwright runner architecture:** Options:
- A. Standalone Node.js microservice with REST API (Drupal POSTs task, receives JSON result) — recommended: clean separation, independently deployable, easier to test
- B. Drupal `shell_exec()` to Node.js script inline — simpler but harder to test/debug
- C. Symfony Process component wrapper around Node.js script
Recommendation: Option A. Keeps automation logic isolated, enables independent scaling, and avoids blocking Drupal PHP processes during potentially slow browser sessions.

**Q2 — Credential encryption library:** Options:
- A. Drupal Encrypt module + AES-256 profile (Drupal ecosystem standard)
- B. PHP sodium_crypto_secretbox (built-in PHP 7.2+, no module dependency)
Recommendation: Option A if Encrypt module is already installed (verify); Option B if not (lighter dependency footprint).

**Q3 — J&J form field confirmation:** The field mapping table above is a best-guess from profile fields. Before Dev builds the automation, someone must inspect the live J&J application form and confirm exact field selectors/names. This is a manual task — PM/CEO/Dev must do a live form walkthrough session and document selectors in a `J&J_FORM_SPEC.md` file. Without this, Dev will be guessing at CSS selectors.

**Q4 — Queue processing trigger:** Does `ApplicationSubmitterQueueWorker` run via Drupal cron (batch) or via a real-time Drupal Queue API consumer triggered on enqueue? For MVP a cron-based approach is simpler; for production, real-time is better. Recommendation: cron-based for MVP (configurable interval, default 15 minutes).

**Q5 — Rate limiting and J&J anti-bot posture:** Does J&J block headless browsers (Playwright's default user-agent is detectable)? A pre-implementation test with a Playwright script that just loads the J&J application URL should be run before Dev commits to the automation approach. If J&J blocks headless browsers, a User-Agent override + `--disable-blink-features=AutomationControlled` flag may be needed. Recommendation: PM/CEO to greenlight a 1-hour spike to test J&J portal accessibility before full Dev investment.

---

## Delegation table

| Task | Owner | Priority | Notes |
|---|---|---|---|
| Finalize ACs + Q1–Q5 decisions | PM (pm-forseti-agent-tracker) | High | Prerequisite for Dev |
| J&J live form field selector walkthrough (Q3) | CEO/PM (manual task) | High | Must precede Dev — no code substitute |
| Playwright headless browser spike test (Q5) | Dev (dev-forseti) | High | 1-hour spike before full build |
| Schema: `jobhunter_portal_credentials` table | Dev | High | Prerequisite for credential storage |
| Playwright Node.js microservice (or chosen arch) | Dev | High | Core deliverable; estimate 8–10 days |
| `ApplicationSubmitterQueueWorker` integration | Dev | High | Drupal queue worker calling microservice |
| Error queue + retry logic + admin notification | Dev | High | AC 7–10 |
| Credential entry UI + encryption | Dev | High | AC 11–12 |
| Manual fallback admin UI | Dev | Medium | AC 13 |
| QA: all 14 ACs + J&J live submission test | QA | High | After Dev ships |

---

## Follow-up inbox item (for executor to create)

**Destination:** `sessions/pm-forseti-agent-tracker/inbox/20260222-job-hunter-flow17-automated-submission-ac-finalize/`

**`command.md`:**
```markdown
- command: |
    PM finalization: job_hunter Flow 17 — Automated Application Submission ACs

    BA has produced a draft requirements artifact for Flow 17.
    This is the last unspecced MVP gap and highest-complexity item remaining.

    Source: sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-15.md
    Reference: ~/forseti.life/JOB_HUNTER_GAP_ANALYSIS.md, Gap 7

    Your tasks:
    1. Review 14 draft ACs and 5 clarifying questions
    2. Decide:
       - Q1: Playwright runner architecture (recommendation: standalone Node.js microservice)
       - Q2: Credential encryption library (Drupal Encrypt module vs PHP sodium)
       - Q3: Greenlight a manual J&J form field selector walkthrough session
       - Q4: Queue processing trigger (cron-based recommended for MVP)
       - Q5: Greenlight 1-hour Playwright spike test against J&J portal
    3. If approved: create Dev work items in sequence:
       a. Schema migration (portal_credentials table)
       b. Playwright spike test (1 hour)
       c. Full automation microservice implementation
    4. If J&J blocks headless browsers (spike reveals): escalate to CEO for alternate strategy

    Acceptance criteria for this PM task:
    - All 5 Q decisions documented
    - ACs 1–14 approved / modified / rejected
    - J&J form walkthrough scheduled or delegated
    - Dev work items created if approved

    Time sensitivity: this is the last MVP blocker; sequencing it now prevents a 2-week delay at end of sprint.
```

**`roi.txt`:** `20`
```
