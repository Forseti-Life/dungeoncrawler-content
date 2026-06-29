# Test Plan: forseti-jobhunter-interview-outcome-tracker

- Feature: forseti-jobhunter-interview-outcome-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- At least one saved job for the test user
- `jobhunter_interview_rounds` table exists

## Test cases

### TC-1: Interview round form renders on saved-job detail

- **Type:** functional / smoke
- **When:** open saved-job detail
- **Then:** interview-round form is visible

---

### TC-2: Saving new round inserts row

- **Type:** functional / happy path
- **When:** save a phone-screen round with outcome `pending`
- **Then:** row exists in `jobhunter_interview_rounds`

---

### TC-3: Round log ordering is chronological

- **Type:** functional / sorting
- **When:** multiple rounds exist with different dates
- **Then:** rendered order matches ascending `conducted_date`

---

### TC-4: Updating existing round does not duplicate

- **Type:** functional / idempotency
- **When:** update the same round from `pending` to `passed`
- **Then:** count remains 1 and outcome changes

---

### TC-5: Cross-user isolation

- **Type:** security / data isolation
- **When:** user B views same saved job id with no round rows
- **Then:** no user A round data is visible

---

### TC-6: Unauthenticated and CSRF failures blocked

- **Type:** security
- **When:** GET/POST without auth or POST without valid CSRF token
- **Then:** requests are blocked appropriately
