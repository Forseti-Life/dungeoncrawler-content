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
