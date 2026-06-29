# Test Plan Design: dc-cr-skills-crafting-actions

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-crafting-actions/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-crafting-actions "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-crafting-actions

## Gap analysis reference
- DB sections: core/ch04/Crafting (Int) (REQs 1644–1656)
- Depends on: dc-cr-skill-system ✓, dc-cr-equipment-system (in-progress Release B)

---

## Happy Path

### Repair [Exploration, Trained]
- [ ] `[NEW]` Repair requires a repair kit, Trained Crafting, and 10 minutes; heals item HP.
- [ ] `[NEW]` HP restoration scales with proficiency: Crit Success = 10 + 10/rank; Success = 5 + 5/rank.
- [ ] `[NEW]` Critical Failure deals 2d6 damage to the item (after Hardness).
- [ ] `[NEW]` Destroyed items cannot be Repaired.

### Craft [Downtime, Trained]
- [ ] `[NEW]` Craft is a downtime activity requiring Trained Crafting, a formula, appropriate tools/workshop, and ≥50% raw material cost paid upfront.
- [ ] `[NEW]` Item level cap = character level; level 9+ requires Master Crafting; level 16+ requires Legendary.
- [ ] `[NEW]` Minimum 4 days; additional days reduce remaining cost; can pause and resume.
- [ ] `[NEW]` Degrees: Crit Success = faster cost reduction (level+1 rate); Success = normal; Failure = full material salvage; Crit Failure = 10% material loss.
- [ ] `[NEW]` Consumables: up to 4 identical items per Craft check; ammunition in standard batch quantities.
- [ ] `[NEW]` Special feats required for alchemical items (Alchemical Crafting), magic items (Magical Crafting), snares (Snare Crafting).

### Identify Alchemy [Exploration, Trained]
- [ ] `[NEW]` Identify Alchemy requires Trained Crafting, alchemist's tools, and 10 uninterrupted minutes.
- [ ] `[NEW]` Critical Failure produces a false identification result.

---

## Edge Cases
- [ ] `[NEW]` Repair on destroyed item: blocked with error.
- [ ] `[NEW]` Craft item above character level: blocked.
- [ ] `[NEW]` Crafting magic items without Magical Crafting feat: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Repair on item at full HP: no-op or minimal effect per GM.
- [ ] `[TEST-ONLY]` Craft without formula: blocked.
- [ ] `[TEST-ONLY]` Identify Alchemy Crit Fail returns false data, not an error.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing downtime/exploration handlers
- Agent: qa-dungeoncrawler
- Status: pending
