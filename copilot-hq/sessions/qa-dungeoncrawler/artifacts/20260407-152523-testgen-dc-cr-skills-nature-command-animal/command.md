# Test Plan Design: dc-cr-skills-nature-command-animal

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-nature-command-animal/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-nature-command-animal "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-nature-command-animal

## Gap analysis reference
- DB sections: core/ch04/Nature (Wis) (REQs 1699–1703)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Recall Knowledge (Nature)
- [ ] `[NEW]` Nature covers: animals, beasts, fey, plants, fungi, weather, terrain, natural environments, creatures from nature categories.
- [ ] `[NEW]` Untrained use permitted for Recall Knowledge.

### Command an Animal [1 action, Auditory, Trained]
- [ ] `[NEW]` Command an Animal costs 1 action, requires Trained Nature.
- [ ] `[NEW]` Target must be an animal (not magical beast, not undead, not mindless).
- [ ] `[NEW]` Commands must be simple and within an animal's capabilities.
- [ ] `[NEW]` DC = Will save of the target animal; untrained animals default to passive behavior.
- [ ] `[NEW]` Degrees: Crit Success = two actions this turn + cooperative for 1 minute; Success = one action this round; Failure = no action; Crit Failure = animal panics (runs/attacks).
- [ ] `[NEW]` Trained/bonded animals (from Handle Animal or companion rules) use a lower DC.

---

## Edge Cases
- [ ] `[NEW]` Command a non-animal creature (beast, mindless): blocked.
- [ ] `[NEW]` Command requires auditory (mute or deaf characters may have penalty/blocked depending on implementation).

## Failure Modes
- [ ] `[TEST-ONLY]` Crit Fail: animal panics; it does not simply comply.
- [ ] `[TEST-ONLY]` Command an Animal untrained: blocked.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter handlers
- Agent: qa-dungeoncrawler
- Status: pending
