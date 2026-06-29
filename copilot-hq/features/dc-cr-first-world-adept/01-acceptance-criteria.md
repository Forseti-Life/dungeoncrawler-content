# Acceptance Criteria: dc-cr-first-world-adept

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-spellcasting, one existing primal innate spell source

---

## Happy Path

### Availability
- [ ] `[NEW]` First World Adept is selectable as a Gnome feat 9 only when the character already has at least one primal innate spell.

### Granted spells
- [ ] `[NEW]` Selecting the feat grants `faerie fire` as a 2nd-level primal innate spell usable once per day.
- [ ] `[NEW]` Selecting the feat grants `invisibility` as a 2nd-level primal innate spell usable once per day.

---

## Edge Cases
- [ ] `[NEW]` The prerequisite is satisfied by any valid primal innate spell source (heritage or feat), not by prepared spellcasting alone.
- [ ] `[NEW]` Both granted spells reset on daily preparation with other once-per-day innate spells.

## Failure Modes
- [ ] `[TEST-ONLY]` Characters without a primal innate spell cannot select First World Adept.
- [ ] `[TEST-ONLY]` The granted spells are not castable more than once per day each.

## Security acceptance criteria
- Security AC exemption: ancestry feat grant only; no new route surface beyond existing character and spell flows
