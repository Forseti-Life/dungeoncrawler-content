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
