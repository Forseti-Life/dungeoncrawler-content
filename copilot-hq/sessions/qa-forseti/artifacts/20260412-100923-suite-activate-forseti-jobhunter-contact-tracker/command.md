- Status: done
- Completed: 2026-04-12T11:38:57Z

# Suite Activation: forseti-jobhunter-contact-tracker

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-12T10:09:23+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-contact-tracker"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-contact-tracker/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-contact-tracker-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-contact-tracker",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-contact-tracker"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-contact-tracker-<route-slug>",
     "feature_id": "forseti-jobhunter-contact-tracker",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-contact-tracker",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-contact-tracker

- Feature: forseti-jobhunter-contact-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- `jobhunter_contacts` table exists: `drush sql:query "DESCRIBE jobhunter_contacts"`
- At least one company in `jobhunter_companies` (for company_id FK tests)

## Test cases

### TC-1: Create new contact (smoke)

- **Type:** functional / smoke
- **Given:** authenticated user
- **When:** submit "Add Contact" form with name="Jane Doe", relationship_type="recruiter"
- **Then:** HTTP 200 redirect; row in `jobhunter_contacts`
- **Verify:** `SELECT name FROM jobhunter_contacts WHERE uid=<uid> ORDER BY created DESC LIMIT 1` → `Jane Doe`

---

### TC-2: Contacts list page renders

- **Type:** functional / smoke
- **Given:** user has 2+ contacts
- **When:** GET `/jobhunter/contacts`
- **Then:** HTTP 200; contact names visible
- **Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/contacts` → HTTP 200, contains contact name

---

### TC-3: Contact surfaces on application detail (company match)

- **Type:** functional / integration
- **Given:** contact with company_id=5; application with company_id=5
- **When:** GET application detail page
- **Then:** "Contacts at this company" section shows contact name and title
- **Verify:** application detail HTML contains contact name

---

### TC-4: Contact does NOT surface when company does not match

- **Type:** functional / negative
- **Given:** contact with company_id=5; application with company_id=9
- **When:** GET application detail page
- **Then:** "Contacts at this company" section is absent or empty
- **Verify:** application detail HTML does NOT contain the contact's name

---

### TC-5: Edit contact updates in-place

- **Type:** functional / update
- **Given:** contact id=12 with title="Manager"
- **When:** submit edit form with title="VP Engineering"
- **Then:** DB row updated; list page reflects new title
- **Verify:** `SELECT title FROM jobhunter_contacts WHERE id=12 AND uid=<uid>` → `VP Engineering`

---

### TC-6: Delete contact removes row

- **Type:** functional / delete
- **Given:** contact id=12 exists
- **When:** POST delete action for id=12
- **Then:** row deleted
- **Verify:** `SELECT COUNT(*) FROM jobhunter_contacts WHERE id=12` → 0

---

### TC-7: Cross-user isolation — user B cannot access user A's contact

- **Type:** security / data isolation
- **Given:** user A has contact id=12
- **When:** user B POST-edits or deletes contact id=12
- **Then:** HTTP 403 or no change to user A's data
- **Verify:** `SELECT uid FROM jobhunter_contacts WHERE id=12` → still `uid_A`

---

### TC-8: Unauthenticated access returns 403

- **Type:** security / auth gate
- **When:** GET `/jobhunter/contacts` without session cookie
- **Then:** HTTP 403 or redirect to login
- **Verify:** `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/jobhunter/contacts` → `403` or `302`

---

### TC-9: CSRF required on POST

- **Type:** security / CSRF
- **Given:** authenticated session
- **When:** POST without valid CSRF token
- **Then:** HTTP 403
- **Verify:** POST without `X-CSRF-Token` → 403

---

### TC-10: LinkedIn URL validation rejects non-LinkedIn URLs

- **Type:** security / input validation
- **Given:** authenticated user
- **When:** submit form with `linkedin_url='https://evil.example.com/fake'`
- **Then:** form validation error; row NOT saved
- **Verify:** `SELECT COUNT(*) FROM jobhunter_contacts WHERE linkedin_url='https://evil.example.com/fake'` → 0

### Acceptance criteria (reference)

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
- Agent: qa-forseti
- Status: pending
