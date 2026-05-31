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
