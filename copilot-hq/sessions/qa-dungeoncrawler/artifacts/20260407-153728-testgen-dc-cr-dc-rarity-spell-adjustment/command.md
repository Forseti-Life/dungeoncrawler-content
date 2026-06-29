# Test Plan Design: dc-cr-dc-rarity-spell-adjustment

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:37:28+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-dc-rarity-spell-adjustment/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-dc-rarity-spell-adjustment "<brief summary>"
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

# Acceptance Criteria: dc-cr-dc-rarity-spell-adjustment

## Gap analysis reference
- DB sections: core/ch10/Setting DCs (10 reqs)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Simple DC Table
- [ ] `[NEW]` Simple DC table implemented (Untrained = 10, Trained = 15, Expert = 20, Master = 30, Legendary = 40, with sub-ratings).

### Level-Based DC Table
- [ ] `[NEW]` Level-based DC table implemented (levels 0–25 mapped to DCs per Table 10–4).

### Spell Level DCs
- [ ] `[NEW]` Spell-level DCs implemented for Identify Spell / Recall Knowledge about spells (spell level → DC mapping).

### DC Adjustment Table
- [ ] `[NEW]` DC adjustments: Incredibly Easy (–10), Very Easy (–5), Easy (–2), Normal (0), Hard (+2), Very Hard (+5), Incredibly Hard (+10).
- [ ] `[NEW]` Rarity adjustments applied as DC adjustments: Uncommon = Hard (+2), Rare = Very Hard (+5), Unique = Incredibly Hard (+10).
- [ ] `[NEW]` Minimum proficiency ranks: characters below that rank cannot succeed (but can attempt and crit fail).

### Specific DC Applications
- [ ] `[NEW]` Craft DC: item's level from Table 10–5 + rarity adjustment from Table 10–6.
- [ ] `[NEW]` Earn Income DC: task level = settlement level → DC from Table 10–5.
- [ ] `[NEW]` Gather Information DC: simple DC based on availability; raise for in-depth info.
- [ ] `[NEW]` Identify Magic / Learn a Spell DC: level-based + rarity adjustment.
- [ ] `[NEW]` Recall Knowledge DC: simple DC for general info; level-based for creatures/hazards; rarity adjustment applied.
- [ ] `[NEW]` NPC social DCs adjusted by attitude: Friendly = Easy (–2), Helpful = Very Easy (–5), Unfriendly = Hard (+2), Hostile = Very Hard (+5); fundamentally opposed request = Incredibly Hard or impossible.

## Edge Cases
- [ ] `[NEW]` Stacking adjustments: multiple adjustments combine (e.g., uncommon + hard = +4 total).
- [ ] `[NEW]` Below minimum proficiency rank: attempt allowed but success impossible.

## Failure Modes
- [ ] `[TEST-ONLY]` Rarity adjustment and level-based DC used together: both applied independently (additive).

## Security acceptance criteria
- Security AC exemption: game-mechanic DC calculation; no new routes or user-facing input beyond existing encounter and downtime handlers
- Agent: qa-dungeoncrawler
- Status: pending
