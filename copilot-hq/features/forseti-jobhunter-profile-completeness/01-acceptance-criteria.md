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
