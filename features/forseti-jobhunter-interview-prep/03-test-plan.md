# Test Plan: forseti-jobhunter-interview-prep

- Feature: forseti-jobhunter-interview-prep
- Module: job_hunter
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify checklist display, notes save/load, AI tips AJAX, My Jobs link, DB schema, and access control at `/jobhunter/interview-prep/{job_id}`.

## Test cases

### TC-1: Anonymous access → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/interview-prep/1`
- Expected: `403`

### TC-2: Page load — checklist and notes textarea rendered
- Steps: Log in; access an interview-stage job.
- Expected: Static checklist (≥5 items) and notes textarea visible.

### TC-3: Notes — save and reload
- Steps: Type notes; POST save; reload page.
- Expected: Notes pre-populated from DB on reload.

### TC-4: Notes save — invalid CSRF → 403
- Steps: POST to `job_hunter.interview_prep_save` without CSRF token.
- Expected: 403.

### TC-5: Notes — over 10,000 chars rejected
- Steps: POST notes > 10,000 chars.
- Expected: 400 with error message; no data saved.

### TC-6: AI tips — valid CSRF → inline response
- Steps: Click "Get AI Interview Tips" (valid CSRF).
- Expected: 3–5 tips rendered inline; no page reload.

### TC-7: AI tips — invalid CSRF → 403
- Steps: POST to AI tips endpoint without CSRF.
- Expected: 403.

### TC-8: AI tips — API error → user-facing message
- Steps: Simulate API failure.
- Expected: Inline error shown; no white screen.

### TC-9: My Jobs — "Prepare for Interview" link present
- Steps: Log in; view `/jobhunter/my-jobs`; find an interview-stage job card.
- Expected: "Prepare for Interview" link → `/jobhunter/interview-prep/{job_id}`.

### TC-10: DB table created
- Steps: `./vendor/bin/drush php:eval "var_dump(\Drupal::database()->schema()->tableExists('jobhunter_interview_notes'));"`
- Expected: `bool(true)`.

### TC-11: Cross-user notes access → 403/404
- Steps: User A accesses prep page for User B's job.
- Expected: 403 or 404.

### TC-12: Non-integer job_id → 404
- Steps: GET `/jobhunter/interview-prep/notanid`
- Expected: 404.

## Regression notes
- Existing `job_hunter.interview_followup` route must still be accessible (do not break it).
- `./vendor/bin/drush router:debug | grep interview` should show both routes.
