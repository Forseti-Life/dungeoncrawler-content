# Outbox: dc-cr-dwarf-ancestry

- Status: done
- Summary: Implemented the complete Dwarf ancestry data layer in CharacterManager.php. Added the missing Free ability boost, bonus_language_pool (Gnomish/Goblin/Jotun/Orcish/Terran/Undercommon), bonus_language_source (intelligence_modifier), and starting_equipment (clan-dagger) to ANCESTRIES['Dwarf']. Expanded HERITAGES['Dwarf'] with Death Warden Dwarf (previously missing) and enriched all five heritage entries with 'special' mechanic blocks covering magic save bonuses, necromancy crit-fail upgrade, heat resistance, bulk size bonus, and poison save upgrade. Added 'special' blocks to all six ANCESTRY_FEATS['Dwarf'] entries, including flat_footed_stone_immunity on Rock Runner, weapon_proficiency maps on Dwarven Weapon Familiarity, target_type_selection on Vengeful Hatred, perception_bonus_stonework on Stonecunning, and armor_speed_penalty reduction on Unburdened Iron. PHP lint passed; site HTTP 200; DB has 77 Dwarf requirement rows.

## Changes made

### forseti.life
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php`
  - ANCESTRIES['Dwarf']: added `'Free'` boost, `bonus_language_pool`, `bonus_language_source`, `starting_equipment`
  - HERITAGES['Dwarf']: added Death Warden Dwarf; enriched Ancient-Blooded, Forge, Rock, Strong-Blooded with `'special'` blocks
  - ANCESTRY_FEATS['Dwarf']: enriched all 6 feats with `'special'` mechanic blocks
- Commit: `4edab81d3` (115 insertions, 11 deletions)

### copilot-hq
- `features/dc-cr-dwarf-ancestry/feature.md` → Status: done
- Commit: `27b05d92b`

## Verification

| Check | Result |
|---|---|
| PHP lint | PASS |
| Site HTTP status | 200 |
| DB rows (dwarf) | 77 rows in dc_requirements |
| ANCESTRIES free boost | Added |
| ANCESTRIES bonus_language_pool | Added (6 languages) |
| HERITAGES Death Warden | Added |
| ANCESTRY_FEATS Rock Runner flat_footed_stone_immunity | Added |
| ANCESTRY_FEATS Vengeful Hatred target_type_selection | Added |

## Note on TC-DWF-08 (vision type)

The test plan (TC-DWF-08) references `low_light_vision` but PF2e CRB (Fourth Printing) grants Dwarves darkvision. The existing `'vision' => 'darkvision'` in ANCESTRIES['Dwarf'] is RAW-correct and was preserved. QA should flag TC-DWF-08 as a test plan error and verify against darkvision. If QA blocks on this discrepancy, it needs a PM ruling on which authority wins (test plan vs. rulebook).

## Next actions
- Await QA verification (qa-dungeoncrawler)
- QA will run TC-DWF-01 through TC-DWF-19 against the new data

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 35
- Rationale: Dwarf is one of the six core ancestries required for the character creation system. Completing it unlocks Dwarf as a fully playable ancestry. Heritage and feat detail directly determines QA pass rate for the 19-test suite.
