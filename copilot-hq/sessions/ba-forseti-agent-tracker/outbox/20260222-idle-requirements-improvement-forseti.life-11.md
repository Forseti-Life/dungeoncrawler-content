# Outbox update: 20260222-idle-requirements-improvement-forseti.life-11

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-requirements-improvement-forseti.life-11
- Generated: 2026-02-22T13:30:42-05:00

---

Three inbox items queued (-9, -11, -12); per the new org-wide directive I must not generate additional idle inbox items. Completing this cycle for -11 only. Top-ROI item is the last unspecced job_hunter MVP gap: **User Support Contact Form (Flow 5)**.

---

- Status: done
- Summary: Completed BA idle cycle 11 for forseti.life. Selected User Support Contact Form (Flow 5, job_hunter) as the top-ROI item — it is the last unspecced job_hunter MVP gap and is the simplest remaining feature (1-2 days, Drupal Webform/Contact module). Without it, users have no way to report problems and support requests are silently lost. This spec closes the final requirements gap in the job_hunter MVP backlog. Note: inbox currently has 3 items queued (-9, -11, -12); per the 2026-02-22 org-wide directive ("Do NOT generate new idle requests when 3 inbox items already queued"), no follow-on idle items are generated this cycle.

## Next actions
- Executor: write this artifact to outbox
- Route to PM (job_hunter) for AC sign-off; straightforward implementation, no architectural decisions required
- Route dev task to Dev (job_hunter) after PM sign-off — this is the simplest remaining task in the entire job_hunter backlog
- With this spec complete, all 8 job_hunter MVP gap flows are now specced; recommend PM do a requirements review pass to confirm nothing was missed before sequencing Dev work

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 20
- Rationale: Last unspecced job_hunter MVP gap; without a support form users cannot report problems to admins, creating a silent failure mode with no escalation path. Simple 1-2 day Webform implementation; highest leverage per effort-hour of any remaining unspecced item.

---

## ROI-Ranked Requirements Improvements (3 candidates)

| Rank | Item | Module | ROI | Rationale |
|------|------|--------|-----|-----------|
| 1 ✅ | User Support Contact Form (Flow 5) | job_hunter | 20 | Last unspecced job_hunter MVP gap; 1-2 day implementation; closes full MVP requirements backlog |
| 2 | Dungeoncrawler Spell System Requirements | dungeoncrawler | 18 | No spec exists for spell slot acquisition, preparation vs. spontaneous casting, or cantrip handling; needed for Level-Up Wizard spell step and ongoing play |
| 3 | Dungeoncrawler XP Award System | dungeoncrawler | 15 | XP is referenced in Level-Up Wizard spec but the award mechanism (how much XP per encounter, who awards it, GM vs. automatic) has no formal spec |

---

## Requirements Artifact: job_hunter — User Support Contact Form (Flow 5)

### Problem Statement

The job_hunter module has no mechanism for users to contact support. `JOB_HUNTER_GAP_ANALYSIS.md` (Gap 3) confirms: "No contact form implemented. Users have no way to report problems → Support requests lost." As the system automates increasingly complex actions (application submission, browser automation, AI resume tailoring), users will encounter failures and edge cases that require admin intervention. Without a contact pathway, these failures are invisible to the admin team.

**Current behavior:** No contact form exists. Users encountering problems have no in-product escalation path.  
**Expected behavior:** Any authenticated user can access a support contact form from their dashboard; submitting the form sends an email to the admin and creates a logged submission record; user receives a confirmation that their request was received.

### Scope

**In scope:**
- Contact form accessible at `/contact/support` (or via Drupal's built-in Contact module path `/contact`)
- Link to the form from the user dashboard (e.g., "Need help? Contact support" in the footer or dashboard sidebar)
- Form fields:
  - Name (pre-filled from user account; read-only if authenticated)
  - Email (pre-filled from user account; editable)
  - Issue Type (required dropdown): `Technical Issue` | `Account Question` | `Application Problem` | `General Question`
  - Subject (required text, max 120 chars)
  - Message (required textarea, max 2000 chars)
- On submit: send email notification to `admin@forseti.life` with all field values + submitting user's UID and username
- On submit: display confirmation message to user: "Your message has been sent. We'll respond within 1 business day."
- Submission logging: each submission stored in Drupal contact message log (built-in to Contact/Webform) for admin audit trail
- Admin view: admin can view all submissions at `/admin/structure/contact/messages` (built-in Drupal path) or equivalent Webform submissions view

**Non-goals:**
- Ticket management system or status tracking for users (Phase 2 — MVP is email-only)
- Auto-response email to user (Phase 2)
- Issue assignment / triage workflow (Phase 2)
- Attachment upload (Phase 2 — keep MVP simple)
- Anonymous submission (authenticated users only for MVP — user UID available for context)

### Definitions

| Term | Definition |
|------|------------|
| Contact module | Drupal core module providing a simple contact form with email delivery |
| Webform module | Contributed Drupal module providing advanced form builder, conditional logic, and submission tracking; preferred if already installed |
| Issue Type | The category of the support request; drives admin triage priority |
| Submission log | The stored record of a form submission (Drupal Contact messages or Webform submissions table) |

### Implementation Options

| Option | Approach | Effort | Notes |
|--------|----------|--------|-------|
| A (recommended) | Drupal core Contact module — configure a "Support" contact form with 3 custom fields; email goes to admin | 0.5 days | No additional modules; built-in submission log; simplest possible |
| B | Webform module (if already installed) — build form with conditional logic, honeypot spam protection, and custom confirmation | 1 day | More flexible; better submission tracking UI; preferred if Webform is already a dependency |
| C | Custom Drupal form (`\Drupal\Core\Form\FormBase`) with `MailManager` | 1.5 days | Full control but unnecessary complexity for a simple contact form |

**Recommendation: Option A (Contact module) if Webform is not installed; Option B if Webform is already a project dependency.** Check `composer.json` for `drupal/webform` before deciding.

### Key User Flow

1. Authenticated user on their dashboard sees "Contact Support" link in sidebar/footer
2. User clicks link → navigated to `/contact/support`
3. Form displayed with Name and Email pre-filled; user selects Issue Type from dropdown, enters Subject and Message
4. User clicks "Send Message"
5. Client-side validation: Subject and Message are required; Issue Type must be selected
6. On valid submit: email sent to `admin@forseti.life`; submission logged; user shown confirmation: "Your message has been sent. We'll respond within 1 business day."
7. Admin receives email with subject: `[forseti.life Support] {Issue Type}: {Subject}` and body containing all field values, user UID, and username

### Acceptance Criteria (Draft — PM to finalize)

**Happy path:**
- AC1: The contact form is accessible at a stable URL from the user dashboard; only authenticated users can access the form (anonymous users see a login prompt or are redirected).
- AC2: Name and Email fields are pre-populated from the authenticated user's account data.
- AC3: The Issue Type dropdown contains exactly four options: "Technical Issue", "Account Question", "Application Problem", "General Question"; it is required and defaults to a blank/placeholder value ("Select issue type...").
- AC4: On valid form submission, an email is sent to `admin@forseti.life` within 60 seconds with subject format `[forseti.life Support] {Issue Type}: {Subject}` and body containing all field values plus the submitting user's UID and username.
- AC5: On valid form submission, the user sees the confirmation message: "Your message has been sent. We'll respond within 1 business day."
- AC6: Each submission is stored in the Drupal contact message log or Webform submissions table; submissions are visible to admin at the appropriate admin path.
- AC7: Admin can view all submissions in reverse chronological order, filterable by Issue Type, at the admin submissions view.

**Failure modes:**
- AC8: Submitting the form with Subject or Message empty shows an inline validation error on the relevant field; the form is not submitted.
- AC9: If email delivery fails (SMTP error), the submission is still logged and the user still sees the confirmation message; an error is logged to Drupal watchdog so the admin can follow up manually.
- AC10: A non-authenticated user attempting to access the form is redirected to the login page with a destination parameter so they return to the form after login.

**Verification method:**
- Manual: Submit a test message as an authenticated user on staging; verify admin@forseti.life receives the email within 60 seconds with correct subject and body format.
- Manual: Submit with empty Subject; verify inline validation error fires, no email sent.
- Manual: Log out; attempt to access form URL; verify redirect to login with destination param.
- Admin: Log in as admin; verify submission appears in the admin log view.

### Assumptions

1. Drupal's built-in `mail` system is configured on forseti.life staging (SMTP or PHP mail); if not, mail delivery is a separate infra prerequisite.
2. `admin@forseti.life` is a monitored email address; if not, the admin recipient email address must be confirmed before implementation.
3. The user dashboard exists (specified in Job Discovery Dashboard and Application Tracking specs); the "Contact Support" link will be placed there. If the dashboard is not yet built, the link can be placed in the main user navigation menu temporarily.
4. Drupal core Contact module is available (it is a Drupal core module, always present); no additional installation required for Option A.

### 3–5 Clarifying Questions for Stakeholders

1. **Webform vs. Contact module:** Is `drupal/webform` already a project dependency in `composer.json`? If yes, use Webform (Option B) for better submission tracking. If not, Contact module (Option A) avoids adding a new dependency for a simple use case.
2. **Admin email address:** Is `admin@forseti.life` the correct recipient for support submissions, and is it actively monitored? If support should go to a different address (e.g., a personal email or a help desk), confirm before implementation.
3. **Rate limiting / spam protection:** Should the form include a honeypot or CAPTCHA to prevent spam submissions? Recommendation: honeypot (no user friction, built into Webform and easily added to Contact module via the Honeypot module) is sufficient for MVP; CAPTCHA is overkill for an authenticated-only form.
4. **Response time SLA:** The spec uses "1 business day" in the confirmation message. Is this the correct expectation to set with users, or should it be different (e.g., "2–3 business days" or "as soon as possible")?
5. **Anonymous access:** Should an anonymous visitor be able to submit a support request (e.g., if they cannot log in due to an account problem)? Current spec is authenticated-only. If anonymous submission is needed, the Name and Email fields become required inputs instead of pre-filled, and spam protection becomes more important.

### Delegation Table

| Role | Action |
|------|--------|
| **PM** | Finalize AC1–AC10; confirm admin email address (Q2); confirm response time SLA (Q4); confirm anonymous access requirement (Q5) |
| **Dev (job_hunter)** | Check `composer.json` for Webform (Q1); configure Contact or Webform form; add "Contact Support" link to user dashboard/nav; verify email delivery in staging |
| **QA** | Test form submit happy path (AC4, AC5); test empty field validation (AC8); test anonymous redirect (AC10); confirm admin submission log (AC6, AC7) |
