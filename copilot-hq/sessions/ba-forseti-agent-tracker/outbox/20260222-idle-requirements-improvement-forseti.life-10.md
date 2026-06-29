# Outbox update: 20260222-idle-requirements-improvement-forseti.life-10

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-requirements-improvement-forseti.life-10
- Generated: 2026-02-22T13:12:30-05:00

---

I have enough context. Producing the requirements artifact for **Automated Application Submission (Flow 17)** — the highest-ROI remaining unspecced item.

---

- Status: done
- Summary: Completed BA idle cycle 10 for forseti.life. Selected Automated Application Submission (Flow 17, job_hunter) as the top-ROI remaining item — it is the core value-delivery mechanism of the entire job_hunter module (without it, users must apply manually, defeating the product's purpose) and has zero implementation started. This spec closes the largest remaining requirements gap: defining the browser automation architecture, credential storage contract, CAPTCHA/MFA fallback strategy, queue worker design, and definition of done for the J&J MVP target. It is intentionally scoped to the J&J MVP to bound complexity and enable the team to ship a working end-to-end demo before generalizing to other portals.

## Next actions
- Executor: write this artifact to outbox
- Route to PM (job_hunter) for AC sign-off; critical decisions Q1 (credential storage strategy) and Q4 (MFA UX) must be decided before Dev can proceed
- Route security review request to Security role before implementation — credential storage and portal authentication handling carry the highest security risk in the entire system
- Note: Flow 4 (Admin Error Queue) is a hard prerequisite for this task — the `error_queue` content type already exists but the admin UI must be built before error inspection for failed automation runs is possible

## Blockers
- None for spec; implementation blocked on credential storage strategy decision (Q1)

## Needs from CEO
- None

## ROI estimate
- ROI: 35
- Rationale: Automated application submission is the primary value proposition of job_hunter — it converts the system from a job discovery tool into an actual job application assistant. Without it, every other completed feature (profile, company selection, job discovery, tailored resume) has no terminal action. The J&J-scoped MVP bounds the risk while proving the end-to-end loop.

---

## ROI-Ranked Requirements Improvements (3 candidates)

| Rank | Item | Module | ROI | Rationale |
|------|------|--------|-----|-----------|
| 1 ✅ | Automated Application Submission Spec (Flow 17) | job_hunter | 35 | Terminal action in job_hunter value chain; zero implementation started; most complex remaining feature requiring early architectural decisions on credentials, browser runtime, and MFA |
| 2 | User Support Contact Form Spec (Flow 5) | job_hunter | 20 | Simple 1-2 day Webform/Contact task; all remaining gaps now specced except this one |
| 3 | Dungeoncrawler Spell System Requirements | dungeoncrawler | 18 | No spell/spellcasting spec exists beyond slot progression references in level-up wizard; needed eventually but not blocking current dev work |

---

## Requirements Artifact: job_hunter — Automated Application Submission (Flow 17, J&J MVP)

### Problem Statement

The job_hunter module discovers jobs, tailors resumes, and presents matched opportunities — but has no mechanism to submit applications. `JOB_HUNTER_GAP_ANALYSIS.md` (Gap 7) confirms: "No browser automation framework implemented. No form completion logic started." Without automated submission, users must copy-paste tailored resumes into job portals manually, defeating the product's core value proposition.

**MVP scope:** Johnson & Johnson (`careers.jnj.com`) only. J&J was selected as the target because it is the confirmed MVP company for initial testing and the `field_careers_url` on J&J's company node points to the J&J careers portal.

**Current behavior:** After `ResumeTailoringService` generates a tailored resume, there is no "Apply" action. The job detail page (specified in Job Discovery Dashboard spec) includes a "Tailor Resume" button linking to `/user/{uid}/tailor-resume/{job_id}` — but after tailoring there is no submission pathway.  
**Expected behavior:** After tailoring, user clicks "Apply Now" → application is queued → `ApplicationSubmitterQueueWorker` processes the queue → browser automation navigates to the job's application URL, authenticates as the user, fills the form, uploads the tailored resume, submits, captures confirmation → `application` node is created/updated with status "Submitted" or "Failed" → user sees outcome in their Application Tracking Dashboard (Flow 9).

### Critical Architectural Decisions (Pre-Requisites for Dev)

#### Decision 1: Browser Automation Runtime

The job_hunter module is PHP/Drupal. Browser automation tools (Playwright, Puppeteer) are Node.js native. Three implementation options:

| Option | Approach | Pros | Cons |
|--------|----------|------|------|
| A | Playwright via Node.js microservice; PHP calls via HTTP/queue | Best isolation; Playwright is best-in-class for reliability | Additional microservice to deploy and maintain |
| B | Puppeteer via Node.js microservice (same pattern) | Slightly simpler than Playwright for basic cases | Less feature-rich; worse CAPTCHA/shadow DOM handling |
| C | PHP Panther (Symfony Browser Kit + ChromeDriver) | Pure PHP, no Node.js runtime | Significantly less capable; poor JS-heavy SPA handling; rarely used in production |

**Recommendation: Option A (Playwright Node.js microservice)** — job portals like J&J are modern SPAs; Playwright's auto-waiting, screenshot capture, and browser context isolation make it the most reliable choice for form automation in 2024–2026.

#### Decision 2: Credential Storage

Users need to authenticate to `careers.jnj.com` to apply. Three storage strategies:

| Option | Approach | Security | UX |
|--------|----------|----------|-----|
| A | Encrypted field in `jobhunter_job_seeker` table (AES-256, key in env var) | Moderate — encrypted at rest; key must be protected | No re-auth required; seamless |
| B | User enters credentials at queue-time via one-time ephemeral form | Better — credentials never stored; discarded after use | Requires user to be online and interact at queue processing time |
| C | OAuth/SSO only (only support portals with OAuth login) | Best | Limits coverage severely; J&J does not support OAuth login for applicants |

**Recommendation: Option A (encrypted at rest)** — MVP requires J&J, which uses username/password; Option B introduces UX complexity (user must be present when the queue runs); Option A is standard practice for credential-storing job automation tools. Security review is required before implementation.

Required fields on `jobhunter_job_seeker` table (new):
- `portal_credentials JSON` — encrypted JSON blob: `[{portal: "careers.jnj.com", username: "...", encrypted_password: "..."}]`
- `credentials_encryption_key_id` — reference to which env key was used (for key rotation)

### Scope

**In scope (J&J MVP only):**
- User-facing credential management form at `/user/{uid}/portal-credentials`: add/edit/delete portal login (username + password); password field masked; stored encrypted
- "Apply Now" button on job detail page (linked from Job Discovery Dashboard spec); only shown when: (a) user has a tailored resume for this job, and (b) user has stored credentials for the job's portal domain
- Queue submission: clicking "Apply Now" creates a Drupal queue item in `application_submission_queue` with `{user_id, job_id, resume_node_id, portal_url}`; creates/updates `application` node to status "Queued"
- `ApplicationSubmitterQueueWorker`: processes queue items; calls `ApplicationSubmissionService` (Node.js Playwright microservice via HTTP); handles success/failure; updates `application` node status
- `ApplicationSubmissionService` (Playwright microservice) capabilities for J&J MVP:
  - Launch isolated browser context
  - Navigate to `field_application_link` URL on job posting
  - Log in using stored credentials (handle 2FA — see Q4 below)
  - Map user profile fields → J&J application form fields (see field mapping table below)
  - Upload tailored resume PDF
  - Submit form
  - Capture screenshot of confirmation page
  - Return `{success: bool, confirmation_number: string|null, screenshot_path: string, error: string|null}`
- On success: `application` node status → "Submitted"; store confirmation number and screenshot path
- On CAPTCHA detection: stop automation; set `application` node status → "Manual Required"; notify user: "Automated application blocked — please apply manually at [URL]"
- On form structure change detection (selector not found): set status → "Failed (Form Changed)"; create `error_queue` node for admin review
- On credentials failure (login rejected): set status → "Failed (Auth Error)"; notify user to update credentials
- Rate limiting: max 5 automated applications per user per 24 hours (configurable via admin settings)

**Non-goals:**
- Multi-portal generalization (Phase 2 — start with J&J only)
- CAPTCHA solving services (Playwright is flagged to detect CAPTCHA; auto-solving not in scope)
- Real-time browser session (user watches automation in browser — Phase 2 "supervised mode")
- Cover letter generation / uploading at submit time (cover letter tailoring is a separate service; scope is limited to uploading the tailored resume)
- LinkedIn Easy Apply (different portal; Phase 2)

### Field Mapping: User Profile → J&J Application Form (J&J-Specific)

| User Profile Field | J&J Form Field | Notes |
|---|---|---|
| `field_first_name` | First Name | Required |
| `field_last_name` | Last Name | Required |
| `field_email` | Email | Required |
| `field_phone` | Phone | Required |
| `field_location_city` | City | |
| `field_location_state` | State/Province | |
| `field_work_authorization` | Work Authorization | Dropdown: US Citizen, Green Card, H1B, etc. |
| tailored resume PDF | Resume Upload | PDF format required |
| `field_linkedin_url` | LinkedIn Profile URL | Optional |
| `field_portfolio_url` | Portfolio/Website | Optional |
| `field_years_experience` | Years of Experience | Derived from profile |

*Note: J&J form field selectors must be mapped during implementation via Playwright recording session; this mapping table is the semantic contract that Dev must fulfil via CSS/XPath selectors.*

### Key User Flows

**Flow A: Happy path — automated submission**
1. User opens job detail for a J&J posting at `/user/{uid}/jobs/{job_id}`
2. User sees "Tailor Resume" button → clicks → tailored resume generated
3. "Apply Now" button appears (was greyed out pre-tailoring)
4. User clicks "Apply Now" → modal: "Submit application to J&J? This will use your stored J&J credentials." → Confirm
5. `application` node created with status "Queued"; user sees toast: "Application queued — we'll notify you when it's submitted"
6. Queue worker picks up item → calls Playwright service → automation runs
7. On success: `application` status → "Submitted"; user receives email: "Your application to [Job Title] at J&J was submitted. Confirmation: #12345"
8. Application Tracking Dashboard shows the new entry

**Flow B: No stored credentials**
1. User clicks "Apply Now" → system checks for `portal_credentials` for `careers.jnj.com`
2. No credentials found → redirect to `/user/{uid}/portal-credentials` with message: "To apply automatically, please add your J&J careers portal login."
3. User saves credentials → returns to job detail → "Apply Now" now active

**Flow C: CAPTCHA / automation blocked**
1. Queue worker runs automation → Playwright detects CAPTCHA challenge page
2. Worker stops automation → sets application status "Manual Required"
3. User receives notification: "Automated application was blocked by a CAPTCHA. Please apply manually at [URL] using your tailored resume. Your resume is saved at [link]."
4. Admin `error_queue` entry created for rate/CAPTCHA monitoring

**Flow D: Form structure changed (J&J updated their form)**
1. Playwright cannot find expected selector for a required field
2. `ApplicationSubmissionService` returns `{success: false, error: "Selector not found: #work-auth-dropdown"}`
3. Application status → "Failed (Form Changed)"
4. Admin `error_queue` node created: type = "Form Selector Failure", portal = "careers.jnj.com", detail = selector name + screenshot
5. Admin can update field mapping config or trigger re-attempt after fix

### Acceptance Criteria (Draft — PM to finalize)

**Happy path:**
- AC1: A user with a tailored resume AND stored J&J credentials sees an active "Apply Now" button on a J&J job detail page; a user missing either condition sees the button greyed out with a tooltip explaining what is missing.
- AC2: Clicking "Apply Now" creates an `application` node with status "Queued" and adds a queue item to `application_submission_queue` within 3 seconds.
- AC3: The queue worker successfully processes a J&J test job application: authenticates, fills all required form fields from the field mapping table, uploads the tailored resume PDF, submits the form, and captures a confirmation screenshot.
- AC4: On successful submission, the `application` node status is updated to "Submitted", the confirmation number (if provided by J&J) is stored, and the user receives an email notification within 5 minutes of queue processing.
- AC5: Stored portal credentials are encrypted at rest using AES-256; the plaintext password is never stored or logged; decryption uses an env-var-managed key.
- AC6: The credential management form at `/user/{uid}/portal-credentials` allows add/edit/delete of portal credentials; the password field is masked in the UI; form submission confirms credentials were saved.
- AC7: Rate limiting enforces max 5 automated application submissions per user per 24 hours; attempting a 6th returns: "Daily application limit reached — try again tomorrow."

**Failure modes:**
- AC8: On CAPTCHA detection during automation, the application status is set to "Manual Required" and the user receives a notification with a direct link to the application URL and their tailored resume download link.
- AC9: On login failure (wrong credentials), the application status is set to "Failed (Auth Error)" and the user receives a notification: "Automated login to [portal] failed — please update your credentials at [link]."
- AC10: On form selector failure (J&J form changed), the application status is set to "Failed (Form Changed)" and an `error_queue` node is created with portal name, failed selector, and a screenshot for admin review.
- AC11: A user cannot view or trigger any automation for another user's application; attempting `/user/{other_uid}/portal-credentials` returns HTTP 403.
- AC12: All automation sessions run in isolated browser contexts (no session/cookie bleed between users).

**Verification method:**
- PHPUnit: `ApplicationSubmitterQueueWorkerTest` — mock `ApplicationSubmissionService` HTTP call; assert status transitions (Queued → Submitted, Queued → Manual Required, Queued → Failed).
- PHPUnit: `CredentialEncryptionTest` — assert credentials are encrypted before storage; assert decrypted value matches original.
- Integration (staging): Submit one real test application to a J&J test/demo job posting; confirm field mapping, form fill, and screenshot capture work end-to-end.
- Manual: Trigger CAPTCHA failure path (use a dev hook or mock); assert AC8 notification fires and status is set correctly.
- Security: Verify with `SELECT portal_credentials FROM jobhunter_job_seeker` that stored value is not plaintext.

### Assumptions

1. The Playwright microservice is deployed as a Node.js service on the same server or accessible via internal HTTP; a `PLAYWRIGHT_SERVICE_URL` env var points to it.
2. The `application` content type already exists (confirmed in `JOB_HUNTER_GAP_ANALYSIS.md`); status field values ("Queued", "Submitted", "Manual Required", "Failed (Auth Error)", "Failed (Form Changed)") are added as allowed values on `field_application_status`.
3. J&J careers portal uses standard username/password authentication; MFA (if present) uses TOTP — see Q4.
4. Drupal queue API (`queue_api` module or core `QueueFactory`) is available for `application_submission_queue` management.
5. The tailored resume is available as a managed file entity linked to the `job_posting` node via `ResumeTailoringService`; the queue worker can retrieve the PDF file path for upload.
6. Admin settings page at `/admin/config/job-hunter/automation` exists (or is created) for rate limit and Playwright service URL configuration.

### 3–5 Clarifying Questions for Stakeholders

1. **Credential storage security sign-off:** The encryption-at-rest approach (Option A) is recommended but requires security review before implementation. Can a security review be scheduled before Dev begins work on credential storage? Is there a preferred key management approach (env var vs. AWS KMS vs. Drupal Key module)?

2. **Playwright microservice deployment:** Does the forseti.life server environment support running a persistent Node.js process alongside Drupal? Is Docker available? What is the deployment mechanism for the Playwright service (systemd unit, Docker container, AWS Lambda)?

3. **J&J form field mapping:** Has anyone done a manual test run on `careers.jnj.com` to record the form's HTML structure and field selectors? This mapping is required for Dev to implement the automation. If not, the Dev task includes a Playwright recording session as step 0 to capture the selectors — estimate 0.5 days.

4. **MFA/2FA handling:** Does J&J's portal require two-factor authentication for returning applicants? If yes, the automation must handle TOTP (time-based OTP). Three approaches: (a) user stores their TOTP secret key (encrypt it alongside password — security concern), (b) automation waits for user to enter OTP in real time (breaks queue automation), (c) treat any MFA challenge as "Manual Required" fallback. Recommendation: option (c) for MVP — treat MFA as a fallback trigger; revisit TOTP secret storage in Phase 2.

5. **Application submission consent and legal:** Does submitting a job application on behalf of a user via automation require explicit written consent from the user (Terms of Service)? What is the legal/TOS risk of automating submissions on `careers.jnj.com`? This should be reviewed before any real applications are submitted. Recommendation: add a one-time consent checkbox to the credential setup flow: "I authorize forseti.life to submit applications on my behalf using the credentials I provide."

### Delegation Table

| Role | Action |
|------|--------|
| **PM** | Finalize AC1–AC12; answer Qs 1–5; obtain security review sign-off before credential storage implementation; confirm legal/consent requirement (Q5) |
| **Security** | Review credential encryption approach (AES-256 + env key vs. AWS KMS vs. Drupal Key module); approve approach before implementation |
| **Dev (job_hunter)** | Build Playwright Node.js microservice (ApplicationSubmissionService); build ApplicationSubmitterQueueWorker; build credential management form; implement J&J field mapping (after Playwright recording session); implement status transitions; implement rate limiting |
| **QA** | Test end-to-end happy path on J&J staging job; test CAPTCHA fallback; test auth failure; test cross-user 403 (AC11); test encrypted credential storage (AC12) |
