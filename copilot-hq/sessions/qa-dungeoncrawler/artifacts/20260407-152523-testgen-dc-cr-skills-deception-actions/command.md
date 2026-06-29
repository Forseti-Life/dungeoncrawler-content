# Test Plan Design: dc-cr-skills-deception-actions

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:25:23+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-skills-deception-actions/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-skills-deception-actions "<brief summary>"
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

# Acceptance Criteria: dc-cr-skills-deception-actions

## Gap analysis reference
- DB sections: core/ch04/Deception (Cha) (REQs 1657–1668)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Create a Diversion [1 action]
- [ ] `[NEW]` Create a Diversion is a 1-action with appropriate traits (manipulate OR auditory+linguistic+mental depending on method).
- [ ] `[NEW]` Grants +4 circumstance bonus to character's Stealth check vs all targeted observers' Perception DCs for 1 minute after attempting.
- [ ] `[NEW]` On success: character becomes Hidden (not Undetected); reverts to Observed on most actions except Hide, Sneak, or Step.
- [ ] `[NEW]` Striking while hidden from diversion: target is flat-footed for that attack; character becomes Observed after the strike.

### Impersonate [Exploration, Secret]
- [ ] `[NEW]` Impersonate is an exploration activity; requires 10 minutes + disguise kit.
- [ ] `[NEW]` Passive observers: system rolls Perception vs character's Deception DC; active searchers use Seek.
- [ ] `[NEW]` Critical Failure: reveals character's true identity to observers who would recognize them.

### Lie [Auditory, Linguistic, Secret]
- [ ] `[NEW]` Lie is a secret check; roll once and compare to each target's Perception DC.
- [ ] `[NEW]` Failure grants the target a +4 circumstance bonus to resist future lies from this character for the rest of the conversation.
- [ ] `[NEW]` GM may allow a delayed recheck if the creature encounters contradicting evidence later.

### Feint [1 action, Mental, Trained]
- [ ] `[NEW]` Feint is a 1-action, mental trait action requiring Trained Deception and melee reach.
- [ ] `[NEW]` Degrees: Crit Success = target flat-footed for full turn of attacks; Success = flat-footed for next one attack only; Crit Failure = attacker becomes flat-footed.

---

## Edge Cases
- [ ] `[NEW]` Create a Diversion bonus applies only to the attempt made (not future diversions).
- [ ] `[NEW]` Feint blocked for untrained Deception or when not in melee reach.
- [ ] `[NEW]` Impersonate without disguise kit: check proceeds at –2 or blocked per GM.

## Failure Modes
- [ ] `[TEST-ONLY]` Feint outside melee reach: blocked.
- [ ] `[TEST-ONLY]` Lie failure resistance tracked per conversation, not permanently.
- [ ] `[TEST-ONLY]` Crit Fail Impersonate: correctly reveals true identity (not just a failure).

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter/exploration handlers
- Agent: qa-dungeoncrawler
- Status: pending
