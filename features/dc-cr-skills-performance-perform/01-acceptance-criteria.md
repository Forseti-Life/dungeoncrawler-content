# Acceptance Criteria: dc-cr-skills-performance-perform

## Gap analysis reference
- DB sections: core/ch04/Performance (Cha) (REQs 1705–1708)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Perform [1 action or Downtime]
- [ ] `[NEW]` Perform can be used as a 1-action in encounter (to support abilities) or downtime to Earn Income.
- [ ] `[NEW]` Art types: acting, comedy, dance, oratory, singing, keyboards, percussion, strings, winds, etc. (character chooses one art type at character creation or training).
- [ ] `[NEW]` Earn Income via Performance: follows standard Earn Income table (see dc-cr-skills-lore-earn-income AC).

### Perform (Encounter — Inspire / Class Feature Support)
- [ ] `[NEW]` Perform check result (Crit Success / Success / Failure / Crit Failure) communicated to class-feature hooks (e.g., Bard Inspire Courage).
- [ ] `[NEW]` Crit Success: crowd loves it; Success: polite reception; Failure: poor reaction; Crit Failure: embarrassing.

---

## Edge Cases
- [ ] `[NEW]` Multiple art types: each tracked independently if feat grants additional art.

## Failure Modes
- [ ] `[TEST-ONLY]` Perform Crit Fail in encounter does not silently succeed — returns fail degree to caller.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter/downtime handlers
