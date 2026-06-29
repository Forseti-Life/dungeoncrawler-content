- Status: done
- Completed: 2026-04-14T17:40:06Z

# Suite Activation: forseti-jobhunter-contact-referral-tracker

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-14T17:26:05+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-contact-referral-tracker"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-contact-referral-tracker/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-contact-referral-tracker-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-contact-referral-tracker",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-contact-referral-tracker"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-contact-referral-tracker-<route-slug>",
     "feature_id": "forseti-jobhunter-contact-referral-tracker",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-contact-referral-tracker",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-contact-referral-tracker

- Feature: forseti-jobhunter-contact-referral-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- At least one saved job for the test user
- `jobhunter_contacts` table exists

## Test cases

### TC-1: Contacts page renders for authenticated user

- **Type:** functional / smoke
- **When:** GET `/jobhunter/contacts`
- **Then:** HTTP 200 and contact list markup is present

---

### TC-2: Add contact creates DB row

- **Type:** functional / happy path
- **When:** save a contact with full name, company name, relationship type, and referral status
- **Then:** DB row exists in `jobhunter_contacts`
- **Command:** `drush sql:query "SELECT full_name, company_name, referral_status FROM jobhunter_contacts WHERE uid=<uid>"`

---

### TC-3: Edit contact updates row without duplication

- **Type:** functional / update path
- **When:** edit an existing contact
- **Then:** row updates and count remains 1 for that contact id

---

### TC-4: Link contact to saved job

- **Type:** functional / association
- **When:** link a saved contact to a saved job
- **Then:** association row exists and saved-job detail shows referral text

---

### TC-5: Cross-user isolation

- **Type:** security / data isolation
- **When:** user B opens contacts page with no contacts
- **Then:** no user A contact data is visible

---

### TC-6: LinkedIn URL validation

- **Type:** security / validation
- **When:** POST a `javascript:` LinkedIn URL
- **Then:** HTTP 422 and no malicious URL is stored

---

### TC-7: Unauthenticated access blocked

- **Type:** security / auth gate
- **When:** GET `/jobhunter/contacts` without session cookie
- **Then:** HTTP 403 or redirect to login

---

### TC-8: CSRF required on save/link routes

- **Type:** security / CSRF
- **When:** POST to save or link endpoint without valid CSRF token
- **Then:** HTTP 403

### Acceptance criteria (reference)

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
