# Acceptance Criteria: dc-cr-skills-stealth-hide-sneak

## Gap analysis reference
- DB sections: core/ch04/Stealth (Dex) (REQs 1715–1729)
- Depends on: dc-cr-skill-system ✓, dc-cr-conditions

---

## Happy Path

### Conceal an Object [1 action, Manipulation]
- [ ] `[NEW]` Conceal an Object allows hiding a carried/worn item; observers must Seek to find it.
- [ ] `[NEW]` Crit Success: item hidden; observers need Seek to discover it; Success: same as Crit Success but lower DC to find.

### Hide [1 action]
- [ ] `[NEW]` Hide requires cover or concealment to attempt; transitions character from Observed → Hidden vs targeted observers.
- [ ] `[NEW]` Check: Stealth vs each observer's Perception DC.
- [ ] `[NEW]` If any observer beats DC: character remains Observed (not just detected by one).
- [ ] `[NEW]` Hidden character cannot use most actions without becoming Observed; Hide/Sneak/Step/undetected actions preserve Hidden status.

### Sneak [1 action, Move]
- [ ] `[NEW]` Sneak is a 1-action move requiring Hidden status; moves at half Speed (rounded down to 5-ft intervals).
- [ ] `[NEW]` At end of Sneak: roll Stealth vs each observer's Perception.
- [ ] `[NEW]` Success: remain Hidden; Failure: become Observed by failing observer.
- [ ] `[NEW]` Cannot end Sneak in an obvious/open location without becoming Observed.

### Avoid Notice [Exploration]
- [ ] `[NEW]` Avoid Notice (exploration) uses Stealth for the duration of exploration; character starts as Unnoticed.
- [ ] `[NEW]` First failed Seek or Perception by a relevant creature transitions character to Observed.

---

## Edge Cases
- [ ] `[NEW]` Sneak without Hidden status first: blocked — must be Hidden before Sneak.
- [ ] `[NEW]` Hide in open terrain with no cover: blocked or auto-fails.

## Failure Modes
- [ ] `[TEST-ONLY]` Sneak at full speed: rounds down to 5-ft interval (not blocked).
- [ ] `[TEST-ONLY]` Hide vs multiple observers: must succeed against ALL to stay Hidden.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter/exploration handlers
