# Test Plan Design: dc-cr-skills-acrobatics-actions

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:18:50+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-acrobatics-actions/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-acrobatics-actions "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-acrobatics-actions

## Gap analysis reference
- DB sections: core/ch04/Acrobatics (Dex) (REQs 1602–1614)
- Depends on: dc-cr-skill-system ✓, dc-cr-skills-calculator-hardening (planned)

---

## Happy Path

### Escape (Acrobatics alternative)
- [ ] `[EXTEND]` Escape action accepts Acrobatics modifier as an alternative to unarmed attack modifier; player selects which to use.

### Balance [1 action, Move]
- [ ] `[NEW]` Balance is a 1-action move action; character is flat-footed during the action.
- [ ] `[NEW]` Balance degrees of success: Critical Success = move at full Speed; Success = move at full Speed (counts as difficult terrain for tracking purposes); Failure = movement stops or character falls prone; Critical Failure = character falls prone and turn ends.
- [ ] `[NEW]` Sample DCs applied: Untrained (roots/cobblestones), Trained (wooden beam), Expert (gravel), Master (tightrope), Legendary (razor edge).
- [ ] `[NEW]` Balance requires a surface to balance on; attempting in midair without flight is blocked.

### Tumble Through [1 action, Move]
- [ ] `[NEW]` Tumble Through allows movement through an occupied enemy space (must enter their space to trigger the check).
- [ ] `[NEW]` Can substitute Climb/Fly/Swim for Stride in appropriate environments (e.g., underwater, aerial combat).
- [ ] `[NEW]` Success = character passes through (space counts as difficult terrain); Failure = movement stops and enemy reactions trigger.

### Maneuver in Flight [1 action, Move, Trained]
- [ ] `[NEW]` Maneuver in Flight requires a fly Speed AND Trained Acrobatics; blocked otherwise.
- [ ] `[NEW]` Sample DCs applied: Trained (steep ascent/descent), Expert (against wind or hover), Master (reverse direction), Legendary (gale force winds).
- [ ] `[NEW]` Failure on Maneuver in Flight triggers a Reflex save or the character falls.

### Squeeze [Exploration, Trained]
- [ ] `[NEW]` Squeeze is an exploration activity; requires Trained Acrobatics; speed through tight space = 1 min/5 ft (Critical Success: 1 min/10 ft).
- [ ] `[NEW]` Critical Failure: character becomes stuck; they can escape with a follow-up Squeeze check (any non-critical-failure result frees them).
- [ ] `[NEW]` Sample DCs applied: Trained (barely fits shoulders), Master (barely fits head).

---

## Edge Cases

- [ ] `[NEW]` Balance check not called when character has sure footing (e.g., on normal flat ground).
- [ ] `[NEW]` Tumble Through vs immovable/incorporeal enemies: GM determines applicability; system presents check.
- [ ] `[NEW]` Maneuver in Flight requires active fly speed (not just jump or levitate).

---

## Failure Modes

- [ ] `[TEST-ONLY]` Maneuver in Flight blocked for characters without fly speed or untrained Acrobatics.
- [ ] `[TEST-ONLY]` Squeeze blocked for characters with untrained Acrobatics.
- [ ] `[TEST-ONLY]` Balance Critical Failure: both prone AND turn-ending trigger correctly.
- [ ] `[TEST-ONLY]` Stuck character from Squeeze critical failure: movement through that space blocked until freed.

---

## Security acceptance criteria

- Security AC exemption: skill action logic; no new routes or user-facing input beyond existing encounter/exploration phase handlers
- Agent: qa-dungeoncrawler
- Status: pending
