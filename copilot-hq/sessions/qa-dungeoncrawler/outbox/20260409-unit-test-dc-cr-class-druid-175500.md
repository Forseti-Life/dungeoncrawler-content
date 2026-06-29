# QA Outbox: dc-cr-class-druid Unit Test Verification

- Status: done
- Summary: Verified dev commit `492995e40` (+294 lines druid data to CharacterManager.php) against AC `features/dc-cr-class-druid/01-acceptance-criteria.md`. All AC items pass: CLASSES['druid'] (hp=8, Wisdom key ability, Expert Will, Trained Perception/Fortitude/Reflex, armor=[light,medium] with metal forbidden via armor_restriction+anathema, fixed_skills=[Nature], wild_empathy Diplomacy on animals/plants, primal prepared spellcasting Wisdom L1-20 slot table with 10th at L19/L20, 4 orders Animal/Leaf/Storm/Wild with correct focus pool starts leaf=2/storm=2/animal=1/wild=1, universal anathema metal armor + teaching Druidic with consequence remove primal spellcasting+order benefits until atone). CLASS_ADVANCEMENT L1-L19 fully populated including Primal Hierophant at L19. CLASS_FEATS 40 feats at L1 and every even level. Suite dc-cr-class-druid-e2e has 30 TCs covering all AC items. PHP lint clean. Site audit 0 violations. **Gate 2 verdict: APPROVE.**

## Verification Evidence

### CLASSES['druid'] (line 1544)
| AC Item | Code Field | Result |
|---|---|---|
| hp=8 | `'hp' => 8` | ✓ PASS |
| key_ability=Wisdom | `'key_ability' => 'Wisdom'` | ✓ PASS |
| Expert Will | `'will' => 'Expert'` | ✓ PASS |
| Trained Perception | `'perception' => 'Trained'` | ✓ PASS |
| Trained Fortitude | `'fortitude' => 'Trained'` | ✓ PASS |
| Trained Reflex | `'reflex' => 'Trained'` | ✓ PASS |
| Armor [light, medium] | `'armor_proficiency' => ['light', 'medium']` | ✓ PASS |
| Metal armor forbidden | `armor_restriction` text + universal anathema `universal_acts` | ✓ PASS |
| fixed_skills=[Nature] | `'fixed_skills' => ['Nature']` | ✓ PASS |
| Wild Empathy (Diplomacy animals) | `'wild_empathy'` block with Diplomacy/Nature description | ✓ PASS |
| Primal Prepared Wisdom | `primal_spellcasting: tradition=Primal, type=Prepared, ability=Wisdom` | ✓ PASS |
| Spell slots L1-20 with 10th at L19 | `spell_slots_by_level` keys 1-20, level 19 includes `'10th' => 1` | ✓ PASS |
| 4 orders: Animal/Leaf/Storm/Wild | `choices: ['animal','leaf','storm','wild']` | ✓ PASS |
| focus_pool_start leaf=2, storm=2 | `focus_pool_start: leaf=>2, storm=>2` | ✓ PASS |
| focus_pool_start animal=1, wild=1 | `focus_pool_start: animal=>1, wild=>1` | ✓ PASS |
| Universal anathema metal armor | `universal_acts[0]: 'Wearing metal armor or carrying a metal shield'` | ✓ PASS |
| Universal anathema teaching Druidic | `universal_acts[1]: 'Teaching the Druidic language to non-druids'` | ✓ PASS |
| Consequence removes primal spells+order | `'consequence' => 'Committing an anathema act removes all primal spellcasting and order benefits...'` | ✓ PASS |
| Order Explorer second-order behavior | `order-explorer` feat: `'Violating that order's anathema removes its feats but not your main primal connection'` | ✓ PASS |

### CLASS_ADVANCEMENT['druid'] (line 7355)
| Level | Feature(s) | Result |
|---|---|---|
| L1 | Primal Spellcasting, Druidic Order, Druidic Language, Wild Empathy, Druidic Anathema | ✓ PASS |
| L3 | Alertness, Expert Spellcaster, Great Nature's Lore | ✓ PASS |
| L5 | Lightning Reflexes, Armor Expertise | ✓ PASS |
| L7 | Weapon Specialization, Resolve | ✓ PASS |
| L9 | Druidic Mastery, Magical Fortitude | ✓ PASS |
| L11 | Druid Expertise, Medium Armor Expertise | ✓ PASS |
| L13 | Greater Weapon Specialization, Medium Armor Mastery | ✓ PASS |
| L15 | Legendary Spellcaster | ✓ PASS |
| L17 | Druid Mastery | ✓ PASS |
| L19 | Primal Hierophant (10th-level prepared primal spell slot) | ✓ PASS |

### CLASS_FEATS['druid'] (line 2724)
- **Total feats**: 40 (Dev outbox said "30+" — undercount, not a defect; AC sets no upper bound)
- **Levels covered**: L1(7), L2(5), L4(5), L6(5), L8(4), L10(4), L12(3), L14(2), L16(2), L18(2), L20(1)
- AC requires: class feat at L1 and every even level (L2, L4, L6...L20) — all 11 feat levels present ✓

### PHP Lint
```
No syntax errors detected in CharacterManager.php
```

### Suite
- Suite `dc-cr-class-druid-e2e`: 30 TCs covering all AC items (Happy Path, Edge Cases, Failure Modes, Security)
- `python3 scripts/qa-suite-validate.py`: OK (validated 5 suite manifests)
- No new TCs needed — all AC items already have corresponding TCs

### Site Audit
- Last run: 20260409-190702 — 0 violations (data-only change, no new routes)

## Gate 2 Verdict

**APPROVE** — all AC items verified, PHP lint clean, suite complete, audit clean.

## Next actions
- Awaiting DC-BARB-001 fix (barbarian lightning-reflexes L7→L9) from dev-dungeoncrawler before barbarian can be re-verified
- No further action required for druid

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 40
- Rationale: Druid is a core PF2e class. APPROVE unblocks this feature for release-f gate. No defects found = no rework loop needed.

## Commits
- `0226aa2d0` — qa: druid class verification APPROVE — dc-cr-class-druid
