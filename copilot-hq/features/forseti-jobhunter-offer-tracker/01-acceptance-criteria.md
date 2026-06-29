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
