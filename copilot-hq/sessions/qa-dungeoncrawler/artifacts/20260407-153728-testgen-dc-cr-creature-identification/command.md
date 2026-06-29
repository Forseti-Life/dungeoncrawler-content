# Test Plan Design: dc-cr-creature-identification

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:37:28+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-creature-identification/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-creature-identification "<brief summary>"
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

# Acceptance Criteria: dc-cr-creature-identification

## Gap analysis reference
- DB sections: core/ch10/Creature Identification (1 req)
- Depends on: dc-cr-skill-system ✓, dc-cr-recall-knowledge

---

## Happy Path
- [ ] `[NEW]` Recall Knowledge skill routing by creature trait:
  - Aberrations, constructs, humanoids, oozes → Arcana
  - Animals, beasts, fungi, plants → Nature
  - Celestials, fiends, monitors, undead → Religion
  - Dragons, elementals → Arcana or Nature
  - Fey → Nature or Occultism
  - Spirits, creatures of spiritual origin → Occultism
  - Any creature → appropriate Lore subcategory (GM-adjudicated)
- [ ] `[NEW]` Multiple applicable skills allowed; character may use any one they are trained in (or untrained for untrained Recall Knowledge).
- [ ] `[NEW]` DC resolution for creatures: level-based DC + rarity adjustment (see dc-cr-dc-rarity-spell-adjustment).
- [ ] `[NEW]` Degrees: Crit Success = all standard info + bonus fact; Success = standard creature info; Failure = no info; Crit Failure = false info presented as true.

## Edge Cases
- [ ] `[NEW]` Creature type not in mapping: defaults to GM Lore check.
- [ ] `[NEW]` Crit Fail: system presents false info (player does not see a "failed" indicator).

## Failure Modes
- [ ] `[TEST-ONLY]` Wrong skill used to identify: if untrained in correct skill but not wrong skill, still must use a valid skill.

## Security acceptance criteria
- Security AC exemption: game-mechanic skill routing; no new routes or user-facing input beyond existing encounter handlers
- Agent: qa-dungeoncrawler
- Status: pending
