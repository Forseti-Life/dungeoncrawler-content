# Acceptance Criteria: forseti-jobhunter-contact-referral-tracker

- Feature: forseti-jobhunter-contact-referral-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Add a per-user contact/referral tracker to Job Hunter. Users can manage a
contact list at `/jobhunter/contacts`, store referral-oriented contact details
in `jobhunter_contacts`, and optionally link a contact to a saved job so the
job detail view can show who referred or is connected to that role.

## Acceptance criteria

### AC-1: Contact list view at /jobhunter/contacts

**Given** an authenticated user with at least one saved contact,
**When** they navigate to `/jobhunter/contacts`,
**Then** the page displays a list of their contacts with name, company,
relationship type, last contact date, referral status badge, and a link to the
detail/edit view.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/contacts | grep -q 'contact'`

---

### AC-2: Add/edit contact form

**Given** an authenticated user,
**When** they add or edit a contact,
**Then** the form supports: full name, job title, company name, LinkedIn URL,
relationship type, last contact date, referral status, and notes; saving
creates or updates a row in `jobhunter_contacts`.

**Verify:** `drush sql:query "SELECT full_name, company_name, referral_status FROM jobhunter_contacts WHERE uid=<uid>"`

---

### AC-3: Contact can be linked to a saved job

**Given** an existing contact and a saved job,
**When** the user links the contact to that saved job,
**Then** the association is stored and the saved-job detail page shows a
`Referred by` or equivalent contact note when the linked contact is marked as a
referral.

**Verify:** query `jobhunter_contact_job_links` (or equivalent) for the saved
job and confirm the detail page renders the linked contact.

---

### AC-4: DB schema — jobhunter_contacts table

**Given** the module update hook has run,
**When** querying the schema,
**Then** `jobhunter_contacts` exists with fields for uid, full name, job title,
company name, LinkedIn URL, relationship type, last contact date, referral
status, notes, created, and changed.

**Verify:** `drush sql:query "DESCRIBE jobhunter_contacts"`

---

### AC-5: Contact data is isolated per user

**Given** user A has contacts and user B does not,
**When** user B visits `/jobhunter/contacts`,
**Then** user B sees an empty list and no data from user A.

**Verify:** `drush sql:query "SELECT COUNT(*) FROM jobhunter_contacts WHERE uid=<uid_B>"`

---

## Security acceptance criteria

### SEC-1: Authentication required

All contact/referral routes require `_user_is_logged_in: 'TRUE'`.

### SEC-2: CSRF on state-changing routes

All POST routes for create/update/link operations use the split-route CSRF
pattern.

### SEC-3: Strict ownership checks

All reads and writes are scoped to `currentUser()->id()`; no uid parameter is
accepted from request input.

### SEC-4: LinkedIn URL validation

Only HTTP/HTTPS LinkedIn URLs may be stored; reject `javascript:` / `data:`
schemes with HTTP 422.

### SEC-5: PII-safe logging

Do not log contact names, LinkedIn URLs, or notes in watchdog. Log only `uid`
and contact id / saved-job id.
