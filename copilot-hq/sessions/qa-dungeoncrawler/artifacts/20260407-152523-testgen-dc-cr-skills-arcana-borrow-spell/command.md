# Test Plan Design: dc-cr-skills-arcana-borrow-spell

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-arcana-borrow-spell/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-arcana-borrow-spell "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-arcana-borrow-spell

## Gap analysis reference
- DB sections: core/ch04/Arcana (Int) (REQs 1615–1618)
- Depends on: dc-cr-skill-system ✓, dc-cr-spellcasting (deferred)

---

## Happy Path

- [ ] `[NEW]` Arcana covers arcane magic knowledge, arcane creature identification, and planar lore (Elemental, Astral, Shadow planes).
- [ ] `[NEW]` Untrained characters can use Arcana to Recall Knowledge about arcane topics.
- [ ] `[NEW]` Borrow an Arcane Spell is an exploration activity requiring Trained Arcana AND the character must be an arcane prepared spellcaster (spellbook user).
- [ ] `[NEW]` Success: borrowed spell can be prepared normally in an open slot. Failure: slot remains open and retry is blocked until next preparation cycle.

## Edge Cases
- [ ] `[NEW]` Borrow an Arcane Spell blocked for untrained characters and non-arcane-prepared spellcasters.

## Failure Modes
- [ ] `[TEST-ONLY]` Borrow Arcane Spell retry blocked until next prep on failure.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing exploration handlers
- Agent: qa-dungeoncrawler
- Status: pending
