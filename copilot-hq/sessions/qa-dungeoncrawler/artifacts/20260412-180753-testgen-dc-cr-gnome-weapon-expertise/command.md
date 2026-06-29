- Status: done
- Completed: 2026-04-12T20:27:47Z

# Test Plan Design: dc-cr-gnome-weapon-expertise

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-gnome-weapon-expertise/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-gnome-weapon-expertise "<brief summary>"
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

# Acceptance Criteria: dc-cr-gnome-weapon-expertise

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-gnome-weapon-familiarity, dc-cr-ancestry-feat-schedule

---

## Happy Path

### Availability
- [ ] `[NEW]` Gnome Weapon Expertise is selectable as a Gnome feat 13 when Gnome Weapon Familiarity is present.

### Proficiency cascade
- [ ] `[NEW]` When a class feature grants expert or greater proficiency in a weapon or weapon group, the same rank is granted to glaive.
- [ ] `[NEW]` The same cascade applies to kukri.
- [ ] `[NEW]` The same cascade applies to trained gnome weapons.

---

## Edge Cases
- [ ] `[NEW]` Only gnome weapons the character is already trained in receive the cascade.
- [ ] `[NEW]` The feat responds to later class-granted proficiency upgrades instead of snapshotting only at selection time.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without Gnome Weapon Familiarity cannot select or benefit from the feat.
- [ ] `[TEST-ONLY]` Non-class proficiency changes do not incorrectly trigger the cascade.

## Security acceptance criteria
- Security AC exemption: passive proficiency event handling only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
