- Status: done
- Completed: 2026-04-12T20:18:50Z

# Test Plan Design: dc-cr-gnome-obsession

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T18:07:53+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-gnome-obsession/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-gnome-obsession "<brief summary>"
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

# Acceptance Criteria: dc-cr-gnome-obsession

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-character-leveling, dc-cr-skill-system

---

## Happy Path

### Availability
- [ ] `[NEW]` Gnome Obsession is selectable as a Gnome feat 1.

### Lore selection and scaling
- [ ] `[NEW]` On feat selection, the character chooses one Lore skill and becomes trained in it.
- [ ] `[NEW]` At level 2, the chosen Lore upgrades to expert, and the background Lore (if any) also upgrades to expert.
- [ ] `[NEW]` At level 7, the tracked Lore skills upgrade to master.
- [ ] `[NEW]` At level 15, the tracked Lore skills upgrade to legendary.

---

## Edge Cases
- [ ] `[NEW]` If the character has no background Lore, only the chosen Lore is auto-upgraded.
- [ ] `[NEW]` Manual proficiency edits do not break the milestone auto-upgrade path.

## Failure Modes
- [ ] `[TEST-ONLY]` Non-Lore skills cannot be chosen for the feat.
- [ ] `[TEST-ONLY]` The auto-upgrades do not fire at levels other than 2, 7, and 15.

## Security acceptance criteria
- Security AC exemption: skill-progression data only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
