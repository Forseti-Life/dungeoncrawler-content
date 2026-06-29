# Test Plan Design: dc-cr-skills-medicine-actions

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-medicine-actions/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-medicine-actions "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-medicine-actions

## Gap analysis reference
- DB sections: core/ch04/Medicine (Wis) (REQs 1688–1698)
- Depends on: dc-cr-skill-system ✓, dc-cr-conditions

---

## Happy Path

### Administer First Aid [2 actions, Manipulation, Trained]
- [ ] `[NEW]` Administer First Aid costs 2 actions, requires Trained Medicine and healer's tools (or improvised at –2).
- [ ] `[NEW]` Two modes: Stabilize (removes dying condition) and Stop Bleeding (stops persistent bleeding damage).
- [ ] `[NEW]` Stabilize: Success = dying 0; Failure = character loses 1 dying level; Crit Fail = character advances 1 dying level.
- [ ] `[NEW]` Cannot Administer First Aid on the same condition in the same target twice per round.

### Treat Disease [Downtime, Trained]
- [ ] `[NEW]` Treat Disease is a downtime activity (8 hours rest period); requires healer's tools.
- [ ] `[NEW]` Makes the target's next disease save a success one degree better.
- [ ] `[NEW]` Can only be applied once per disease save per rest period.

### Treat Poison [1 action, Manipulation, Trained]
- [ ] `[NEW]` Treat Poison costs 1 action, requires Trained Medicine and healer's tools.
- [ ] `[NEW]` Makes the target's next poison save one degree better.
- [ ] `[NEW]` Can only treat one poison per save; applies to the first upcoming save only.

### Treat Wounds [Exploration, Manipulation, Trained]
- [ ] `[NEW]` Treat Wounds is an exploration activity (10 minutes); requires healer's tools and Trained Medicine.
- [ ] `[NEW]` DC 15 at Trained; DC 20 at Expert; DC 30 at Master; DC 40 at Legendary.
- [ ] `[NEW]` HP restored: Trained (2d8), Expert (2d8+10), Master (2d8+30), Legendary (2d8+50).
- [ ] `[NEW]` Crit Success restores double HP.
- [ ] `[NEW]` Crit Failure deals 1d8 damage instead.
- [ ] `[NEW]` Target cannot benefit from Treat Wounds again for 1 hour (tracked per target).

---

## Edge Cases
- [ ] `[NEW]` First Aid without healer's tools: improvised penalty (–2) applies or blocked if no tools available.
- [ ] `[NEW]` Treat Wounds on same target within 1-hour cooldown: blocked.
- [ ] `[NEW]` Stabilize on non-dying target: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` First Aid Crit Fail on Stabilize advances dying (not just fails).
- [ ] `[TEST-ONLY]` Treat Wounds Crit Fail deals damage, not just 0 healing.
- [ ] `[TEST-ONLY]` Treat Wounds 1-hour cooldown is per-target, not per-healer.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing exploration/encounter handlers
- Agent: qa-dungeoncrawler
- Status: pending
