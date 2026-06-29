# Acceptance Criteria: dc-cr-skills-thievery-disable-pick-lock

## Gap analysis reference
- DB sections: core/ch04/Thievery (Dex) (REQs 1738–1748)
- Depends on: dc-cr-skill-system ✓, dc-cr-equipment-system

---

## Happy Path

### Palm an Object [1 action, Manipulation]
- [ ] `[NEW]` Palm an Object hides a small, easily concealed item; observers must Seek to notice.
- [ ] `[NEW]` Success: item considered hidden on the character's person until Seek reveals it.

### Steal [1 action, Manipulation]
- [ ] `[NEW]` Steal takes a small item from a target; requires the target to be unaware of the attempt (Observed to character or distracted).
- [ ] `[NEW]` Critical Failure: target (and nearby observers) become aware the character attempted to steal.

### Disable a Device [2 actions, Manipulation, Trained]
- [ ] `[NEW]` Disable a Device costs 2 actions per attempt; requires thieves' tools and Trained Thievery.
- [ ] `[NEW]` Complex devices (traps, alarms, etc.) may require multiple successes.
- [ ] `[NEW]` Crit Failure: triggers the trap/device.

### Pick a Lock [2 actions, Manipulation, Trained]
- [ ] `[NEW]` Pick a Lock costs 2 actions per attempt; requires thieves' tools and Trained Thievery.
- [ ] `[NEW]` DC set by lock quality (simple, average, good, superior).
- [ ] `[NEW]` Without thieves' tools (improvised): DC increases by 5.
- [ ] `[NEW]` Crit Failure: lock becomes jammed; no further attempts until repaired.

---

## Edge Cases
- [ ] `[NEW]` Disable Device without thieves' tools: improvised penalty or blocked per GM (system flags).
- [ ] `[NEW]` Pick Lock jammed state tracked per lock; persists until repaired.

## Failure Modes
- [ ] `[TEST-ONLY]` Steal Crit Fail: observers become aware (not just target).
- [ ] `[TEST-ONLY]` Pick Lock Crit Fail: lock jammed, further attempts blocked (not retriable).
- [ ] `[TEST-ONLY]` Disable Device untrained: blocked.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter handlers
