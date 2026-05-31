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
