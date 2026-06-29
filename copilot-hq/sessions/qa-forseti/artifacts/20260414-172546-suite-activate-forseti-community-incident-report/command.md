# Suite Activation: forseti-community-incident-report

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-14T17:25:46+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-community-incident-report"`**  
   This links the test to the living requirements doc at `features/forseti-community-incident-report/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-community-incident-report-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-community-incident-report",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-community-incident-report"`**  
   Example:
   ```json
   {
     "id": "forseti-community-incident-report-<route-slug>",
     "feature_id": "forseti-community-incident-report",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-community-incident-report",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-community-incident-report

- Feature: forseti-community-incident-report
- Module: community_incident_report
- Author: pm-forseti (skeleton; QA to elaborate commands)
- Date: 2026-04-13
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- Admin session cookie in `$FORSETI_COOKIE_ADMIN`
- Module `community_incident_report` installed and enabled
- At least one published `community_incident` node in the system

## Test cases

### TC-1: Report form ACL — anonymous redirect

- **Type:** security / ACL
- **When:** GET `/community/report` without auth
- **Then:** 302 redirect to login or 403 Forbidden

---

### TC-2: Report form loads for authenticated user

- **Type:** functional / smoke
- **When:** GET `/community/report` with auth cookie
- **Then:** 200 OK; form renders with incident type, description, location, and date fields

---

### TC-3: Report submission creates unpublished node

- **Type:** functional / happy path
- **When:** authenticated user submits form with all required fields
- **Then:** new `community_incident` node created with `status: 0` (unpublished); confirmation message shown; form clears

---

### TC-4: Anonymous can view public listing

- **Type:** functional / ACL
- **When:** GET `/community-reports` without auth
- **Then:** 200 OK; published nodes listed in reverse-chronological order

---

### TC-5: Listing paginates at 20

- **Type:** functional / edge-case
- **When:** more than 20 published community incident nodes exist
- **Then:** pager appears; page 1 shows 20 items max

---

### TC-6: Admin moderation view accessible

- **Type:** functional / ACL
- **When:** GET `/admin/content/community-reports` as admin
- **Then:** 200 OK; pending and published reports listed with publish/unpublish controls

---

### TC-7: Publish/unpublish toggle works

- **Type:** functional
- **When:** admin uses the publish toggle for an unpublished report
- **Then:** node status changes to published; report appears on `/community-reports`

---

### TC-8: AmISafe map shows community layer toggle

- **Type:** functional / integration
- **When:** GET `/amisafe/crime-map` as authenticated user
- **Then:** "Community Reports" layer toggle is visible; toggling on shows community pins; no JS console errors

---

### TC-9: No regression on /amisafe and /safety-calculator

- **Type:** regression
- **When:** GET `/amisafe` and `/safety-calculator`
- **Then:** both return 200 and load without errors

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-community-incident-report

- Feature: forseti-community-incident-report
- Module: community_incident_report (new)
- Author: pm-forseti (extracted from feature.md)
- Date: 2026-04-13
- Project: PROJ-006

## Summary

Add a community-managed safety observation form at `/community/report` for authenticated users. Published submissions appear at `/community-reports` and as a toggleable layer on the AmISafe crime map. New module `community_incident_report`.

## Acceptance criteria

### AC-1: Content type and fields

**Given** the module is installed,
**When** a content type check is run,
**Then** `community_incident` content type exists with fields: title, description (textarea), incident_type (taxonomy: unsafe_lighting, suspicious_activity, hazard, other), location (address text + lat/lng), occurred_at (datetime), photo (optional image, max 5MB), author (entity ref to user). Config created via `config/install/`.

---

### AC-2: Authenticated incident report form

**Given** an authenticated user visits `/community/report`,
**When** they fill and submit the form,
**Then** a new `community_incident` node is created with `status: 0` (unpublished, pending moderation); anonymous users are redirected to login.

---

### AC-3: Public listing page

**Given** published `community_incident` nodes exist,
**When** any visitor (including anonymous) requests `/community-reports`,
**Then** nodes are listed in reverse-chronological order, paged at 20 per page.

---

### AC-4: AmISafe map layer integration

**Given** the module is active,
**When** a user views the crime map at `/amisafe/crime-map`,
**Then** a toggleable "Community Reports" layer appears showing community incident pins as a visually distinct marker type (different icon/color from official crime data); layer is off by default; no H3 aggregation required.

---

### AC-5: Permissions

**Given** a fresh install,
**When** permissions are reviewed,
**Then** `submit community incident reports` permission controls form access (default: authenticated); `view community incident reports` controls listing access (default: public).

---

### AC-6: Admin moderation view

**Given** an admin user visits `/admin/content/community-reports`,
**When** the page loads,
**Then** pending and published community reports are listed with a one-click publish/unpublish toggle (Drupal core Views + bulk operations acceptable).

---

### AC-7: Submission confirmation

**Given** an authenticated user submits the report form successfully,
**When** submission completes,
**Then** the message "Thank you — your report has been submitted and will appear after review." is shown and the form clears.

## Definition of done

- All 7 ACs pass QA verification on production.
- `/community/report` returns 403 for anonymous, 200 for authenticated.
- `/community-reports` returns 200 for anonymous.
- AmISafe map community layer toggle works without JS console errors.
- No regressions on `/amisafe` or `/safety-calculator`.
- Agent: qa-forseti
- Status: pending
