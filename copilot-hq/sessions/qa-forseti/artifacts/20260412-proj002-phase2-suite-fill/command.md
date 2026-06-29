- Status: done
- Completed: 2026-04-12T20:53:16Z

# PROJ-002 Phase 2 — Fill Priority Suite test_cases

- Project: PROJ-002
- Product: forseti.life
- Owner: qa-forseti
- Delegated by: pm-forseti
- Priority: P2
- ROI: 22

## Context

Phase 1 (triage) is complete. The triage report at `sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md` identified:
- **52 fill** candidates (shipped features with empty suite shells)
- **18 retire** candidates (superseded, should be deleted from suite.json)
- **18 defer** (in_progress or Playwright-only — skip for now)

Current state of `qa-suites/products/forseti/suite.json`: **252 suites, 2 with test_cases** (15 test cases total). The suite count grew from 94 → 252 since triage due to new feature releases.

## Task

**Phase 2a (this dispatch):** Fill `test_cases` for the 27 CEO pre-classified `fill` candidates from the triage report. These are the highest-priority suites — all backed by shipped features with existing QA verification evidence in session outboxes.

### Priority fill targets (from triage report)

```
forseti-jobhunter-application-status-dashboard-static
forseti-jobhunter-application-status-dashboard-functional
forseti-jobhunter-application-status-dashboard-regression
forseti-jobhunter-google-jobs-ux-static
forseti-jobhunter-google-jobs-ux-functional
forseti-jobhunter-google-jobs-ux-regression
forseti-jobhunter-profile-completeness-static
forseti-jobhunter-profile-completeness-functional
forseti-jobhunter-profile-completeness-regression
forseti-jobhunter-resume-tailoring-display-static
forseti-jobhunter-resume-tailoring-display-functional
forseti-jobhunter-resume-tailoring-display-regression
forseti-ai-conversation-user-chat-static
forseti-ai-conversation-user-chat-acl
forseti-ai-conversation-user-chat-csrf-post
forseti-ai-conversation-user-chat-regression
forseti-jobhunter-application-submission-route-acl
forseti-jobhunter-application-submission-unit
forseti-copilot-agent-tracker-route-acl
forseti-copilot-agent-tracker-api
forseti-copilot-agent-tracker-happy-path
forseti-copilot-agent-tracker-security
forseti-jobhunter-browser-automation-unit
forseti-jobhunter-controller-extraction-phase1-static
forseti-jobhunter-controller-extraction-phase1-regression
forseti-csrf-seed-consistency
role-url-audit
```

### Also execute: retire the 18 retire candidates

Remove these suite entries from `suite.json` (superseded/refactor-era):
```
forseti-jobhunter-controller-refactor-static
forseti-jobhunter-controller-refactor-unit
forseti-jobhunter-controller-refactor-phase2-unit-db-calls
forseti-jobhunter-controller-refactor-phase2-unit-service-methods
forseti-jobhunter-controller-refactor-phase2-unit-services-yml
forseti-ai-service-refactor-*  (3 suites)
forseti-ai-debug-gate-*  (3 suites)
[remaining retire candidates per triage report]
```

## Acceptance criteria

### AC-1: Fill test_cases
- For each of the 27 priority fill suites: at least 2 `test_cases` entries written in `suite.json`
- Each test_case: `id`, `description`, `type` (`static` / `functional` / `unit`), `command` (bash command that exits 0 on PASS), `status: active`
- Source: read the relevant feature's `03-test-plan.md` and/or the QA session outbox where the feature was verified — extract the bash commands already run during Gate 2 verification

### AC-2: Retire stale suites
- 18 retire-classified suite entries removed from `suite.json`
- No test_cases data lost (these suites have no test_cases — safe to remove)

### AC-3: Validate passes
```bash
cd /home/ubuntu/forseti.life/copilot-hq
python3 scripts/qa-suite-validate.py
# Expected: OK (may warn about missing commands for defer suites — acceptable)
```

### AC-4: Suite count improvement
- After Phase 2a: `suite.json` suites-with-test_cases count ≥ 25 (up from 2)
- Total test_cases ≥ 54 (≥2 per filled suite)

## Verification commands

```bash
cd /home/ubuntu/forseti.life/copilot-hq

# Check fill progress
python3 -c "
import json
with open('qa-suites/products/forseti/suite.json') as f:
    d=json.load(f)
suites=d['suites']
filled=[s for s in suites if s.get('test_cases')]
print(f'Total: {len(suites)}, filled: {len(filled)}, total_tc: {sum(len(s[\"test_cases\"]) for s in filled)}')
"

# Validate manifest
python3 scripts/qa-suite-validate.py
```

## Source evidence

For each suite, the QA verification commands were already run during the original feature release. Find them in:
- `sessions/qa-forseti/outbox/` — Gate 2 APPROVE outboxes contain the exact bash commands run
- `features/<feature-id>/03-test-plan.md` — test plan lists the verification approach

Do NOT re-run all tests from scratch — transcribe the commands from existing QA evidence. If a command needs minor updates (path changes, URL changes), update it.

## Out of scope (Phase 2b — separate dispatch)
- The additional 25 fill candidates identified in full triage (beyond the 27 CEO pre-classified)
- E2E Playwright auth pipeline (`forseti-qa-e2e-auth-pipeline` — requires env provisioning)
- Cross-user isolation test_cases
- Newly added suites (252 - 94 = 158 post-triage additions — Phase 2b)
