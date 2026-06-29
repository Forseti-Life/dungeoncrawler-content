- Status: done
- Completed: 2026-04-12T22:36:34Z

# Test Plan Design: dc-cr-halfling-ancestry

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-halfling-ancestry/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-halfling-ancestry "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

# Acceptance Criteria: dc-cr-halfling-ancestry

## Gap analysis reference
- DB sections: core/ch02 (Halfling ancestry, halfling heritages, halfling ancestry feats)
- Depends on: dc-cr-ancestry-system, dc-cr-halfling-keen-eyes

---

## Happy Path

### Character creation availability
- [ ] `[NEW]` Halfling appears as a selectable ancestry in character creation.

### Core ancestry stats
- [ ] `[NEW]` Halfling ancestry grants 6 HP, Small size, and 25-foot Speed.
- [ ] `[NEW]` Halfling ancestry grants Dexterity and Wisdom boosts plus the free-boost path supported by the build rules.

### Automatic ancestry traits
- [ ] `[NEW]` Halfling characters gain Halfling Luck.
- [ ] `[NEW]` Halfling characters gain Keen Eyes automatically.

### Ancestry integration
- [ ] `[NEW]` Halfling heritages and ancestry feats become available when Halfling is selected.
- [ ] `[NEW]` Halfling ancestry persists on the character sheet and downstream ancestry logic.

---

## Edge Cases
- [ ] `[NEW]` Keen Eyes is granted automatically and is not presented as an optional feat choice.
- [ ] `[NEW]` Switching away from Halfling ancestry removes halfling-only passive ancestry benefits on recalculation.

## Failure Modes
- [ ] `[TEST-ONLY]` Invalid ancestry-stat submissions are rejected and replaced with canonical halfling values.
- [ ] `[TEST-ONLY]` Halfling-only heritages and feats are blocked for non-halfling characters.

## Security acceptance criteria
- Security AC exemption: ancestry data modeling only; no new route surface beyond existing character flows
- Agent: qa-dungeoncrawler
- Status: pending
