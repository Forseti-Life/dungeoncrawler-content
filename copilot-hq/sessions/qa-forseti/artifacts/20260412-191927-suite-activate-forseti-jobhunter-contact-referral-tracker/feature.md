# Feature Brief

- Work item id: forseti-jobhunter-contact-referral-tracker
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260412-forseti-release-e
- Feature type: new-feature
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: CEO feature brief request 2026-04-12 (release-e)

## Summary

Job seekers often leverage professional contacts and referrals as their highest-value application channel, yet the Job Hunter module has no way to track contacts at target companies. This feature adds a contact/referral tracker: a `jobhunter_contacts` table per user storing contacts at companies (name, title, company, LinkedIn URL, relationship type, last contact date, referral status, notes). A contact list view is available at `/jobhunter/contacts`. Contacts can optionally be linked to a saved job to record who referred the user to that role.

## User story

As a job seeker building a referral network, I want to track my professional contacts at target companies, record when I last reached out, and note whether they've referred me to a role, so I can maintain warm relationships and prioritize high-value referral paths.

## Non-goals

- Email/calendar integration for contact outreach (separate notification feature)
- LinkedIn API sync (future; URL field enables manual entry for now)
- Sharing contact lists across users
- CRM pipeline automation

## Acceptance criteria

### AC-1: Contact list view at /jobhunter/contacts

Given an authenticated user with at least one saved contact, when they navigate to `/jobhunter/contacts`, then the page displays a list of their contacts with: name, company, relationship type, last contact date, referral status badge, and a link to the contact detail view.

Verify: `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/contacts` → HTTP 200; page contains contact list markup.

### AC-2: Add/edit contact form

Given an authenticated user, when they click "Add Contact," then a form appears with fields: full name (text, required), job title (text, optional), company name (text, required), LinkedIn URL (text, optional), relationship type (select: colleague, recruiter, friend, alumni, other), last contact date (date, optional), referral status (select: none, referred, pending-referral), notes (textarea, optional). On save, a row is created/updated in `jobhunter_contacts`.

Verify: `drush sql:query "SELECT full_name, company_name, referral_status FROM jobhunter_contacts WHERE uid=<uid>"` → returns saved values.

### AC-3: Link contact to a saved job

Given an existing contact and a saved job, when the user links the contact to the saved job via the contact detail form, then the association is stored in `jobhunter_contact_job_links` (or equivalent). The saved-job detail view shows a "Referred by: [Name]" note when a linked contact has `referral_status=referred`.

Verify: `drush sql:query "SELECT contact_id FROM jobhunter_contact_job_links WHERE saved_job_id=<id> AND uid=<uid>"` → returns contact_id; saved-job detail page contains referred-by text.

### AC-4: DB schema — jobhunter_contacts table

Given the module update hook has run, when querying the schema, then the table `jobhunter_contacts` exists with columns: `id` (serial PK), `uid` (int), `full_name` (varchar 255), `job_title` (varchar 255, nullable), `company_name` (varchar 255), `linkedin_url` (varchar 512, nullable), `relationship_type` (varchar 32), `last_contact_date` (date, nullable), `referral_status` (varchar 20: none/referred/pending-referral), `notes` (text, nullable), `created` (int), `changed` (int).

Verify: `drush sql:query "DESCRIBE jobhunter_contacts"` → all expected columns.

### AC-5: Contact data is per-user — no cross-user leakage

Given user A has contacts and user B has none, when user B visits `/jobhunter/contacts`, then user B sees an empty contact list. No data from user A is exposed.

Verify: `drush sql:query "SELECT COUNT(*) FROM jobhunter_contacts WHERE uid=<uid_B>"` → 0; page shows empty-state prompt.

## Security acceptance criteria

- `/jobhunter/contacts` and all contact POST endpoints require `_user_is_logged_in: 'TRUE'`
- CSRF token required on all state-changing POST routes (split-route pattern)
- Controller verifies `uid = currentUser()->id()` on all reads/writes; no uid accepted from URL
- LinkedIn URL must be validated as an HTTP/HTTPS URL; no `javascript:` or `data:` URIs stored
- Full name and notes stored as plain text; Twig auto-escaping on display (no `|raw`)
- Contact PII (name, LinkedIn URL) must NOT be logged to watchdog at debug severity; log only `uid` and contact `id`
