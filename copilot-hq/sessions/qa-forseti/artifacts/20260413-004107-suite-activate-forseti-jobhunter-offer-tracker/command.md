- Status: done
- Completed: 2026-04-13T02:54:55Z

# Suite Activation: forseti-jobhunter-offer-tracker

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-13T00:41:07+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-offer-tracker"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-offer-tracker/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-offer-tracker-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-offer-tracker",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-offer-tracker"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-offer-tracker-<route-slug>",
     "feature_id": "forseti-jobhunter-offer-tracker",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-offer-tracker",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-offer-tracker

- Feature: forseti-jobhunter-offer-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Add structured offer tracking for saved jobs that reach `offer received`. Users
can record offer details on the saved-job detail view and compare active offers
side-by-side at `/jobhunter/offers`.

## Acceptance criteria

### AC-1: Offer details form on saved-job detail view

**Given** an authenticated user whose saved job has status `offer received`,
**When** they view the detail panel,
**Then** an `Offer Details` form is visible with base salary, equity/bonus
summary, benefits summary, response deadline, and notes; saving happens via
CSRF-protected AJAX POST.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -q 'offer-details'`

---

### AC-2: Offer comparison page at /jobhunter/offers

**Given** an authenticated user with at least two saved jobs with recorded offer
details,
**When** they navigate to `/jobhunter/offers`,
**Then** all active offers are shown in a comparison layout with company, role,
base salary, equity, deadline, and a link back to the job detail.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/offers`

---

### AC-3: Offer data is scoped to current user

**Given** user A has an offer and user B does not,
**When** user B visits `/jobhunter/offers`,
**Then** user B sees only their own empty-state or their own offers.

**Verify:** `drush sql:query "SELECT COUNT(*) FROM jobhunter_offers WHERE uid=<uid_B>"`

---

### AC-4: DB schema — jobhunter_offers table

**Given** the module update hook has run,
**When** querying the schema,
**Then** `jobhunter_offers` exists with uid, saved_job_id, base salary, equity
summary, benefits summary, response deadline, notes, created, and changed.

**Verify:** `drush sql:query "DESCRIBE jobhunter_offers"`

---

## Security acceptance criteria

### SEC-1: Authentication required

All offer routes require `_user_is_logged_in: 'TRUE'`.

### SEC-2: CSRF on POST save route

Offer save/update uses the split-route CSRF pattern.

### SEC-3: Ownership check on saved jobs

Controller verifies the saved job belongs to `currentUser()->id()` before any
offer row is created or updated.

### SEC-4: Safe text rendering

Salary-adjacent free text and notes are stored as plain text and rendered with
Twig auto-escaping only.

### SEC-5: No sensitive debug logging

Do not log salary values or offer notes at debug severity.
