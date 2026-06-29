# BA Grooming: forseti-qa-e2e-auth-pipeline

- Project: PROJ-002 Phase 1
- Feature: forseti-qa-e2e-auth-pipeline
- Dispatched by: pm-forseti
- Date: 2026-04-09
- ROI: 35

## Task

Expand `features/forseti-qa-e2e-auth-pipeline/01-acceptance-criteria.md` from stub to full AC.

## Context

The Playwright E2E suite (`jobhunter-e2e`) is blocked because `FORSETI_COOKIE_AUTHENTICATED` is not provisioned. The fix is to run `drush user:login --name=qa_tester_authenticated` (or equivalent) and extract the session cookie into the environment variable. A second QA user (`qa_tester_authenticated_2`) is needed for cross-user isolation tests.

This is a release-h feature (lower priority than fill features). Acceptance criteria should be actionable but realistic about the Drupal/Playwright environment.

## ACs to write

1. **Auth provisioning script AC**: script exists, is executable, produces `FORSETI_COOKIE_AUTHENTICATED` env var
2. **Playwright auth AC**: `jobhunter-e2e` Playwright suite runs with the cookie set and at least 1 authenticated test passes
3. **Second QA user AC**: `qa_tester_authenticated_2` user exists in Drupal (idempotent creation)
4. **Cross-user isolation AC**: at least 1 test case verifies user A cannot see user B's job records
5. **Validation AC**: `python3 scripts/qa-suite-validate.py` passes

## Drupal context

- Drupal site root: `/home/ubuntu/forseti.life/sites/forseti`
- Drush path: `vendor/bin/drush`
- drush user:login produces a one-time login URL; session cookie extraction may require a curl follow

## Definition of done

- [ ] `01-acceptance-criteria.md` expanded with 5 concrete ACs
- [ ] Script path specified (e.g., `scripts/qa-playwright-auth.sh`)
- [ ] Cross-user isolation test pattern documented
- [ ] No stub placeholders remain
- [ ] Committed to HQ repo
- Agent: ba-forseti
- Status: pending
