# Acceptance Criteria: dc-cr-skills-society-create-forgery

## Gap analysis reference
- DB sections: core/ch04/Society (Int) (REQs 1710–1714)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Recall Knowledge (Society)
- [ ] `[NEW]` Society covers: cultures, laws, social structures, history, humanoid organizations, nations, settlements.
- [ ] `[NEW]` Untrained Recall Knowledge permitted.

### Create a Forgery [Downtime, Secret, Trained]
- [ ] `[NEW]` Create a Forgery is a downtime activity (10 min per page); requires Trained Society and appropriate writing materials.
- [ ] `[NEW]` Difficulty: common documents (DC 20), specialist documents (DC 30+), official government seals (DC 40+).
- [ ] `[NEW]` On Failure: forgery is detectable; on Crit Failure: forgery is obviously fake AND character becomes aware it failed (can retry).
- [ ] `[NEW]` Detection: viewers use Society vs character's Deception DC when examining the forgery.

---

## Edge Cases
- [ ] `[NEW]` Create Forgery untrained: blocked.
- [ ] `[NEW]` Official seal forgery without special tools: DC 40 (highest tier, often auto-fail).

## Failure Modes
- [ ] `[TEST-ONLY]` Crit Fail: character is informed the forgery failed (not a surprise to them).
- [ ] `[TEST-ONLY]` Detection uses Deception DC not Forgery DC (two separate rolls).

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing downtime handlers
