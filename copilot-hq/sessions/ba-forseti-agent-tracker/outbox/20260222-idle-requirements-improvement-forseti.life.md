# Outbox update: 20260222-idle-requirements-improvement-forseti.life

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-requirements-improvement-forseti.life
- Generated: 2026-02-22T09:52:09-05:00

---

I have enough context. Here is the complete outbox artifact for executor to write to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-requirements-improvement-forseti.life/update.md`:

---

- Status: done
- Summary: Completed BA idle cycle for forseti.life (second pass). Ranked 3 candidate requirements improvements by ROI; completed the top-ranked item: a full tightened problem statement and draft acceptance criteria for the Job Hunter User Profile Management feature (Flow 7). This is the single highest-ROI unblocked work item in the product: 24 DB fields exist with no profile UI, which blocks all downstream job matching, discovery, and application tracking. A full requirements artifact, clarifying questions, and delegation plan are produced below.

## Next actions
- Executor: write this artifact to outbox
- Route requirements artifact to PM for acceptance criteria sign-off (see delegation table)
- Route Flow 7 dev task to Dev (job_hunter module) after PM sign-off
- Seed inbox for PM with Flow 7 requirements artifact when ready

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 95
- Rationale: User Profile Management is Gate Zero for the entire Job Hunter module — no user can reach any working feature (AI resume tailoring, job matching, application tracking) without a completed profile. Unblocking this 3-4 day dev task unlocks 6+ downstream flows and moves the module from ~30% to ~50% MVP completion.

---

## ROI-Ranked Requirements Improvements (3 candidates)

| Rank | Item | Module | ROI | Rationale |
|------|------|--------|-----|-----------|
| 1 ✅ | User Profile Management UI (Flow 7) | job_hunter | 95 | Gate Zero: blocks all downstream flows; DB fields exist, just needs UI spec |
| 2 | Diffbot/Web Scraping Integration Spec (Flow 11) | job_hunter | 75 | Core product loop; no spec = Dev can't start job discovery; Diffbot API key assumed available |
| 3 | Combat Condition Lifecycle Requirements | dungeoncrawler | 60 | Combat is partially shipped; condition application/stacking/removal has no dedicated requirements doc tying DB schema to state machine behavior |

---

## Requirements Artifact: Job Hunter — User Profile Management (Flow 7)

### Problem Statement

The Job Hunter module's user entity has 24 custom fields defined in the database (`field_work_authorization`, `field_desired_salary`, `field_job_availability`, `field_resume_file`, `field_job_keywords`, etc.), but there is **no registration extension, no profile edit UI, and no form integration**. Users who register on forseti.life see a stock Drupal registration form with no job-search fields. They have no way to upload a resume, set preferences, or complete their profile.

**Consequence chain:**
1. Profile is empty → job matching algorithm has no inputs → no jobs surface for the user
2. Resume file field is empty → AI Resume Tailoring (`/tailor-resume/{job}`) has nothing to tailor from → core working feature is inaccessible to new users
3. Profile completeness is 0% with no feedback → users don't know what to fill in

**Current behavior:** New user registers → sees stock Drupal form → profile is empty → no Job Hunter features are usable.  
**Expected behavior:** New user registers → sees extended form with job-search fields → fills in resume + preferences → profile completeness percentage displayed → AI tailoring and job matching are immediately accessible.

### Scope

**In scope:**
- Extend Drupal user registration form (`/user/register`) with job-hunter fields (work authorization, job keywords, availability, desired salary range)
- Build profile edit form at `/user/{uid}/edit` surfacing all 24 custom fields with validation
- Resume upload (PDF/DOCX, max 10 MB), stored in managed file field, with text extraction on save
- Profile completeness percentage widget (read-only, displayed on profile and dashboard)
- Validation rules: required fields, file type/size, salary range format
- Success redirect to user dashboard after registration

**Non-goals:**
- Job matching algorithm implementation (separate flow)
- Admin user management UI (separate Gap 2 / Flow 4)
- Social login / OAuth (out of scope for MVP)
- Profile photo / avatar upload

### Definitions

| Term | Definition |
|------|------------|
| Profile completeness | Percentage of the 24 custom fields that have a non-empty value; displayed as a progress indicator |
| Work authorization | User's legal right to work in target country (e.g., US citizen, H1B, OPT, requires sponsorship) |
| Resume file | Uploaded PDF or DOCX; system extracts plain text on save for use by AI tailoring endpoint |
| Job keywords | Comma-separated list of job titles / skills user is targeting (used as matching inputs) |
| Availability | Immediate / 2 weeks / 1 month / not currently looking |

### Key User Flow

1. New user lands on `/user/register`
2. Fills in: email, password, **work authorization** (required dropdown), **job keywords** (required), **availability** (required dropdown), **desired salary** (optional range), **resume upload** (required PDF/DOCX)
3. Submits → Drupal creates account, custom fields saved, resume text extracted, profile completeness calculated
4. User redirected to `/user/{uid}` dashboard with completeness widget showing (e.g., "Profile 70% complete — add desired salary to improve matches")
5. User can edit at any time via `/user/{uid}/edit`
6. User can re-upload resume; old file is replaced, text re-extracted

### Acceptance Criteria (Draft — PM to finalize)

**Happy path:**
- AC1: `/user/register` displays work authorization, job keywords, availability, and resume upload fields in addition to standard Drupal fields.
- AC2: Submitting a valid registration with a PDF resume creates the user account, saves all custom fields, and extracts resume text into `field_resume_text`.
- AC3: After registration, user is redirected to their dashboard at `/user/{uid}` and sees a profile completeness percentage ≥ 50% when required fields are filled.
- AC4: `/user/{uid}/edit` displays all 24 custom fields and allows updates; saving updates the completeness percentage.
- AC5: AI Resume Tailoring at `/user/{uid}/tailor-resume/{job_id}` uses the extracted `field_resume_text` from the profile without requiring any additional setup.

**Failure modes:**
- AC6: Submitting the registration form without work authorization, job keywords, or availability displays inline validation errors and does not create the account.
- AC7: Uploading a file type other than PDF/DOCX (e.g., `.txt`, `.jpg`) produces a specific error: "Resume must be a PDF or DOCX file."
- AC8: Uploading a file larger than 10 MB produces: "Resume file must be under 10 MB."
- AC9: If resume text extraction fails (corrupt file), the account is still created, `field_resume_text` is set to empty string, and an admin error queue entry is logged.

**Verification method:**
- Register a new test user at `/user/register` with a valid PDF resume; assert `/user/{uid}/edit` shows all 24 fields with values.
- Assert `field_resume_text` is populated in the DB for the test user.
- Navigate to `/user/{uid}/tailor-resume/{job_id}` (any existing job posting); assert AI tailoring UI loads without "no resume" error.
- PHPUnit: `JobHunterUserProfileTest` — assert field save, text extraction, completeness calculation.

### Assumptions

1. The 24 custom user fields already exist in the DB schema (confirmed in Gap Analysis).
2. Resume text extraction library (PDF/DOCX) is already present in the codebase (used in existing job posting creation flow).
3. Desired salary is stored as a range (min/max integer, currency assumed USD) — confirm with stakeholder (Q2 below).
4. No email verification step is required at registration (standard Drupal behavior assumed).

### 3–5 Clarifying Questions for Stakeholders

1. **Required vs. optional fields at registration:** Should work authorization, keywords, and availability be required to *complete registration*, or should registration allow empty fields and only require them before job matching is triggered? (Tradeoff: higher-friction registration vs. incomplete profiles blocking matching silently.)
2. **Salary field format:** Is desired salary a single number, a min/max range, or a text field? What unit — annual USD? This affects validation and how the matching algorithm reads it.
3. **Resume re-upload behavior:** When a user re-uploads a resume, should old tailored resumes (`tailored_resume` nodes referencing the previous resume) be flagged as stale or left unchanged? (Data integrity concern.)
4. **Profile completeness formula:** Should all 24 fields count equally toward completeness, or are some fields (resume, keywords) weighted more heavily? Who owns this business rule — PM or Dev?
5. **Work authorization values:** What is the canonical list of work authorization options? (e.g., US Citizen, Green Card, H1B, OPT/STEM OPT, TN, Requires Sponsorship, Other — or a different set.) This must be locked before Dev builds the dropdown.

### Delegation Table

| Role | Action |
|------|--------|
| **PM** | Finalize acceptance criteria AC1–AC9; answer clarifying questions 1–5; confirm salary field format and work authorization value list |
| **Dev (job_hunter)** | Implement Drupal form alter for registration + profile edit; resume text extraction on save; completeness calculation service |
| **QA** | Register test user with PDF resume; verify all 24 fields saved; verify tailoring endpoint uses extracted text; test AC6–AC9 failure modes |
