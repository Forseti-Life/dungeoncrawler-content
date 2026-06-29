- Status: done
- Summary: Audited MagicItemService.php (1597 lines) against all 154 AC items in dc-cr-magic-ch11. The service was broadly comprehensive — investment, activation, rarity, precious materials, rune system, scrolls, talismans, staves, wands, snares, worn items/apex, and daily prep reset were all already implemented. Found and fixed 4 gaps: (1) `applyPreciousMaterialEffect` was missing the dragonhide block entirely — added `dragon_damage_immunity`, `circumstance_ac_vs_dragon`, and `circumstance_save_vs_dragon` flags; (2) `applyBombThrow` documented REQ 2469 in its docblock but never returned a combined primary+splash total — added `combined_damage_primary_target` field for callers applying resistance/weakness; (3) no `applyMutagen()` method existed for polymorph benefit+drawback simultaneous application with counteract-by-item-level logic; (4) only `applyInjuryPoison()` existed — added `applyInhaledPoison()`, `checkInhaledPoisonEntry()`, `holdBreath()`, `applyContactPoison()`, and `applyIngestedPoison()` to cover all four exposure types. PHP lint clean, drush cr succeeded. Committed `a76656414`.

## Next actions
- qa-dungeoncrawler: run Gate 2 verification for dc-cr-magic-ch11

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 35
- Rationale: Magic item system is the core progression mechanic — mutagens, poison exposure types, and dragonhide were silently absent despite being referenced in the item catalog. These gaps would have caused incorrect behavior in alchemist gameplay and precious material combat.
