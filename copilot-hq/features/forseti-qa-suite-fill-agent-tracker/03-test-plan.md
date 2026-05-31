# Test Plan: forseti-qa-suite-fill-agent-tracker

- Feature: forseti-qa-suite-fill-agent-tracker
- Module: qa_suites
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify that the 4 new suite entries exist in `qa-suites/products/forseti-agent-tracker/suite.json`, that `qa-suite-validate.py` passes, and that each suite command exits 0 when run against a live Drupal instance.

## Test cases

### TC-1: Manifest schema valid
- Steps: `python3 scripts/qa-suite-validate.py`
- Expected: exits 0 with "OK: validated N suite manifest(s)"

### TC-2: All 4 suite IDs present in manifest
- Steps:
  ```
  python3 -c "
  import json
  d=json.load(open('qa-suites/products/forseti-agent-tracker/suite.json'))
  ids=[s['id'] for s in d['suites']]
  required=['forseti-copilot-agent-tracker-route-acl','forseti-copilot-agent-tracker-api','forseti-copilot-agent-tracker-happy-path','forseti-copilot-agent-tracker-security']
  missing=[x for x in required if x not in ids]
  assert not missing, f'Missing suites: {missing}'
  print('PASS')
  "
  ```
- Expected: prints "PASS"; exits 0

### TC-3: All 4 new suites have required_for_release: true
- Steps: `python3 -c "import json; d=json.load(open('qa-suites/products/forseti-agent-tracker/suite.json')); ids=['forseti-copilot-agent-tracker-route-acl','forseti-copilot-agent-tracker-api','forseti-copilot-agent-tracker-happy-path','forseti-copilot-agent-tracker-security']; suites={s['id']:s for s in d['suites']}; assert all(suites[i]['required_for_release'] for i in ids), 'not required_for_release'; print('PASS')"`
- Expected: "PASS"; exits 0

### TC-4: route-acl suite command exits 0 (live)
- Prereq: `FORSETI_BASE_URL` set; Drupal running
- Steps: Run the command in the `forseti-copilot-agent-tracker-route-acl` suite entry
- Expected: exits 0

### TC-5: api suite command exits 0 (live)
- Prereq: `FORSETI_BASE_URL` and `TELEMETRY_TOKEN` set; Drupal running
- Steps: Run the command in the `forseti-copilot-agent-tracker-api` suite entry
- Expected: exits 0

### TC-6: happy-path suite command exits 0 (live)
- Prereq: `FORSETI_BASE_URL`, `TELEMETRY_TOKEN`, and `FORSETI_COOKIE_ADMIN` set
- Steps: Run the command in the `forseti-copilot-agent-tracker-happy-path` suite entry
- Expected: exits 0

### TC-7: security suite command exits 0 (live)
- Prereq: `FORSETI_BASE_URL`, `TELEMETRY_TOKEN`, `FORSETI_COOKIE_ADMIN`, and `FORSETI_COOKIE_AUTHENTICATED` set
- Steps: Run the command in the `forseti-copilot-agent-tracker-security` suite entry
- Expected: exits 0; no PII leak detected

### TC-8: Security AC traceability
- Steps: Confirm AC-SEC-01 through AC-SEC-04 in `01-acceptance-criteria.md` each cite one of the 4 required site.instructions.md subsections
- Expected: Authentication/permission surface → AC-SEC-01; CSRF expectations → AC-SEC-02; Input validation requirements → AC-SEC-03; PII/logging constraints → AC-SEC-04

## Regression notes
- `tracker-copilot-agent-tracker` (existing 24-test suite) must continue to pass
- `python3 scripts/qa-suite-validate.py` must still exit 0 after adding the 4 new entries
