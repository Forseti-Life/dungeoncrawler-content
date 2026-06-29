# Test Plan Design: dc-cr-feats-ch05

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:29:33+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-feats-ch05/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-feats-ch05 "<brief summary>"
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

# Acceptance Criteria: dc-cr-feats-ch05

## Gap analysis reference
- DB sections: core/ch05/Chapter Overview (5 reqs), core/ch05/Key Feat Mechanic Notes (17 reqs), core/ch05/Non-Skill General Feats Table (2 reqs)
- Depends on: dc-cr-general-feats, dc-cr-skill-feats, dc-cr-character-leveling

---

## Happy Path

### Feat Category System
- [ ] `[NEW]` System supports a general feat category accessible to all characters regardless of class or ancestry.
- [ ] `[NEW]` General feat slots granted at levels 3, 7, 11, 15, 19.
- [ ] `[NEW]` Skill feats are a subcategory of general feats; granted at level 2 and every 2 levels thereafter.
- [ ] `[NEW]` Skill feat slot requires a feat with the `skill` trait; non-skill general feats cannot fill skill feat slots.
- [ ] `[NEW]` Feat level = minimum character level at which a character could meet the feat's proficiency prerequisite.

### Repeatable Feats
- [ ] `[NEW]` Repeatable feats (Armor Proficiency, Weapon Proficiency) track progression; subsequent selections must respect prior grants.
- [ ] `[NEW]` Each non-skill general feat is implementable as a character option at its listed level.

### Assurance [Fortune Feat]
- [ ] `[NEW]` Assurance produces a fixed result = 10 + proficiency bonus; no other bonuses, penalties, or modifiers apply.
- [ ] `[NEW]` Can be selected once per skill; each selection (per skill) is tracked independently.

### Recognize Spell [Reaction]
- [ ] `[NEW]` Recognize Spell is a reaction with no action cost; requires awareness of casting.
- [ ] `[NEW]` Auto-identify thresholds (common spells only): Trained ≤ level 2, Expert ≤ level 4, Master ≤ level 6, Legendary ≤ level 10.
- [ ] `[NEW]` Crit Success: +1 circumstance bonus to save or AC vs that spell.
- [ ] `[NEW]` Crit Failure: false identification (player sees wrong spell name/effect).

### Trick Magic Item [1 action, Manipulate]
- [ ] `[NEW]` Trick Magic Item requires Trained in the appropriate tradition skill and knowledge of the item's function.
- [ ] `[NEW]` Spell attack/DC falls back to level-based proficiency + highest mental ability score.
- [ ] `[NEW]` Crit Failure: locked out of using the item until next daily preparations.

### Battle Medicine [1 action, Manipulate]
- [ ] `[NEW]` Battle Medicine requires healer's tools and Trained Medicine; uses same DC/HP table as Treat Wounds.
- [ ] `[NEW]` Does NOT remove the wounded condition (distinct from Treat Wounds which may clear dying-adjacent states).
- [ ] `[NEW]` Per-character 1-day immunity after receiving Battle Medicine; does not block other healers from using Treat Wounds on same target.

### Specialty Crafting
- [ ] `[NEW]` Specialty Crafting grants +1 circumstance bonus to relevant Craft checks; at Master proficiency, bonus increases to +2.
- [ ] `[NEW]` GM-adjudicated partial applicability for items spanning multiple specialties (system flags for manual review).

### Virtuosic Performer
- [ ] `[NEW]` Virtuosic Performer grants +1 circumstance bonus to chosen performance type; at Master proficiency, +2.

---

## Edge Cases
- [ ] `[NEW]` Skill feat slot at level 2 filled with non-skill feat: blocked.
- [ ] `[NEW]` Assurance selected twice for same skill: blocked (one selection per skill).
- [ ] `[NEW]` Battle Medicine on target already treated today: blocked for same healer.

## Failure Modes
- [ ] `[TEST-ONLY]` Assurance modifiers/penalties: silently ignored (not added to result).
- [ ] `[TEST-ONLY]` Recognize Spell Crit Fail: returns false info, not an error.
- [ ] `[TEST-ONLY]` Trick Magic Item Crit Fail: locked out per-item per-day (not permanently).

## Security acceptance criteria
- Security AC exemption: game-mechanic feat system logic; no new routes or user-facing input beyond existing character creation and leveling forms
- Agent: qa-dungeoncrawler
- Status: pending
