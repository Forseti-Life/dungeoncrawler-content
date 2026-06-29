- Status: done
- Completed: 2026-04-12T22:14:12Z

# Test Plan Design: dc-cr-goblin-ancestry

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-goblin-ancestry/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-goblin-ancestry "<brief summary>"
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

# Acceptance Criteria: dc-cr-goblin-ancestry

## Gap analysis reference
- DB sections: core/ch02 (Goblin ancestry, goblin heritages, goblin ancestry feats)
- Depends on: dc-cr-ancestry-system

---

## Happy Path

### Character creation availability
- [ ] `[NEW]` Goblin appears as a selectable ancestry in character creation.

### Core ancestry stats
- [ ] `[NEW]` Goblin ancestry grants 6 HP, Small size, and 25-foot Speed.
- [ ] `[NEW]` Goblin ancestry grants Dexterity and Charisma boosts plus one free boost.
- [ ] `[NEW]` Goblin ancestry applies Wisdom as the ancestry flaw.

### Ancestry integration
- [ ] `[NEW]` Goblin ancestry links to goblin heritages and the goblin ancestry feat tree.
- [ ] `[NEW]` Goblin ancestry data persists on the character record and is visible in the character sheet.

---

## Edge Cases
- [ ] `[NEW]` The free boost cannot duplicate an ancestry-assigned fixed boost if the character builder enforces PF2e boost restrictions.
- [ ] `[NEW]` Existing non-goblin characters cannot gain goblin ancestry-only feats without ancestry reassignment.

## Failure Modes
- [ ] `[TEST-ONLY]` Character creation rejects invalid ancestry stat payloads for goblin characters.
- [ ] `[TEST-ONLY]` Goblin ancestry cannot be applied to a character without re-running ancestry-dependent recalculation.

## Security acceptance criteria
- Security AC exemption: ancestry data modeling only; no new route surface beyond existing character flows
- Agent: qa-dungeoncrawler
- Status: pending
