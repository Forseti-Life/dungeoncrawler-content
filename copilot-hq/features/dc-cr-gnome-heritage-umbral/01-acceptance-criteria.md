# Acceptance Criteria: dc-cr-gnome-heritage-umbral

## Gap analysis reference
- DB sections: core/ch02 (Gnome Heritages)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-darkvision (shared sense type)

---

## Happy Path

### Darkvision
- [ ] `[NEW]` Umbral Gnome has darkvision — can see in complete darkness with no penalty.
- [ ] `[NEW]` Uses the shared darkvision sense type defined by dc-cr-darkvision; no new sense logic required.
- [ ] `[NEW]` Darkvision replaces (or supersedes) Low-Light Vision — gnome already has Low-Light Vision from ancestry, but darkvision is strictly superior.

### Heritage Availability
- [ ] `[NEW]` Umbral Gnome heritage is selectable when Gnome ancestry is chosen.

---

## Edge Cases
- [ ] `[NEW]` If darkvision is already granted by another source (feat, item), the character does not gain a duplicate sense entry.

## Failure Modes
- [ ] `[TEST-ONLY]` Darkvision grants vision in complete darkness — not just dim light (which is Low-Light Vision only).

## Security acceptance criteria
- Security AC exemption: game-mechanic sense; no new routes or user-facing input
