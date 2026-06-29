# Test Plan Design: dc-cr-skills-calculator-hardening

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:28:30+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-calculator-hardening/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-calculator-hardening "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-calculator-hardening

## Gap analysis reference
- DB sections: core/ch04 General Skill Actions (REQs 1554–1568, 1600, 2323) — enforcement gaps
- Depends on: dc-cr-skill-system ✓, dc-cr-character-leveling

---

## Happy Path

### Trained-Only Action Gating
- [ ] `[EXTEND]` `calculateSkillCheck()` returns an error/blocked result when an untrained character attempts a trained-only action.
- [ ] `[EXTEND]` Error message clearly states the character is untrained in the required skill.

### Proficiency Rank Ceiling Enforcement
- [ ] `[EXTEND]` `submitSkillIncrease()` enforces level ≥ 7 before allowing Expert → Master increase.
- [ ] `[EXTEND]` `submitSkillIncrease()` enforces level ≥ 15 before allowing Master → Legendary increase.
- [ ] `[EXTEND]` Blocked increase returns clear error, does not silently no-op.

### Armor Check Penalty
- [ ] `[EXTEND]` `calculateSkillCheck()` applies the character's armor check penalty to Strength- and Dexterity-based skill rolls.
- [ ] `[EXTEND]` Armor check penalty is NOT applied to attack-trait actions (e.g., Grapple, Trip, Disarm when used as attack actions).
- [ ] `[EXTEND]` Zero armor check penalty for unarmored characters = no penalty applied.

---

## Edge Cases
- [ ] `[EXTEND]` Expert → Master below level 7: blocked with clear level-requirement error.
- [ ] `[EXTEND]` Master → Legendary below level 15: blocked with clear level-requirement error.

## Failure Modes
- [ ] `[TEST-ONLY]` Untrained character attempting trained-only action: blocked (not silently degraded).
- [ ] `[TEST-ONLY]` Armor check penalty on Dex-based attack trait action: NOT applied.
- [ ] `[TEST-ONLY]` Rank ceiling bypass via direct API call: still enforced server-side.

## Security acceptance criteria
- Security AC exemption: internal calculator service hardening; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
