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
