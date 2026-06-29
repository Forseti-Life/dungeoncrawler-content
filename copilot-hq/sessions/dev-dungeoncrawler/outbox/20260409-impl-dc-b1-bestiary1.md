# Outbox: dc-b1-bestiary1 — Bestiary 1 Creature Stat Blocks

- Status: done
- Summary: Implemented the full Bestiary 1 creature catalog by adding a `CREATURES` constant to `CharacterManager.php` containing 34 creature stat blocks across levels 0–8. All creature IDs match the `EncounterBalancer::getFallbackCreatures()` catalog for cross-reference in encounter generation. Each stat block includes id, name, level, rarity, size, traits, role, perception, senses, languages, skills, ac, saves (fort/ref/will), hp, immunities, weaknesses, resistances, speeds, attacks (with traits and effects), abilities, and xp_award. PHP lint passes clean. Feature `dc-b1-bestiary1` marked done.

## Commits
- `96fd33c7a` — `feat(dungeoncrawler): add CREATURES constant with Bestiary 1 stat blocks` (forseti.life repo)
- `b04b7f921` — `feat(dc-b1-bestiary1): mark feature done` (copilot-hq repo)

## Creatures implemented

| Level | Creatures |
|---|---|
| 0 | kobold_scout, giant_rat, zombie_shambler, vine_lasher, giant_bat, giant_centipede |
| 1 | goblin_warrior, goblin_pyro, skeleton_guard, giant_spider, cave_scorpion |
| 2 | orc_brute, ghoul, darkmantle, animated_statue, dretch, quasit, cave_fisher, drow_fighter |
| 3 | hobgoblin_soldier, ogre, cave_bear, shadow, rust_monster |
| 4 | wight |
| 5 | troll, basilisk, guardian_naga |
| 6 | wraith, mummy_guardian, babau, xorn, drider |
| 7 | vampire_spawn |
| 8 | stone_golem, roper, umber_hulk, drow_priestess |

## Verification
```bash
grep -c "xp_award" /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php
# Expected: 34
php -l /home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php
# Expected: No syntax errors
```

## Rollback
```bash
git -C /home/ubuntu/forseti.life revert 96fd33c7a
```

## Next actions
- QA: verify encounter generation endpoints return creatures from CREATURES catalog
- Follow-on: L9+ creatures (vrock, hezrou, glabrezu, shield_guardian, mind_flayer, beholder, lich, purple_worm, iron_golem) deferred per scope; dispatch separate inbox item if needed

## Blockers
- None

## KB reference
- None found for this specific pattern; PF2e class implementation pattern used (same file, same constant structure)

## ROI estimate
- ROI: 35
- Rationale: Encounter generation is the core GM feature of DungeonCrawler; without a creature catalog, the encounter balancer can only produce level/ID pairs with no stat data. These 34 stat blocks cover the most-used dungeon/cave/underdark themes and unlock the encounter preview and GM prep surfaces.
