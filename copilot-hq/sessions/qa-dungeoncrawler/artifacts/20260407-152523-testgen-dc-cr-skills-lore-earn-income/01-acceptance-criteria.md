# Acceptance Criteria: dc-cr-skills-lore-earn-income

## Gap analysis reference
- DB sections: core/ch04/Lore (Int) (REQs 1684–1687)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Recall Knowledge (Lore)
- [ ] `[NEW]` Lore Recall Knowledge functions identically to other skills' Recall Knowledge but is scoped to a specific Lore subcategory (e.g., Mercantile Lore, Sailing Lore).
- [ ] `[NEW]` Characters may have multiple Lore subcategories from background, ancestry, or class features.
- [ ] `[NEW]` Untrained Lore can be used to Recall Knowledge, but only for the chosen subcategory.

### Earn Income [Downtime]
- [ ] `[NEW]` Earn Income is a downtime activity using Lore (or Crafting/Performance for other professions).
- [ ] `[NEW]` DC determined by task level (1–20); max task level = character level (except Trained cap at level –1).
- [ ] `[NEW]` Degrees: Crit Success = next-tier daily income; Success = on-level daily income; Failure = 0 income but no penalty; Crit Failure = 0 income + blocked from that employer for ~1 week.

---

## Edge Cases
- [ ] `[NEW]` Multiple Lore subcategories stacked: each tracked independently.
- [ ] `[NEW]` Earn Income task level cap enforced per proficiency tier.

## Failure Modes
- [ ] `[TEST-ONLY]` Earn Income Crit Fail blocks same employer for ~1 week; other employers unaffected.
- [ ] `[TEST-ONLY]` Task above level cap: blocked (not just penalized).

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing downtime handlers
