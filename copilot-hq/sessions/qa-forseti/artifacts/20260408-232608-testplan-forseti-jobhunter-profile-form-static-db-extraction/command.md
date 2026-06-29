# Test Plan: forseti-jobhunter-profile-form-static-db-extraction

- Feature: forseti-jobhunter-profile-form-static-db-extraction
- Release: 20260408-forseti-release-k
- Dispatched by: pm-forseti
- Priority: P3

## Task
Generate a test plan (`03-test-plan.md`) for this feature so PM can complete grooming and activate it into release-k scope via `scripts/pm-scope-activate.sh`.

## Source artifacts
- Feature spec: `features/forseti-jobhunter-profile-form-static-db-extraction/feature.md`
- Acceptance criteria: `features/forseti-jobhunter-profile-form-static-db-extraction/01-acceptance-criteria.md`
- Prior release pattern: `features/forseti-jobhunter-profile-form-db-extraction/` (release-j — same module, same extraction pattern)

## Test coverage required
- Static: grep/lint checks covering AC-1, AC-2, AC-3
- Functional: site audit + endpoint check for AC-4
- Regression: confirm prior release-j ACs still hold (no `$this->database` reintroduced, repository still present)

## Output
Write: `features/forseti-jobhunter-profile-form-static-db-extraction/03-test-plan.md`
Then write outbox: `sessions/qa-forseti/outbox/20260408-232608-testplan-forseti-jobhunter-profile-form-static-db-extraction.md`

## Done when
`features/forseti-jobhunter-profile-form-static-db-extraction/03-test-plan.md` exists with static/functional/regression suites and pass criteria.
