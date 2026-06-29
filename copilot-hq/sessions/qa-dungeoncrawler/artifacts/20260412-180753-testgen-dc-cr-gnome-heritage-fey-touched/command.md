- Status: done
- Completed: 2026-04-12T20:04:01Z

# Test Plan Design: dc-cr-gnome-heritage-fey-touched

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-gnome-heritage-fey-touched/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-gnome-heritage-fey-touched "<brief summary>"
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

# Acceptance Criteria: dc-cr-gnome-heritage-fey-touched

## Gap analysis reference
- DB sections: core/ch02 (Gnome Heritages)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-spellcasting (innate spell subsystem), dc-cr-gnome-heritage-wellspring (tradition override path)

---

## Happy Path

### Traits
- [ ] `[NEW]` Character gains the fey trait in addition to Gnome and Humanoid.

### At-Will Primal Cantrip
- [ ] `[NEW]` Player selects one cantrip from the primal spell list at heritage selection time.
- [ ] `[NEW]` Cantrip is stored on the character record as a primal innate spell castable at will.
- [ ] `[NEW]` Cantrip is automatically heightened to a spell level equal to ceil(character_level / 2).

### Daily Cantrip Swap
- [ ] `[NEW]` Character can swap the selected cantrip once per day via a 10-minute activity tagged with the concentrate trait.
- [ ] `[NEW]` Replacement cantrip must be chosen from the primal spell list.
- [ ] `[NEW]` Swap resets at daily preparation (24-hour period / long rest).

### Wellspring Override Integration
- [ ] `[NEW]` If character also has Wellspring Gnome heritage (not normally stacked — but tradition-override flag applies if a multiclass/variant rule grants both), the cantrip tradition is overridden to the Wellspring tradition.

---

## Edge Cases
- [ ] `[NEW]` fey trait adds to character traits — does NOT replace Gnome or Humanoid.
- [ ] `[NEW]` Only one cantrip swap allowed per day; attempting a second swap in the same day is blocked with a system message.

## Failure Modes
- [ ] `[TEST-ONLY]` Cantrip is at-will (not once/day); the daily-swap is the limited action, not the casting itself.
- [ ] `[TEST-ONLY]` Heightening must be dynamic — if character level increases, the cantrip level re-calculates automatically.

## Security acceptance criteria
- Security AC exemption: game-mechanic heritage; no new routes or user-facing input beyond character creation
- Agent: qa-dungeoncrawler
- Status: pending
