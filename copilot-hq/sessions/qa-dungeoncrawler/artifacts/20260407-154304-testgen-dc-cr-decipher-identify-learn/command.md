# Test Plan Design: dc-cr-decipher-identify-learn

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:43:04+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-decipher-identify-learn/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-decipher-identify-learn "<brief summary>"
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

# Acceptance Criteria: dc-cr-decipher-identify-learn

## Gap analysis reference
- DB sections: core/ch04 General Skill Actions (REQs 1574–1590 — Decipher Writing, Identify Magic, Learn a Spell; already in_progress via dc-cr-skill-system)
- Depends on: dc-cr-skill-system ✓, dc-cr-dc-rarity-spell-adjustment, dc-cr-spellcasting

---

## Happy Path

### Decipher Writing [Exploration, Secret, Trained]
- [ ] `[NEW]` Decipher Writing is an exploration activity (1 min/page; ~1 hr/page for ciphers); requires Trained in applicable skill.
- [ ] `[NEW]` Skills: Arcana (arcane/esoteric texts), Occultism (metaphysical/occult texts), Religion (religious texts), Society (coded/legal/historical texts).
- [ ] `[NEW]` Character must be able to read the language; Society may allow attempt in unfamiliar language at GM discretion.
- [ ] `[NEW]` Degrees: Crit Success = full meaning; Success = true meaning (coded text = general summary); Failure = blocked + –2 circumstance penalty to retry same text; Crit Failure = false interpretation (player believes they succeeded).

### Identify Magic [Exploration, Trained]
- [ ] `[NEW]` Identify Magic is an exploration activity (10 minutes); requires Trained in applicable tradition skill.
- [ ] `[NEW]` Skills: Arcana (arcane), Nature (primal), Occultism (occult), Religion (divine).
- [ ] `[NEW]` DC = standard DC for the item/effect's level; +5 DC penalty if using wrong tradition skill.
- [ ] `[NEW]` Degrees: Crit Success = full ID + one bonus fact; Success = full identification; Failure = blocked for 1 day (cannot retry same item); Crit Failure = false identification (secret trait — player sees false result).
- [ ] `[NEW]` Pre-existing spell effects require this action (cannot use Recall Knowledge to identify active effects).

### Learn a Spell [Exploration, Trained]
- [ ] `[NEW]` Learn a Spell is an exploration activity (1 hour); requires Trained in applicable tradition skill.
- [ ] `[NEW]` Material cost = spell level × 10 gp; consumed on attempt (regardless of outcome).
- [ ] `[NEW]` DC = standard DC for the spell's level (from dc-cr-dc-rarity-spell-adjustment).
- [ ] `[NEW]` Degrees: Crit Success = learn spell + refund half material cost; Success = learn spell, full cost consumed; Failure = spell not learned, no cost (cost not consumed on failure); Crit Failure = spell not learned + materials lost.
- [ ] `[NEW]` Character must have an appropriate spellcasting class feature and the spell must be on their tradition's list.

---

## Edge Cases
- [ ] `[NEW]` Decipher Writing retry after Failure: –2 penalty persists for same text until fully deciphered.
- [ ] `[NEW]` Identify Magic 1-day block: same item only; another item can be attempted immediately.
- [ ] `[NEW]` Learn a Spell when character lacks a spellcasting class feature: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Decipher Writing Crit Fail: player sees a result (not an error); system marks internally as false.
- [ ] `[TEST-ONLY]` Identify Magic Crit Fail: false ID presented as true; player cannot distinguish it from success.
- [ ] `[TEST-ONLY]` Learn a Spell Failure: NO materials consumed (only Crit Fail destroys them).

## Security acceptance criteria
- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing exploration phase handlers
- Agent: qa-dungeoncrawler
- Status: pending
