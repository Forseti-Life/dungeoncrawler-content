# Test Plan Design: dc-cr-snares

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:43:04+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-snares/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-snares "<brief summary>"
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

# Acceptance Criteria: dc-cr-snares

## Gap analysis reference
- DB sections: core/ch11/Snares (7 reqs) — already covered in dc-cr-magic-ch11 and flipped to in_progress.
  This feature focuses on snares as a distinct ranger/crafting subsystem.
- Depends on: dc-cr-equipment-system ✓, dc-cr-skill-system ✓, dc-cr-class-ranger

---

## Happy Path

### Crafting Snares
- [ ] `[EXTEND]` Snares require the Snare Crafting feat and a snare kit.
- [ ] `[EXTEND]` Snares occupy one 5-ft square and cannot be relocated after placement.
- [ ] `[EXTEND]` Quick crafting: 1 minute at full price; discounted version: downtime Craft activity.
- [ ] `[EXTEND]` Crafting produces a functional snare in place; no inventory item stored.

### Detection and Disabling
- [ ] `[EXTEND]` Detection DC = creator's Crafting DC; Disable DC = same (Thievery skill).
- [ ] `[EXTEND]` Expert+ Crafter snares: only found by actively-searching creatures (passive observers fail automatically).
- [ ] `[EXTEND]` Detection/disable minimum proficiency gates: Trained/Expert/Master based on creator's Crafting proficiency.
- [ ] `[EXTEND]` Creator disarms their own snare: 1 Interact action while adjacent (no check required).

### Snare Types (initial catalog)
- [ ] `[NEW]` Alarm Snare: triggers an audible alarm on trigger condition (loud noise in 300-ft radius).
- [ ] `[NEW]` Hampering Snare: terrain becomes difficult or greater difficult terrain for triggering creature; persists for limited time.
- [ ] `[NEW]` Marking Snare: applies a visual marker to the triggering creature (useful for tracking).
- [ ] `[NEW]` Striking Snare: deals physical damage to triggering creature (damage scales with snare level).

---

## Edge Cases
- [ ] `[EXTEND]` Snare detection while actively searching: normal Perception check vs Detection DC.
- [ ] `[EXTEND]` Snare placement in occupied square: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Snare Crafting without Snare Crafting feat: blocked (not just penalized).
- [ ] `[TEST-ONLY]` Creator disarms own snare without Thievery check: 1 Interact action only.

## Security acceptance criteria
- Security AC exemption: game-mechanic snare crafting and placement logic; no new routes or user-facing input beyond existing exploration and encounter handlers
- Agent: qa-dungeoncrawler
- Status: pending
