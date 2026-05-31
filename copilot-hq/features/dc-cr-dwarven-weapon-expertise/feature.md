# Feature Brief: Dwarven Weapon Expertise

- Work item id: dc-cr-dwarven-weapon-expertise
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-dwarven-weapon-familiarity, dc-cr-equipment-system
- Source: PF2E Core Rulebook (Fourth Printing), lines 5884–5890
- Category: game-mechanic
- Schema changes: no
- Cross-site modules: none
- Release: (set by PM at activation)
- Created: 2026-04-06

## Goal

Implement Dwarven Weapon Expertise as a 13th-level dwarf ancestry feat. When a character gains a class feature that grants expert or greater proficiency in certain weapons, they also gain that proficiency for battle axes, picks, warhammers, and all other dwarven weapons in which they are trained. This feat rewards long-term dwarf builds by extending weapon-expertise class features into the full dwarven weapon category.

## Source reference

> Dwarven Weapon Expertise (Feat 13, Dwarf) — Prerequisites: Dwarven Weapon Familiarity. Your dwarven affinity blends with your training, granting you great skill with dwarven weapons. Whenever you gain a class feature that grants you expert or greater proficiency in certain weapons, you also gain that proficiency for battle axes, picks, warhammers, and all dwarven weapons in which you are trained.

## Implementation hint

Store as a dwarf ancestry feat at level 13 in the dungeoncrawler_content ancestry feat catalog. At feat application time, iterate over the character's weapon proficiency table: for each weapon category where the class feature granted expert+, also apply that rank to battle axe, pick, warhammer, and any dwarf-tagged weapons already in the character's trained set. Requires `dc-cr-dwarven-weapon-familiarity` to be resolved first (provides the trained dwarven weapon list).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
