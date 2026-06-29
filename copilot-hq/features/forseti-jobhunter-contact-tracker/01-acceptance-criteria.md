# Acceptance Criteria: forseti-jobhunter-contact-tracker

- Feature: forseti-jobhunter-contact-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Per-user contact/referral relationship tracker. New table `jobhunter_contacts` keyed by `uid` with fields: contact name, title, company_id (FK to `jobhunter_companies`, nullable), LinkedIn URL, email, relationship type (recruiter/referral/hiring_manager/connection), notes, and timestamps. Contacts surface on the saved-job detail view when the contact's `company_id` matches the job's company, allowing users to see who they know at a target company.

## Acceptance criteria

### AC-1: Contact creation form saves a new contact row

**Given** an authenticated user on the contacts page,
**When** they submit the "Add Contact" form with name="Jane Doe", title="Engineering Manager", company_id=3, relationship_type="hiring_manager", email="jane@example.com",
**Then** a row is created in `jobhunter_contacts` with those values and `uid=currentUser()->id()`.

**Verify:**
```sql
SELECT name, title, company_id, relationship_type
FROM jobhunter_contacts
WHERE uid=<uid> ORDER BY created DESC LIMIT 1;
-- Expected: Jane Doe | Engineering Manager | 3 | hiring_manager
```

---

### AC-2: Contacts page at /jobhunter/contacts

**Given** an authenticated user has added 2+ contacts,
**When** they navigate to `/jobhunter/contacts`,
**Then** the page renders HTTP 200 with a list showing: contact name, title, company name (joined), relationship type, and an edit/delete link per row.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/contacts` → HTTP 200; contains at least one contact row.

---

### AC-3: Contact surfaces on saved-job detail when company matches

**Given** user has a contact with company_id=5 and a saved job application associated with company_id=5,
**When** the user views the application detail page,
**Then** a "Contacts at this company" section appears showing the matching contact's name, title, and relationship type.

**Verify:** Application detail page HTML contains the contact name when `contact.company_id == application.company_id`.

---

### AC-4: DB schema — jobhunter_contacts table

**Given** the module update hook has run,
**When** querying the schema,
**Then** the table `jobhunter_contacts` exists with columns: `id` (serial PK), `uid` (int), `name` (varchar 255), `title` (varchar 255, nullable), `company_id` (int, nullable), `linkedin_url` (varchar 512, nullable), `email` (varchar 255, nullable), `relationship_type` (varchar 32), `notes` (text, nullable), `created` (int), `changed` (int). Index on `(uid)`.

**Verify:** `drush sql:query "DESCRIBE jobhunter_contacts"` → all columns present.

---

### AC-5: Edit and delete contact rows

**Given** user has an existing contact row with id=12,
**When** they submit an edit form changing `title` to "VP Engineering",
**Then** the row is updated in-place with `changed` timestamp updated.

**When** they click delete and confirm,
**Then** the row is deleted: `SELECT COUNT(*) FROM jobhunter_contacts WHERE id=12` → 0.

---

## Security acceptance criteria

### SEC-1: Authentication required
All contacts routes require `_user_is_logged_in: 'TRUE'`. Unauthenticated access returns 403.

### SEC-2: CSRF protection on POST/DELETE
Save and delete routes are POST-only with `_csrf_token: 'TRUE'` (split-route pattern).

### SEC-3: UID-scoped queries
All SELECT, UPDATE, DELETE queries include `WHERE uid = currentUser()->id()`. Contact ID alone is insufficient — ownership must be verified server-side.

### SEC-4: Input sanitization
All text fields (name, title, notes, email, LinkedIn URL) stored as plain text. LinkedIn URL validated to `https://linkedin.com/...` pattern before save. Email validated against RFC format. Display uses Twig auto-escaping only.

### SEC-5: No PII in logs
Contact name, email, and LinkedIn URL must NOT appear in watchdog logs at any severity. Log only `uid` and contact `id` for mutations.
