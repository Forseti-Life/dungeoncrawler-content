# Acceptance Criteria: forseti-qa-suite-retire-stale

- Feature: forseti-qa-suite-retire-stale
- Module: qa_suites
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Remove all confirmed `retire` suite shells from `qa-suites/products/forseti/suite.json`. Execution requires the qa-forseti triage report at `sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md` as the authoritative gate. The triage report exists and has confirmed all 19 suites as `retire` (18 from feature.md scope + 1 additional `forseti-jobhunter-profile-refactor-static` discovered by qa-forseti audit). All 19 confirmed-retire suites have 0 `test_cases` entries.

## Triage report status (verified at AC authoring)

- Triage report path: `sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md`
- Report exists: ✅ YES
- All 18 feature.md pre-classified suites confirmed as `retire` in report: ✅ YES
- Additional suite confirmed `retire` by qa-forseti audit: `forseti-jobhunter-profile-refactor-static` (✅ retire)
- **Total confirmed retire count: 19**
- All 19 have `test_cases: []` (0 populated test cases): ✅ VERIFIED

## Confirmed retire list (19 suites)

From feature.md pre-classification, confirmed ✅ by triage report:

1. `forseti-jobhunter-controller-refactor-static`
2. `forseti-jobhunter-controller-refactor-unit`
3. `forseti-jobhunter-controller-refactor-phase2-unit-db-calls`
4. `forseti-jobhunter-controller-refactor-phase2-unit-service-methods`
5. `forseti-jobhunter-controller-refactor-phase2-unit-services-yml`
6. `forseti-jobhunter-controller-refactor-phase2-unit-lint-controller`
7. `forseti-jobhunter-controller-refactor-phase2-unit-lint-service`
8. `forseti-jobhunter-controller-refactor-phase2-unit-no-new-routes`
9. `forseti-jobhunter-controller-refactor-phase2-e2e-post-flows`
10. `forseti-ai-service-refactor-static`
11. `forseti-ai-service-refactor-functional`
12. `forseti-ai-service-refactor-unit`
13. `forseti-ai-debug-gate-route-acl`
14. `forseti-ai-debug-gate-static`
15. `forseti-ai-debug-gate-functional`
16. `forseti-jobhunter-profile-e2e`
17. `forseti-jobhunter-browser-automation-e2e`
18. `forseti-jobhunter-browser-automation-functional`

Additional, confirmed by qa-forseti audit:

19. `forseti-jobhunter-profile-refactor-static`

---

## Acceptance criteria

### AC-1: Triage gate — report consulted and confirms retire before any deletion
- **Precondition**: `sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md` must exist before qa-forseti begins suite.json edits.
- **Verify**: `test -f /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md && echo "GATE: PASS"`
- **If report missing**: STOP — do not delete any suites. Escalate to pm-forseti.
- **Reclassification rule**: If the triage report lists any of the 19 suites with disposition `fill` or `defer` instead of `retire`, do NOT remove that suite. Treat triage report as source of truth over feature.md.
- **PASS condition**: Report exists at expected path; all 19 suites listed with `retire` disposition (verified at AC authoring — no reclassifications found).

### AC-2: No suite with populated test_cases is removed
- **Precondition**: Before deleting any suite entry, confirm `test_cases` array is empty (`[]` or absent).
- **Verify per suite**: `python3 -c "import json; d=json.load(open('qa-suites/products/forseti/suite.json')); s=next((x for x in d['suites'] if x['id']=='<SUITE_ID>'), None); print(len(s.get('test_cases',[])))"` → `0`
- **If any targeted suite has test_cases > 0**: STOP — do not remove that suite; escalate to pm-forseti as scope exception.
- **PASS condition**: All 19 suites confirmed at 0 test_cases before deletion proceeds (verified at AC authoring — all 19 have `test_cases: []`).

### AC-3: All 19 confirmed retire suites removed from suite.json
- After qa-forseti completes the deletion, none of the 19 suite IDs appear in the `suites` array.
- **Verify each** (run for all 19 IDs):
  ```bash
  python3 -c "
  import json
  d=json.load(open('qa-suites/products/forseti/suite.json'))
  ids=set(s['id'] for s in d['suites'])
  retire=[
    'forseti-jobhunter-controller-refactor-static',
    'forseti-jobhunter-controller-refactor-unit',
    'forseti-jobhunter-controller-refactor-phase2-unit-db-calls',
    'forseti-jobhunter-controller-refactor-phase2-unit-service-methods',
    'forseti-jobhunter-controller-refactor-phase2-unit-services-yml',
    'forseti-jobhunter-controller-refactor-phase2-unit-lint-controller',
    'forseti-jobhunter-controller-refactor-phase2-unit-lint-service',
    'forseti-jobhunter-controller-refactor-phase2-unit-no-new-routes',
    'forseti-jobhunter-controller-refactor-phase2-e2e-post-flows',
    'forseti-ai-service-refactor-static',
    'forseti-ai-service-refactor-functional',
    'forseti-ai-service-refactor-unit',
    'forseti-ai-debug-gate-route-acl',
    'forseti-ai-debug-gate-static',
    'forseti-ai-debug-gate-functional',
    'forseti-jobhunter-profile-e2e',
    'forseti-jobhunter-browser-automation-e2e',
    'forseti-jobhunter-browser-automation-functional',
    'forseti-jobhunter-profile-refactor-static',
  ]
  still_present=[r for r in retire if r in ids]
  if still_present:
      print('FAIL: still present:', still_present)
      exit(1)
  print('PASS: all 19 retire suites removed')
  "
  ```
- **PASS condition**: Script exits 0 and prints "PASS: all 19 retire suites removed".

### AC-4: Suite count decreases by exactly 19
- **Before deletion baseline**: `jq '.suites | length' qa-suites/products/forseti/suite.json` = 102 (verified at AC authoring)
- **After deletion expected**: `jq '.suites | length' qa-suites/products/forseti/suite.json` = 83
- **Verify**:
  ```bash
  COUNT=$(python3 -c "import json; print(len(json.load(open('qa-suites/products/forseti/suite.json'))['suites']))")
  [ "$COUNT" -eq 83 ] && echo "PASS: count=$COUNT" || (echo "FAIL: expected 83, got $COUNT" && exit 1)
  ```
- **PASS condition**: Count is exactly 83 after deletion.

### AC-5: schema validation passes after all deletions
- **Verify**: `python3 scripts/qa-suite-validate.py`
- **PASS condition**: Exits 0 with no errors. No fill/defer suites were accidentally removed; no JSON syntax errors introduced.

### AC-6: Non-retire suites are unaffected
- Spot check: 3 suites adjacent to retire entries must still be present:
  - `forseti-jobhunter-controller-extraction-phase1-static` (fill — must remain)
  - `forseti-ai-service-db-refactor-static` (fill — must remain)
  - `forseti-jobhunter-browser-automation-unit` (fill — must remain; only the `-e2e` and `-functional` variants retire)
- **Verify**:
  ```bash
  python3 -c "
  import json
  d=json.load(open('qa-suites/products/forseti/suite.json'))
  ids=set(s['id'] for s in d['suites'])
  required=['forseti-jobhunter-controller-extraction-phase1-static','forseti-ai-service-db-refactor-static','forseti-jobhunter-browser-automation-unit']
  missing=[r for r in required if r not in ids]
  if missing: print('FAIL: missing suites', missing); exit(1)
  print('PASS: all required non-retire suites present')
  "
  ```
- **PASS condition**: Script exits 0; no adjacent fill suites removed.

### AC-7: Commit message lists all 19 removed suite IDs
- Git commit message must include the list of removed IDs (or reference this feature ID) so the deletion is auditable.
- **Verify**: `git --no-pager log --oneline -1 -- qa-suites/products/forseti/suite.json` shows a commit with message referencing "retire" or listing suite IDs.
- **PASS condition**: Commit exists; message is traceable to this feature.

---

## Definition of done

- [ ] AC-1: Triage report existence confirmed before deletion starts
- [ ] AC-2: All 19 targeted suites have 0 test_cases (verified before deletion)
- [ ] AC-3: All 19 retire suites absent from suite.json after commit
- [ ] AC-4: Suite count = 83 after deletion
- [ ] AC-5: `python3 scripts/qa-suite-validate.py` exits 0
- [ ] AC-6: 3 spot-check fill suites still present
- [ ] AC-7: Git commit with auditable message
- [ ] `feature.md` Status → ready
- [ ] `03-test-plan.md` created

## Verification commands

```bash
# Pre-deletion: confirm triage report
test -f /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md && echo "triage-gate: PASS"

# Pre-deletion: baseline count
python3 -c "import json; print('suite count:', len(json.load(open('qa-suites/products/forseti/suite.json'))['suites']))"

# Post-deletion: all 19 removed
python3 -c "
import json; d=json.load(open('qa-suites/products/forseti/suite.json')); ids=set(s['id'] for s in d['suites'])
retire=['forseti-jobhunter-controller-refactor-static','forseti-jobhunter-controller-refactor-unit','forseti-jobhunter-controller-refactor-phase2-unit-db-calls','forseti-jobhunter-controller-refactor-phase2-unit-service-methods','forseti-jobhunter-controller-refactor-phase2-unit-services-yml','forseti-jobhunter-controller-refactor-phase2-unit-lint-controller','forseti-jobhunter-controller-refactor-phase2-unit-lint-service','forseti-jobhunter-controller-refactor-phase2-unit-no-new-routes','forseti-jobhunter-controller-refactor-phase2-e2e-post-flows','forseti-ai-service-refactor-static','forseti-ai-service-refactor-functional','forseti-ai-service-refactor-unit','forseti-ai-debug-gate-route-acl','forseti-ai-debug-gate-static','forseti-ai-debug-gate-functional','forseti-jobhunter-profile-e2e','forseti-jobhunter-browser-automation-e2e','forseti-jobhunter-browser-automation-functional','forseti-jobhunter-profile-refactor-static']
still=[r for r in retire if r in ids]; print('FAIL:' if still else 'PASS:', still or 'all 19 retired')
"

# Post-deletion: count check
python3 -c "import json; c=len(json.load(open('qa-suites/products/forseti/suite.json'))['suites']); print('PASS' if c==83 else 'FAIL', f'count={c}')"

# Post-deletion: schema validation
python3 scripts/qa-suite-validate.py
```

## Notes for qa-forseti

- **Do not remove** `forseti-jobhunter-browser-automation-unit` — only `-e2e` and `-functional` are retired. The `-unit` variant is `fill`.
- **Execution order**: triage gate check → pre-deletion count → delete 19 entries → post-deletion count → validate → commit
- **`forseti-jobhunter-profile-refactor-static`** is an additional retire discovered by qa-forseti audit (not in feature.md original list but confirmed in triage report). Include in deletion.
