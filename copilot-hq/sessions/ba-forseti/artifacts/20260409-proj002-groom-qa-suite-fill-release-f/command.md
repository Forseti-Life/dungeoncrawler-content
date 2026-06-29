# BA Grooming: forseti-qa-suite-fill-release-f

- Project: PROJ-002 Phase 1
- Feature: forseti-qa-suite-fill-release-f
- Dispatched by: pm-forseti
- Date: 2026-04-09
- ROI: 45

## Task

Expand `features/forseti-qa-suite-fill-release-f/01-acceptance-criteria.md` from stub to full AC.

The stub has the correct structure. Your job: for each suite, write concrete, verifiable acceptance criteria that qa-forseti can translate directly into `test_cases` entries in `suite.json`.

## Suites to cover (16 total)

- forseti-jobhunter-application-status-dashboard-static / functional / regression
- forseti-jobhunter-google-jobs-ux-static / functional / regression
- forseti-jobhunter-profile-completeness-static / functional / regression
- forseti-jobhunter-resume-tailoring-display-static / functional / regression
- forseti-ai-conversation-user-chat-static / acl / csrf-post / regression

## AC format guidance

For each suite, each AC should specify:
- What is being checked (e.g., "route /jobhunter/my-jobs returns 200 for authenticated user")
- What tool runs it (bash curl, drush, phpunit, or grep/static check)
- What the PASS condition is (HTTP status code, exit code, string presence)

Reference: `features/forseti-jobhunter-application-status-dashboard/01-acceptance-criteria.md` for style.

For the static check suites, reference the shipped feature's `01-acceptance-criteria.md` to understand what was implemented.

## Shipped feature references

- `features/forseti-jobhunter-application-status-dashboard/`
- `features/forseti-jobhunter-google-jobs-ux/` (check if exists)
- `features/forseti-jobhunter-profile-completeness/` (check if exists)
- `features/forseti-jobhunter-resume-tailoring-display/` (check if exists)
- `features/forseti-ai-conversation-user-chat/`

## Definition of done

- [ ] `01-acceptance-criteria.md` expanded: at least 2 concrete ACs per suite (16 suites = 32+ ACs total)
- [ ] Each AC is testable via a single bash command or PHPUnit class
- [ ] No stub placeholders remain ("ba-forseti to complete" removed)
- [ ] Committed to HQ repo

## Constraints

- Do NOT modify `suite.json` — that is qa-forseti's job
- Do NOT scope-activate the feature — pm-forseti does that after testgen completes
- Agent: ba-forseti
- Status: pending
