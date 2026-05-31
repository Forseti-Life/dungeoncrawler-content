# Feature Brief

- Work item id: forseti-jobhunter-profile-refactor
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 1
- Group: profile-resume
- Group Title: Profile & Resume
- Group Sort: 2
- Status: shipped
- Release: 20260408-forseti-release-b
- Priority: P2
- Feature type: refactor
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Source: BA inventory JH-R3 (ROI 12)

## Goal

Extract two high-risk sections from `UserProfileForm.php` (7425 lines) into dedicated subforms/components: (1) education history section → `EducationHistorySubform`, (2) resume upload/management → `ResumeUploadSubform`. The main form becomes a coordinator. This reduces collision risk on the most-frequently-changed user-facing form.

## Non-goals

- Rewriting the entire UserProfileForm in one cycle (incremental extraction only).
- Changing form behavior, field names, routes, or user-visible UI.
- Touching the consolidated JSON sync logic (separate concern, deferred).

## PM Decision

Phase 1: extract education history and resume upload sections only. Keep form API element keys and submit handler signatures identical so existing tests pass unchanged.

## Acceptance Criteria

See: features/forseti-jobhunter-profile-refactor/01-acceptance-criteria.md

## Risks

- UserProfileForm is the primary user-facing form; regressions affect active job seekers.
- Resume delete CSRF fix (job_hunter.delete_resume) must remain intact post-extraction.
- Education history persistence must be validated end-to-end.

## Security acceptance criteria
- Security AC exemption: pure form-structure extraction refactor — no new routes, no new permissions, no new user input surfaces introduced. CSRF protection on `job_hunter.delete_resume` (POST + `_csrf_token: 'TRUE'`) must be preserved post-extraction; this is explicitly verified in AC-3.

## Latest updates

- 2026-04-08: Scoped into release — suite activation sent to QA.

- 2026-04-08: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-08: Handed off to QA for test generation (pm-qa-handoff.sh)
- 2026-04-08: Feature brief created from BA inventory JH-R3. Groomed for 20260408-forseti-release-c.
