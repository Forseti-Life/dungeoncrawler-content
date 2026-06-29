# Feature Brief: Gnome Heritage — Chameleon Gnome

- Work item id: dc-cr-gnome-heritage-chameleon
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6484–6783
- Category: game-mechanic
- Release:

## Goal

Add the Chameleon Gnome heritage, granting mutable coloration and a +2 circumstance bonus to Stealth checks when the gnome is in terrain that roughly matches their current coloration. A single action allows minor color shifts; dramatic full-body shifts take up to an hour. This feeds the combat/exploration stealth system.

## Source reference

> "When you're in an area where your coloration is roughly similar to the environment (for instance, forest green in a forest), you can use the single action to make minor localized shifts designed to help you blend into your surroundings. This grants you a +2 circumstance bonus to Stealth checks until your surroundings shift in coloration or pattern."

## Implementation hint

Heritage node in `dungeoncrawler_content` linked to Gnome ancestry. Adds a conditional passive Stealth bonus: when character is in a terrain-tag-matched environment, apply +2 to Stealth rolls. Requires terrain-tag concept in dungeon/encounter data (forest, stone, shadow, etc.). AI GM prompt should describe color-shifting flavor text during Stealth attempts.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access

## Security acceptance criteria
- Security AC exemption: static data content only — no new routes, no user input, no PII. Data added to CharacterManager.php constants.
