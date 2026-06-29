# Suite Activation: forseti-jobhunter-profile-completeness

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T12:10:13+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-profile-completeness"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-profile-completeness/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-profile-completeness-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-profile-completeness",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-profile-completeness"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-profile-completeness-<route-slug>",
     "feature_id": "forseti-jobhunter-profile-completeness",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-profile-completeness",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-profile-completeness

- Feature: forseti-jobhunter-profile-completeness
- Module: job_hunter
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify completeness calculation accuracy, missing-field checklist, widget embed on profile summary and home dashboard, and access control.

## Test cases

### TC-1: Anonymous access to profile page → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/profile`
- Expected: `403` or redirect to login.

### TC-2: 100% complete profile — success state shown
- Steps: Log in as a user with all required fields populated. Visit `/jobhunter/profile`.
- Expected: Widget shows "Profile complete" success state. No missing-field checklist rendered.

### TC-3: Incomplete profile — percentage < 100% and checklist shown
- Steps: Log in as a user missing resume_text and education_history. Visit `/jobhunter/profile`.
- Expected: Completeness percentage < 100%; missing fields listed in checklist (at minimum "Resume" and "Education history").

### TC-4: Checklist links are valid
- Steps: Click each link in the missing-field checklist.
- Expected: Each link returns 200 (for authenticated user) and navigates to the relevant profile edit section.

### TC-5: Widget on profile summary page
- Steps: Log in and visit the profile summary route.
- Expected: Completeness widget renders in the page without PHP error.

### TC-6: Widget on home dashboard (incomplete profile)
- Steps: Log in as a user with completeness < 100%. Visit `/jobhunter` (or home route).
- Expected: Completeness widget rendered; shows percentage and checklist.

### TC-7: Widget on home dashboard (complete profile)
- Steps: Log in as a user with 100% completeness. Visit home route.
- Expected: Widget either hidden or shows success state only (no checklist).

### TC-8: Calculation determinism
- Steps: (Unit test) Call `ProfileCompletenessService::calculate($profile)` twice with identical data.
- Expected: Same integer returned both times.

### TC-9: PHP lint clean
- Steps: `php -l src/Service/ProfileCompletenessService.php`
- Expected: `No syntax errors detected`.

### TC-10: Drush cache rebuild after new service
- Steps: `./vendor/bin/drush cr`
- Expected: Exits 0 with no fatal errors.

## Regression notes
- Profile summary route (`/jobhunter/profile`) must still render existing profile fields unchanged.
- Job hunter home route (`/jobhunter`) must still render without error after widget embed.

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-profile-completeness

- Feature: forseti-jobhunter-profile-completeness
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Make the `profile-completeness.html.twig` widget functionally correct: compute completeness from real profile fields, surface a missing-field checklist with direct edit links, and embed the widget on both the profile summary page and the job hunter home dashboard.

## Acceptance criteria

### AC-1: Completeness calculation
- A `ProfileCompletenessService` (or equivalent method on the existing profile service) computes an integer 0–100 from real field presence.
- Fields evaluated (all or subset as documented in `02-implementation-notes.md`): display_name, resume_text, education_history (≥1 entry), work_experience (≥1 entry), skills (≥1 entry).
- Calling the method twice with identical profile data returns the same integer.

### AC-2: Missing field checklist
- When completeness < 100%: the widget renders a list of missing/incomplete field labels.
- Each list item contains a direct link to the profile edit section for that field (e.g., `/jobhunter/profile#education`).
- Links return 200 for authenticated users.

### AC-3: Profile summary page embed
- Completeness widget is rendered at the top of the profile summary route (`/jobhunter/profile` or equivalent).
- Widget renders without PHP error or empty output.

### AC-4: Home dashboard embed
- Completeness widget is embedded on the job hunter home route (`/jobhunter` or `/job-hunter`).
- Widget is not shown (or shows success state only) when completeness = 100%.

### AC-5: Complete state
- When all required fields are populated: widget displays a "Profile complete" success state. No missing-field checklist is shown.

### AC-6: PHP lint clean
- `php -l src/Service/ProfileCompletenessService.php` (or equivalent) exits 0.

## Security acceptance criteria

### Authentication/permission surface
- Completeness widget is only rendered for the currently authenticated user's own profile. No route or controller method exposes another user's completeness data.
- Profile edit links point to routes protected by `_user_is_logged_in: 'TRUE'`.
- Verify: anonymous access to profile route → 403 or redirect.

### CSRF expectations
- Widget is read-only display (no POST). No CSRF token required.

### Input validation requirements
- Completeness score computed entirely server-side. No client-supplied score value is accepted or trusted.
- Field presence checks are boolean (field exists and is non-empty) — no regex or complex parsing that could throw on malformed data.

### PII/logging constraints
- No profile field content (resume text, LinkedIn URL, education history) written to watchdog at any level.
- Completeness integer (0–100) may be logged at debug level if needed for instrumentation.

## Verification commands
```bash
# PHP lint on new service
php -l /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Service/ProfileCompletenessService.php

# Drush cache rebuild (required after new service registration)
cd /home/ubuntu/forseti.life/sites/forseti && ./vendor/bin/drush cr

# Anonymous access check
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/profile
# Expected: 403 or redirect

# Template exists
ls /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/templates/profile-completeness.html.twig
```
