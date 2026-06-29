# Test Plan Design: dc-cr-skills-diplomacy-actions

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-diplomacy-actions/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-diplomacy-actions "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-diplomacy-actions

## Gap analysis reference
- DB sections: core/ch04/Diplomacy (Cha) (REQs 1669–1677) + Intimidation (Cha) (REQs 1678–1683)
- Note: no separate intimidation feature stub found; grouped here pending dev confirmation
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Gather Information [Downtime]
- [ ] `[NEW]` Gather Information is a downtime activity taking ~2 hrs; yields rumors and info about a specific topic.
- [ ] `[NEW]` Lower DC for common knowledge; higher for secrets or rare information.
- [ ] `[NEW]` Critical Failure reveals to the target community that the character is asking questions.

### Make an Impression [Downtime]
- [ ] `[NEW]` Make an Impression is a downtime activity (~10 min); shifts target NPC's attitude one step on success.
- [ ] `[NEW]` Degrees: Crit Success = two steps toward Friendly; Success = one step; Failure = no change; Crit Failure = one step toward Hostile.
- [ ] `[NEW]` Initial attitude (Unfriendly through Helpful) loaded from NPC data.

### Request [1 action, Auditory, Linguistic, Mental]
- [ ] `[NEW]` Request requires a Friendly or Helpful attitude or relevant leverage.
- [ ] `[NEW]` Unreasonable requests carry a –2 to –4 circumstance penalty.
- [ ] `[NEW]` Degrees: Crit Success = comply + go one step more helpful; Success = comply; Failure = decline; Crit Fail = decline + become one step less friendly.

---

## Intimidation (Cha)

### Coerce [Downtime]
- [ ] `[NEW]` Coerce is a downtime activity (~10 min one-on-one); produces compliance from any attitude level but creates long-term resentment.
- [ ] `[NEW]` On success: target complies for ~1 week then becomes Unfriendly; Crit Success = ~1 month.
- [ ] `[NEW]` Target immune to further Coerce attempts for ~1 week.

### Demoralize [1 action, Auditory, Emotion, Mental]
- [ ] `[NEW]` Demoralize is a 1-action with Auditory+Emotion+Mental traits; target cannot be immune.
- [ ] `[NEW]` Degrees: Crit Success = frightened 2; Success = frightened 1. Target becomes immune to Demoralize attempts for 10 minutes.
- [ ] `[NEW]` Demoralize blocked if target can't understand the character's language (language barrier check).

---

## Edge Cases
- [ ] `[NEW]` Request at Unfriendly/Hostile attitude without leverage: auto-fail or –4 penalty.
- [ ] `[NEW]` Coerce immunity timer tracked; further attempts blocked during window.
- [ ] `[NEW]` Demoralize vs immune target: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Gather Info Crit Fail: community becomes aware character is investigating.
- [ ] `[TEST-ONLY]` Make Impression cannot shift attitude beyond Helpful or below Hostile.
- [ ] `[TEST-ONLY]` Demoralize without shared language: blocked (not just penalized).

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing downtime/encounter handlers
- Agent: qa-dungeoncrawler
- Status: pending
