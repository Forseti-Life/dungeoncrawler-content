# Test Plan: forseti-jobhunter-offer-tracker

- Feature: forseti-jobhunter-offer-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- At least one saved job in offer status
- `jobhunter_offers` table exists

## Test cases

### TC-1: Offer form renders for eligible saved job

- **Type:** functional / smoke
- **When:** open saved-job detail for job in `offer received`
- **Then:** `offer-details` markup is visible

---

### TC-2: Saving offer details creates or updates row

- **Type:** functional / happy path
- **When:** save base salary and notes via AJAX POST
- **Then:** corresponding `jobhunter_offers` row exists or updates in place

---

### TC-3: Offers comparison page renders

- **Type:** functional / page render
- **When:** GET `/jobhunter/offers`
- **Then:** HTTP 200 with comparison markup or correct empty state

---

### TC-4: Cross-user isolation

- **Type:** security / data isolation
- **When:** user B visits `/jobhunter/offers`
- **Then:** no data from user A is visible

---

### TC-5: Unauthenticated and CSRF failures blocked

- **Type:** security
- **When:** GET without auth or POST without valid CSRF token
- **Then:** GET is blocked/redirected and POST returns HTTP 403
