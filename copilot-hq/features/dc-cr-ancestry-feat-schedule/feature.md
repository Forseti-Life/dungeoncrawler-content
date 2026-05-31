# Feature Brief: Ancestry Feat Schedule

- Work item id: dc-cr-ancestry-feat-schedule
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P3 (depends on dc-cr-character-leveling which is deferred; ancestry feat slots blocked until leveling system exists)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 5284–5583
- Category: game-mechanic
- Created: 2026-02-28

## Goal

Implement the ancestry feat progression schedule: characters gain their first ancestry feat at level 1, then additional ancestry feats at levels 5, 9, 13, and 17. At each selection point, the player may choose any ancestry feat of their character's level or lower (provided prerequisites are met). This is a required leveling milestone for all characters and must integrate with the character leveling system.

## Source reference

> "You gain your first ancestry feat at 1st level, and you gain another at 5th level, 9th level, 13th level, and 17th level, as indicated in the class advancement table in the descriptions of each class. Ancestry feats are organized by level. As a starting character, you can choose from only 1st-level ancestry feats, but later choices can be made from any feat of your level or lower. These feats also sometimes list prerequisites—requirements that your character must fulfill to select that feat."

## Implementation hint

Add `ancestry_feat` slots to the character entity at levels 1, 5, 9, 13, 17. The feat selection UI must filter to `feat_type: ancestry` and `parent_ancestry: <character.ancestry>`, and filter by `level <= character.level` with prerequisite checking. This integrates with dc-cr-character-leveling (level-up workflow) and the `feat` content type from dc-cr-general-feats. Per-ancestry feat catalogs (e.g., all dwarven ancestry feats) will be added as individual feats are scanned from the rulebook.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
