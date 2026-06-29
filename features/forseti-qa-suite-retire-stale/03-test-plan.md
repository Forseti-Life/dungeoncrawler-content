# Test Plan: forseti-qa-suite-retire-stale

- Feature: forseti-qa-suite-retire-stale
- Module: qa_suites
- Author: ba-forseti (scaffold — qa-forseti to execute)
- Date: 2026-04-09

## Scope

Verify the 19 confirmed-retire suite entries are removed from `qa-suites/products/forseti/suite.json` without affecting any fill/defer suites. Execution is gated on the triage report.

## Prerequisites

- Triage report exists at `sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md`
- Running from HQ root: `/home/ubuntu/forseti.life/copilot-hq`
- `python3 scripts/qa-suite-validate.py` available

## Test cases

### TC-1: Triage gate — report exists
- Command: `test -f sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md && echo PASS`
- Expected: PASS
- On fail: STOP — do not proceed with any deletions

### TC-2: Pre-deletion baseline count = 102
- Command: `python3 -c "import json; c=len(json.load(open('qa-suites/products/forseti/suite.json'))['suites']); print('PASS' if c==102 else f'WARN baseline={c} (may differ if other work ran first)')"`
- Expected: PASS or acknowledged count

### TC-3: All 19 targeted suites have 0 test_cases before deletion
- Command: `python3 -c "import json; d=json.load(open('qa-suites/products/forseti/suite.json')); retire=['forseti-jobhunter-controller-refactor-static','forseti-jobhunter-controller-refactor-unit','forseti-jobhunter-controller-refactor-phase2-unit-db-calls','forseti-jobhunter-controller-refactor-phase2-unit-service-methods','forseti-jobhunter-controller-refactor-phase2-unit-services-yml','forseti-jobhunter-controller-refactor-phase2-unit-lint-controller','forseti-jobhunter-controller-refactor-phase2-unit-lint-service','forseti-jobhunter-controller-refactor-phase2-unit-no-new-routes','forseti-jobhunter-controller-refactor-phase2-e2e-post-flows','forseti-ai-service-refactor-static','forseti-ai-service-refactor-functional','forseti-ai-service-refactor-unit','forseti-ai-debug-gate-route-acl','forseti-ai-debug-gate-static','forseti-ai-debug-gate-functional','forseti-jobhunter-profile-e2e','forseti-jobhunter-browser-automation-e2e','forseti-jobhunter-browser-automation-functional','forseti-jobhunter-profile-refactor-static']; m={s['id']:s for s in d['suites']}; bad=[r for r in retire if len(m.get(r,{}).get('test_cases',[]))>0]; print('FAIL:',bad) if bad else print('PASS: all 0 test_cases')"`
- Expected: PASS: all 0 test_cases

### TC-4: All 19 retire suites absent post-deletion (AC-3)
- Command: (same Python snippet from AC-3 verification section)
- Expected: PASS: all 19 retired

### TC-5: Suite count = 83 post-deletion (AC-4)
- Command: `python3 -c "import json; c=len(json.load(open('qa-suites/products/forseti/suite.json'))['suites']); print('PASS' if c==83 else f'FAIL: expected 83, got {c}')"`
- Expected: PASS

### TC-6: schema validation passes (AC-5)
- Command: `python3 scripts/qa-suite-validate.py`
- Expected: exit 0

### TC-7: Fill suite adjacents still present (AC-6)
- Command: `python3 -c "import json; d=json.load(open('qa-suites/products/forseti/suite.json')); ids=set(s['id'] for s in d['suites']); required=['forseti-jobhunter-controller-extraction-phase1-static','forseti-ai-service-db-refactor-static','forseti-jobhunter-browser-automation-unit']; missing=[r for r in required if r not in ids]; print('FAIL:',missing) if missing else print('PASS')"`
- Expected: PASS

### TC-8: browser-automation-unit NOT removed (only -e2e and -functional retire)
- Command: `python3 -c "import json; d=json.load(open('qa-suites/products/forseti/suite.json')); ids=set(s['id'] for s in d['suites']); print('PASS' if 'forseti-jobhunter-browser-automation-unit' in ids else 'FAIL: unit variant was removed')"`
- Expected: PASS

### TC-9: Git commit exists with auditable message
- Command: `git --no-pager log --oneline -1 -- qa-suites/products/forseti/suite.json`
- Expected: commit hash + message referencing retire/deletion

## Execution order

1. TC-1 (gate check) → if FAIL: stop
2. TC-2 (baseline count) → log result
3. TC-3 (pre-deletion empty check) → if FAIL: stop and escalate
4. **Execute deletion** (qa-forseti edits suite.json)
5. TC-4 → TC-5 → TC-6 → TC-7 → TC-8 → TC-9

## Caution notes
- Do NOT remove `forseti-jobhunter-browser-automation-unit` — only the `-e2e` and `-functional` variants retire
- `forseti-jobhunter-profile-refactor-static` was discovered by qa-forseti audit (not in original feature.md list) but IS confirmed `retire` in the triage report; include in deletion
