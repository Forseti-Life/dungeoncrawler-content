# Acceptance Criteria: dc-cr-gnome-obsession

## Gap analysis reference
- DB sections: core/ch02 (Gnome ancestry feats)
- Depends on: dc-cr-gnome-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-character-leveling, dc-cr-skill-system

---

## Happy Path

### Availability
- [ ] `[NEW]` Gnome Obsession is selectable as a Gnome feat 1.

### Lore selection and scaling
- [ ] `[NEW]` On feat selection, the character chooses one Lore skill and becomes trained in it.
- [ ] `[NEW]` At level 2, the chosen Lore upgrades to expert, and the background Lore (if any) also upgrades to expert.
- [ ] `[NEW]` At level 7, the tracked Lore skills upgrade to master.
- [ ] `[NEW]` At level 15, the tracked Lore skills upgrade to legendary.

---

## Edge Cases
- [ ] `[NEW]` If the character has no background Lore, only the chosen Lore is auto-upgraded.
- [ ] `[NEW]` Manual proficiency edits do not break the milestone auto-upgrade path.

## Failure Modes
- [ ] `[TEST-ONLY]` Non-Lore skills cannot be chosen for the feat.
- [ ] `[TEST-ONLY]` The auto-upgrades do not fire at levels other than 2, 7, and 15.

## Security acceptance criteria
- Security AC exemption: skill-progression data only; no new route surface
