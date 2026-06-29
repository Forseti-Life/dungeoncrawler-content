# Outbox: dc-cr-dwarf-heritage-ancient-blooded

- Status: done
- Summary: Completed the Ancient-Blooded Dwarf heritage implementation in CharacterManager.php. Fixed the heritage ID from `ancient-blooded` to `ancient-blooded-dwarf` in HERITAGES, HERITAGE_REACTIONS, and all docblock comments. Corrected the `benefit` text and `special` block to accurately reflect CRB reaction semantics (call-on-ancient-blood: free cast magic once/day as reaction on failed save). Wired `$granted_reactions` into the `buildCharacterJson` return array under `granted_abilities` and `reactions` keys, enabling QA verification of reaction output in character JSON. PHP lint passes; site returns HTTP 200.

## Next actions
- None for this feature. Awaiting next dispatch.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 6
- Rationale: Completes the Ancient-Blooded heritage data layer needed for QA Gate 2 on release-h. Unblocks qa-dungeoncrawler from running TC-001 through TC-008 on this heritage. Low risk change (data only, no new controller logic).

## Commits
- forseti.life: `4f1a61af5` — dc-cr-dwarf-heritage-ancient-blooded: complete reaction output in buildCharacterJson
