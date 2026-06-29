- Status: done
- Completed: 2026-04-12T20:12:25Z

# Test Plan Design: dc-cr-gnome-heritage-wellspring

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-gnome-heritage-wellspring/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-gnome-heritage-wellspring "<brief summary>"
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

# Acceptance Criteria: dc-cr-gnome-heritage-wellspring

## Gap analysis reference
- DB sections: core/ch02 (Gnome Heritages)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-spellcasting (innate spell subsystem), dc-cr-first-world-magic (tradition-override interaction)

---

## Happy Path

### Tradition Selection
- [ ] `[NEW]` At heritage selection, player chooses one non-primal magical tradition: arcane, divine, or occult.
- [ ] `[NEW]` Chosen tradition is stored in `character.wellspring_tradition` (persistent character field).

### At-Will Cantrip from Chosen Tradition
- [ ] `[NEW]` Player selects one cantrip from the chosen tradition's spell list at heritage selection.
- [ ] `[NEW]` Cantrip is stored as an at-will innate spell using the chosen tradition (not primal).
- [ ] `[NEW]` Cantrip is automatically heightened to ceil(character_level / 2).

### Tradition Override for Gnome Ancestry Feats
- [ ] `[NEW]` Any primal innate spell gained from a gnome ancestry feat (e.g., First World Magic) is overridden to the `wellspring_tradition` at the time the feat is applied.
- [ ] `[NEW]` The override is automatic — no player action required at feat selection.
- [ ] `[NEW]` Override applies to future feats as well (all gnome ancestry feats with innate primal spells).

---

## Edge Cases
- [ ] `[NEW]` Primal tradition choice is not available; player must choose arcane, divine, or occult.
- [ ] `[NEW]` If `wellspring_tradition` changes (not normally possible without a character rebuild), all gnome-ancestry innate spells re-override to the new value.

## Failure Modes
- [ ] `[TEST-ONLY]` Cantrip is at-will (not once/day); tradition override is on the spell's tradition tag, not the cantrip casting frequency.
- [ ] `[TEST-ONLY]` Override applies to gnome ancestry feat innate spells ONLY — not to class spells or non-gnome-ancestry innate spells.

## Security acceptance criteria
- Security AC exemption: game-mechanic heritage; no new routes or user-facing input beyond character creation
- Agent: qa-dungeoncrawler
- Status: pending
