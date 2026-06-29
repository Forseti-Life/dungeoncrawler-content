- Status: done
- Completed: 2026-04-12T19:52:40Z

# Test Plan Design: dc-cr-first-world-magic

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-first-world-magic/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-first-world-magic "<brief summary>"
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

# Acceptance Criteria: dc-cr-first-world-magic

## Gap analysis reference
- DB sections: core/ch02 (Gnome Ancestry Feats)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-spellcasting (innate spell subsystem), dc-cr-gnome-heritage-wellspring (tradition-override interaction)

---

## Happy Path

### Availability
- [ ] `[NEW]` First World Magic is a Gnome ancestry feat (level 1); selectable at character creation and at level 1 ancestry feat slots.

### Fixed Primal Cantrip
- [ ] `[NEW]` Player selects one primal cantrip from the primal spell list at feat acquisition time.
- [ ] `[NEW]` Selected cantrip is fixed — cannot be swapped after acquisition (unlike Fey-touched Heritage).
- [ ] `[NEW]` Cantrip is stored as a primal innate spell castable at will.
- [ ] `[NEW]` Cantrip is automatically heightened to ceil(character_level / 2).

### Wellspring Override
- [ ] `[NEW]` If character has the Wellspring Gnome heritage, the cantrip's tradition is overridden to `character.wellspring_tradition` at the moment the feat is applied.
- [ ] `[NEW]` Override is automatic; no player action required at feat selection.

---

## Edge Cases
- [ ] `[NEW]` First World Magic and Fey-touched Heritage can both be taken (different slots); each grants a separate at-will innate cantrip.
- [ ] `[NEW]` The two cantrips may be the same spell — system must allow duplicate cantrip registrations (each is a separate innate spell record).

## Failure Modes
- [ ] `[TEST-ONLY]` The cantrip is at will (not once/day or once/encounter); no use counter applies.
- [ ] `[TEST-ONLY]` The cantrip is fixed at acquisition — no in-play or per-day swap available (swap is only for Fey-touched Heritage).

## Security acceptance criteria
- Security AC exemption: game-mechanic ancestry feat; no new routes or user-facing input
- Agent: qa-dungeoncrawler
- Status: pending
