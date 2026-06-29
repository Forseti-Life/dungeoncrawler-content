# BA Grooming: forseti-qa-suite-retire-stale

- Project: PROJ-002 Phase 1
- Feature: forseti-qa-suite-retire-stale
- Dispatched by: pm-forseti
- Date: 2026-04-09
- ROI: 40

## Task

Expand `features/forseti-qa-suite-retire-stale/01-acceptance-criteria.md` from stub to full AC.

## Context

This feature retires 18 pre-classified `retire` suite shells from `suite.json`. **Execution must wait for qa-forseti triage report** (`sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md`). The triage report is the authoritative confirmation of which suites are retired vs. fill vs. defer.

## ACs to write

1. **Triage gate AC**: triage report must exist and confirm each suite as `retire` before deletion
2. **Deletion AC**: each confirmed `retire` suite is removed from `suite.json` suites array
3. **Integrity AC**: no suite with populated `test_cases` is removed
4. **Validation AC**: `python3 scripts/qa-suite-validate.py` passes after all deletions
5. **Count AC**: total suite count decreases by exactly N (where N = number of confirmed retires)

## Definition of done

- [ ] `01-acceptance-criteria.md` expanded with 5+ concrete ACs
- [ ] Triage gate AC explicitly requires triage report confirmation
- [ ] Specific verification commands listed
- [ ] No stub placeholders remain
- [ ] Committed to HQ repo
- Agent: ba-forseti
- Status: pending
