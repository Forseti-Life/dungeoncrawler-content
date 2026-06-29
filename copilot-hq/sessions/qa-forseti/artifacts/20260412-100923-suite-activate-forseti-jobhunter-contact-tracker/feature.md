# Feature Brief

- Work item id: forseti-jobhunter-contact-tracker
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260412-forseti-release-e
- Feature type: new-feature
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: CEO feature brief request 2026-04-12 (release-e)

## Summary

Job seekers who have warm contacts or referrals at target companies have no structured way to track those relationships within the Job Hunter module. This feature adds a per-user contact/referral CRM: a new `jobhunter_contacts` table storing people the user knows at companies. Each contact record holds: name, company (FK to `jobhunter_companies`), role/title, relationship type (warm/cold/referral/recruiter), last contact date, referral status (none/requested/pending/provided), and freetext notes. Users manage contacts at `/jobhunter/contacts`. A contact can optionally be linked to a saved job to surface the relevant network on the job detail view.

## User story

As a job seeker building a professional network, I want to track who I know at each company — their role, how warm the relationship is, and whether they've agreed to refer me — so I can prioritize outreach and never lose track of who to contact next.

## Non-goals

- Email compose or LinkedIn integration (out of scope for v1)
- Shared or team contact lists
- Contact deduplication against external address books

## Acceptance criteria

### AC-1: Add contact form accessible from the contacts list page

Given an authenticated user at `/jobhunter/contacts`, when they click "Add Contact," then a form appears with: name (text, required), company (select from `jobhunter_companies` entries, required), role/title (text, optional), relationship type (select: warm/cold/referral/recruiter), last contact date (date, optional), referral status (select: none/requested/pending/provided), and notes (textarea, optional). The form saves via CSRF-protected AJAX POST.

Verify: `drush sql:query "SELECT name, company_id, relationship_type FROM jobhunter_contacts WHERE uid=<uid>"` → submitted row exists.

### AC-2: Contacts list at /jobhunter/contacts

Given an authenticated user has added 2+ contacts, when they navigate to `/jobhunter/contacts`, then the page lists all their contacts with: name, company, relationship badge, referral status, last contact date, and edit/delete actions.

Verify: `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/contacts` → HTTP 200; page contains contact names.

### AC-3: Contact linked to saved job surfaces on job detail view

Given the user has a contact at the same company as a saved job, when the user views the saved job detail panel, then a "Your contacts here" section lists relevant contacts (matched on company_id) with their name, role, and referral status.

Verify: add a contact with company_id=X; view a saved job at company X → "Your contacts here" section shows that contact.

### AC-4: DB schema — jobhunter_contacts table

Given the module update hook has run, when querying the schema, then the table exists with columns: `id` (serial PK), `uid` (int), `name` (varchar 255, required), `company_id` (int, nullable FK), `role_title` (varchar 255, nullable), `relationship_type` (varchar 16: warm/cold/referral/recruiter), `last_contact_date` (date, nullable), `referral_status` (varchar 16: none/requested/pending/provided), `notes` (text, nullable), `created` (int), `changed` (int).

Verify: `drush sql:query "DESCRIBE jobhunter_contacts"` → all columns present.

### AC-5: Delete contact removes the row

Given the user has a contact row, when they click "Delete" and confirm, then the row is removed from `jobhunter_contacts` and no longer appears in the list.

Verify: `SELECT COUNT(*) FROM jobhunter_contacts WHERE id=<deleted_id>` → 0.

## Security acceptance criteria

- All contact routes require `_user_is_logged_in: 'TRUE'`
- CSRF token required on all POST/DELETE state-changing routes (split-route pattern)
- All DB queries scoped with `uid = currentUser()->id()`; no uid accepted from request params
- Contact name and notes fields stored as plain text; Twig auto-escaping on display (no `|raw`)
- Contact name and email (if added) are PII — must NOT be logged to watchdog at any severity; log only `uid` and contact `id`
