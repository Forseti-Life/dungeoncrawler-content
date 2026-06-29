# Test Plan Design: dc-cr-skills-recall-knowledge

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:28:30+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-recall-knowledge/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-recall-knowledge "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-recall-knowledge

## Gap analysis reference
- DB sections: core/ch04/Occultism (Int), core/ch04/Religion (Wis), core/ch04 General Skill Actions (Recall Knowledge rows, REQs 1591–1594, 2329)
- Depends on: dc-cr-skill-system ✓, dc-cr-creature-identification, dc-cr-dc-rarity-spell-adjustment

---

## Happy Path

### Recall Knowledge [1 action, Secret]
- [ ] `[EXTEND]` Recall Knowledge is a 1-action secret check; roll once and compare to GM-set DC.
- [ ] `[EXTEND]` Degrees: Crit Success = accurate info + bonus detail; Success = accurate info; Failure = nothing; Crit Failure = false info (player not told it is false).
- [ ] `[EXTEND]` Skill routing by topic: Arcana (arcane creatures/magic/planes), Crafting (item construction/artifice), Lore (specific subcategory), Medicine (diseases/poisons/anatomy), Nature (animals/plants/terrain), Occultism (metaphysics/weird philosophies/occult magic), Religion (deities/undead/divine magic), Society (cultures/laws/humanoid organizations).
- [ ] `[EXTEND]` DC resolution: simple DC based on obscurity (GM-defined); creature/hazard DCs level-based; rarity adjustment applied via dc-cr-dc-rarity-spell-adjustment rules.

### Occultism (Int)
- [ ] `[NEW]` Occultism Decipher Writing covers metaphysics, syncretic principles, and weird philosophies (see Decipher Writing AC in dc-cr-skill-system).
- [ ] `[NEW]` Occultism Identify Magic routes to occult tradition items/effects.
- [ ] `[NEW]` Occultism Learn a Spell routes to occult tradition spells.

### Religion (Wis)
- [ ] `[NEW]` Religion Decipher Writing covers religious allegories, homilies, and proverbs.
- [ ] `[NEW]` Religion Identify Magic routes to divine tradition items/effects.
- [ ] `[NEW]` Religion Learn a Spell routes to divine tradition spells.

---

## Edge Cases
- [ ] `[EXTEND]` Wrong tradition skill used for Identify Magic: +5 DC penalty applied.
- [ ] `[EXTEND]` GM-set false info on Crit Fail: player-facing output shows "you recall…" (not "you failed").

## Failure Modes
- [ ] `[TEST-ONLY]` Recall Knowledge Crit Fail: returned info appears truthful to player, not flagged as false.
- [ ] `[TEST-ONLY]` Recall Knowledge with untrained proficiency: permitted (untrained action).
- [ ] `[TEST-ONLY]` Occultism/Religion Identify Magic blocked for wrong tradition without penalty only if hardcoded routing is not in place — must apply +5 DC not block.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes or user-facing input beyond existing encounter handlers
- Agent: qa-dungeoncrawler
- Status: pending
