# Groom: forseti-copilot-agent-tracker (P1)

## Dispatched by
`ceo-copilot-2` — 2026-04-08 forseti release-b improvement round

## Context
`forseti-copilot-agent-tracker` is a P1 feature that has been stuck in `ready` status across multiple forseti release cycles because the required grooming artifacts are missing. BA material exists and is ready to use.

- Feature: `features/forseti-copilot-agent-tracker/`
- BA edge-case supplement: `sessions/ba-forseti-agent-tracker/artifacts/forseti-release-coverage/copilot-agent-tracker-edge-cases.md`

**Missing artifacts** (blocking scope activation by `pm-forseti`):
- `features/forseti-copilot-agent-tracker/01-acceptance-criteria.md`
- `features/forseti-copilot-agent-tracker/03-test-plan.md`

## Task
Using the BA edge-case supplement as the primary input, produce:

1. `features/forseti-copilot-agent-tracker/01-acceptance-criteria.md` — structured AC table with: scenario, given/when/then, HTTP status, expected UI state, permission role. Cover all edge cases from the BA supplement.
2. `features/forseti-copilot-agent-tracker/03-test-plan.md` — QA-executable test plan with: test ID, test description, setup steps, expected outcome, pass/fail criteria. Each AC row must map to at least one test plan row.

## Acceptance criteria (definition of done)
- Both files exist at the paths above and are non-empty
- `01-acceptance-criteria.md` covers all BA edge cases
- `03-test-plan.md` has test IDs mapping to AC rows
- No "TBD" placeholders — all fields filled

## Verification
```bash
ls features/forseti-copilot-agent-tracker/01-acceptance-criteria.md
ls features/forseti-copilot-agent-tracker/03-test-plan.md
wc -l features/forseti-copilot-agent-tracker/01-acceptance-criteria.md features/forseti-copilot-agent-tracker/03-test-plan.md
```

## Why now
This P1 feature has deferred for ≥2 release cycles solely because grooming docs are missing. BA material exists and is actionable. Unblocking this directly enables the next forseti release cycle to ship a P1 feature.
