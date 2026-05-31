# Acceptance Criteria: dc-cr-skills-crafting-actions

## Gap analysis reference
- DB sections: core/ch04/Crafting (Int) (REQs 1644–1656)
- Depends on: dc-cr-skill-system ✓, dc-cr-equipment-system (in-progress Release B)

---

## Happy Path

### Repair [Exploration, Trained]
- [ ] `[NEW]` Repair requires a repair kit, Trained Crafting, and 10 minutes; heals item HP.
- [ ] `[NEW]` HP restoration scales with proficiency: Crit Success = 10 + 10/rank; Success = 5 + 5/rank.
- [ ] `[NEW]` Critical Failure deals 2d6 damage to the item (after Hardness).
- [ ] `[NEW]` Destroyed items cannot be Repaired.

### Craft [Downtime, Trained]
- [ ] `[NEW]` Craft is a downtime activity requiring Trained Crafting, a formula, appropriate tools/workshop, and ≥50% raw material cost paid upfront.
- [ ] `[NEW]` Item level cap = character level; level 9+ requires Master Crafting; level 16+ requires Legendary.
- [ ] `[NEW]` Minimum 4 days; additional days reduce remaining cost; can pause and resume.
- [ ] `[NEW]` Degrees: Crit Success = faster cost reduction (level+1 rate); Success = normal; Failure = full material salvage; Crit Failure = 10% material loss.
- [ ] `[NEW]` Consumables: up to 4 identical items per Craft check; ammunition in standard batch quantities.
- [ ] `[NEW]` Special feats required for alchemical items (Alchemical Crafting), magic items (Magical Crafting), snares (Snare Crafting).

### Identify Alchemy [Exploration, Trained]
- [ ] `[NEW]` Identify Alchemy requires Trained Crafting, alchemist's tools, and 10 uninterrupted minutes.
- [ ] `[NEW]` Critical Failure produces a false identification result.

---

## Edge Cases
- [ ] `[NEW]` Repair on destroyed item: blocked with error.
- [ ] `[NEW]` Craft item above character level: blocked.
- [ ] `[NEW]` Crafting magic items without Magical Crafting feat: blocked.

## Failure Modes
- [ ] `[TEST-ONLY]` Repair on item at full HP: no-op or minimal effect per GM.
- [ ] `[TEST-ONLY]` Craft without formula: blocked.
- [ ] `[TEST-ONLY]` Identify Alchemy Crit Fail returns false data, not an error.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing downtime/exploration handlers
