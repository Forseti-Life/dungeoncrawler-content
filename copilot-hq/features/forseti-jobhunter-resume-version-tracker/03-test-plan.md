# Test Plan: forseti-jobhunter-resume-version-tracker

- Feature: forseti-jobhunter-resume-version-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- At least one application row for the test user
- At least one base resume and one tailored resume
- Updated `jobhunter_applications` schema

## Test cases

### TC-1: Submitted resume section renders on application detail

- **Type:** functional / smoke
- **When:** open application detail
- **Then:** `Resume submitted` section is visible for linked applications

---

### TC-2: Updating linked resume persists id/type

- **Type:** functional / happy path
- **When:** select a different owned resume and save
- **Then:** `submitted_resume_id` and `submitted_resume_type` update in DB

---

### TC-3: Resume detail shows where-used list

- **Type:** functional / render
- **When:** open a resume detail page for a resume used by applications
- **Then:** `resume-used-in` section lists matching applications/jobs

---

### TC-4: Cross-user ownership enforced

- **Type:** security / authorization
- **When:** attempt to link another user's resume
- **Then:** HTTP 403 and no DB change

---

### TC-5: Unauthenticated and CSRF failures blocked

- **Type:** security
- **When:** GET/POST without auth or POST without valid CSRF token
- **Then:** requests are blocked appropriately
