# Test Plan Design: dc-cr-dwarf-ancestry

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:43:04+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-dwarf-ancestry/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-dwarf-ancestry "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

# Acceptance Criteria: dc-cr-dwarf-ancestry

## Gap analysis reference
- DB sections: core/ch02 (Dwarf Ancestry — not yet loaded in dc_requirements; see human ancestry DB gap note)
- Depends on: dc-cr-ancestry-system ✓, dc-cr-heritage-system ✓, dc-cr-languages ✓

---

## Happy Path

### Core Stats
- [ ] `[NEW]` Dwarf ancestry: HP 10, Medium size, Speed 20 feet.
- [ ] `[NEW]` Ability boosts: Constitution, Wisdom, and one free boost.
- [ ] `[NEW]` Ability flaw: Charisma.
- [ ] `[NEW]` Traits: Dwarf, Humanoid.
- [ ] `[NEW]` Starting languages: Common, Dwarven.
- [ ] `[NEW]` Bonus languages: Gnomish, Goblin, Jotun, Orcish, Terran, Undercommon — each available at 1 bonus language per point of positive Intelligence modifier.

### Clan Dagger
- [ ] `[NEW]` Every Dwarf receives a free Clan Dagger at character creation.
- [ ] `[NEW]` Selling the Clan Dagger is flagged as a taboo (system note/warning displayed).

### Low-Light Vision
- [ ] `[NEW]` Dwarves have Low-Light Vision (see in dim light as bright light; see twice as far from light sources).

### Ancestry Feats (1st-level Dwarf feats available at character creation)
- [ ] `[NEW]` Dwarven Lore (1st): trained in Crafting and Religion; Dwarven Lore skill (special Lore subcategory).
- [ ] `[NEW]` Dwarven Weapon Familiarity (1st): trained in battleaxe, pick, and warhammer; Dwarf weapon trait feats apply.
- [ ] `[NEW]` Rock Runner (1st): Acrobatics DCs for uneven stone surfaces reduced; not flat-footed on uneven/slippery stone surfaces.
- [ ] `[NEW]` Stonecunning (1st): +2 Perception to notice unusual stonework; automatically rolled when passing within 10 ft.
- [ ] `[NEW]` Unburdened Iron (1st): removes –5 ft Speed penalty from medium/heavy armor; reduces Speed penalties from armor by 5 ft (minimum 0).
- [ ] `[NEW]` Vengeful Hatred (1st): +1 circumstance bonus to damage vs. selected creature type (giants/humanoids/orcs/ogrekin); one type chosen at selection.

### Heritage Selection (mandatory at character creation)
- [ ] `[NEW]` Ancient-Blooded Dwarf: +1 circumstance bonus to saves vs. magic; once per day Aid a save as free action.
- [ ] `[NEW]` Death Warden Dwarf: additional saving throw vs. necromancy on Crit Fail if no Crit Fail resistance.
- [ ] `[NEW]` Forge Dwarf: heat resistance; ignore the heat effects of non-extreme environments and standard armor.
- [ ] `[NEW]` Rock Dwarf: +1 circumstance bonus to Fortitude against Shove and Trip; treated as 1 size larger for item Bulk limits.
- [ ] `[NEW]` Strong-Blooded Dwarf: +1 status bonus to Fortitude saves vs. poison; reduce poison stage on Crit Success (expunge on Success).

---

## Edge Cases
- [ ] `[NEW]` Dwarf Dwarven Weapon Familiarity + class weapon proficiency: trained in all marked as Dwarf weapons (group treats them as martial if already in martial group, or simple if not yet trained).
- [ ] `[NEW]` Rock Runner: does NOT prevent flat-footed from other sources (only stone uneven surfaces exempted).

## Failure Modes
- [ ] `[TEST-ONLY]` Dwarf Speed 20 ft: default for Medium creature; does not auto-reduce to 20 (must be set explicitly).
- [ ] `[TEST-ONLY]` Clan Dagger free item: does not cost gp at character creation; appears in inventory automatically.

## Security acceptance criteria
- Security AC exemption: game-mechanic ancestry selection; no new routes or user-facing input beyond existing character creation forms
- Agent: qa-dungeoncrawler
- Status: pending
