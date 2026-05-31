# Test Plan: dc-cr-creature-identification — Creature Identification

**QA owner:** qa-dungeoncrawler
**Feature:** dc-cr-creature-identification
**Depends on:** dc-cr-skill-system ✓, dc-cr-recall-knowledge
**KB reference:** TC-RK-05 in dc-cr-skills-recall-knowledge covers the Crit Fail false-info obfuscation pattern — same mechanic applies here for creature identification Crit Fail.

---

## Summary

12 TCs (TC-CI-01–12) covering: skill routing by creature trait (6 trait groups + multi-skill + Lore fallback), untrained use permission, DC resolution dependency gate, all four degrees of success (Crit Success bonus fact / Success standard info / Failure no info / Crit Fail false info obfuscated), edge cases (unknown type → GM Lore, Crit Fail player-facing mask), and failure mode (invalid skill validation).

**Dependency split:**
- 9 TCs immediately activatable (dc-cr-skill-system ✓ + dc-cr-recall-knowledge in backlog)
- 3 TCs conditional on dc-cr-dc-rarity-spell-adjustment (TC-CI-06, TC-CI-07, TC-CI-10)

---

## Test Cases

### Skill Routing by Creature Trait (TC-CI-01–05)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-CI-01 | Arcana routing: Aberrations, Constructs, Humanoids, Oozes map to Arcana | Playwright / encounter | Recall Knowledge action against Aberration character → skill_required includes "arcana"; Construct, Humanoid, Ooze same result | authenticated |
| TC-CI-02 | Nature routing: Animals, Beasts, Fungi, Plants map to Nature | Playwright / encounter | Recall Knowledge against Animal → skill_required includes "nature"; Beast/Fungi/Plant same | authenticated |
| TC-CI-03 | Religion routing: Celestials, Fiends, Monitors, Undead map to Religion | Playwright / encounter | Recall Knowledge against Fiend → skill_required includes "religion"; Celestial/Monitor/Undead same | authenticated |
| TC-CI-04 | Multi-skill routing: Dragons/Elementals allow Arcana or Nature; Fey allow Nature or Occultism; Spirits allow Occultism | Playwright / encounter | Dragon creature → skill_required includes both "arcana" and "nature"; Fey → "nature" and "occultism"; Spirit → "occultism" | authenticated |
| TC-CI-05 | Lore fallback: any creature type allows appropriate Lore subcategory as alternative | Playwright / encounter | Recall Knowledge against any creature → Lore skill option present in valid skill list | authenticated |

### Skill Selection and Untrained Use (TC-CI-06)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-CI-06 | Character may use any one valid mapped skill; trained or untrained both permitted | Playwright / encounter | Character untrained in Arcana but attempting Recall Knowledge on Humanoid: untrained use allowed (no trained-gate block); roll proceeds with untrained penalty if any | authenticated |

### DC Resolution (TC-CI-07)

| TC | Description | Suite | Expected behavior | Roles | Dependency |
|----|-------------|-------|-------------------|-------|------------|
| TC-CI-07 | Creature identification DC = level-based DC + rarity adjustment from dc-cr-dc-rarity-spell-adjustment | Playwright / encounter | Level 3 common creature: DC matches level-based table value with no rarity modifier; Level 3 uncommon creature: DC elevated per rarity table | authenticated | dc-cr-dc-rarity-spell-adjustment |

### Degrees of Success (TC-CI-08–11)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-CI-08 | Crit Success: all standard info + bonus fact returned | Playwright / encounter | Roll result = Crit Success → response includes full standard creature info block AND at least one bonus fact (e.g., special ability, weakness detail) | authenticated |
| TC-CI-09 | Success: standard creature info returned (no bonus) | Playwright / encounter | Roll result = Success → response includes standard info block; no bonus fact present in response | authenticated |
| TC-CI-10 | Failure: no info returned | Playwright / encounter | Roll result = Failure → response body contains no creature info; UI shows generic "no information recalled" message | authenticated |
| TC-CI-11 | Crit Failure: false info presented as true (no failure indicator shown to player) | Playwright / encounter | Roll result = Crit Fail → response includes fabricated/false creature info; player-facing UI shows no "failed" or "uncertain" indicator; log records actual result as crit-fail internally | authenticated |

### Edge Cases (TC-CI-12)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-CI-12 | Creature type not in standard mapping: system defaults to GM-adjudicated Lore check | Playwright / encounter | Creature with no mapped trait type → skill_required = null or "lore_gm"; UI indicates "use appropriate Lore (GM)" | authenticated |

### Failure Mode (TC-CI-13)

| TC | Description | Suite | Expected behavior | Roles |
|----|-------------|-------|-------------------|-------|
| TC-CI-13 | Invalid skill selection: attempting Recall Knowledge with a skill not in the valid list for that creature type blocked or flagged | Playwright / encounter | Character attempts to use Athletics (never valid) for creature identification → validation error "skill not applicable to this creature type"; system does not roll | authenticated |

---

## Open PM questions / automation notes

1. **TC-CI-11 Crit Fail false info model**: Same open question as TC-RK-05 in dc-cr-skills-recall-knowledge — PM must confirm the player-facing message format contract (what text is shown vs what is logged internally). This is cross-feature; one PM decision should update both plans.
2. **TC-CI-08 bonus fact definition**: PM should confirm what constitutes a "bonus fact" in the data model — is it a separate field on the creature entity, or a subset of the full stat block? Automation needs a defined field to assert against.
3. **TC-CI-12 GM Lore handling**: Whether the system presents a skill picker to the GM or auto-selects "Lore" with a GM note needs Dev implementation decision before Stage 0.
4. **TC-CI-06 untrained penalty**: Confirm whether untrained Recall Knowledge has a penalty (–2 or none) — behavior inherited from dc-cr-skill-system; no new AC here but automation needs to verify the expected roll modifier.
