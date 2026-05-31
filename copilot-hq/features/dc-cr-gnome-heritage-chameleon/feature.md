# Feature Brief: Gnome Heritage — Chameleon Gnome

- Work item id: dc-cr-gnome-heritage-chameleon
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6484–6783
- Category: game-mechanic
- Release: 20260412-dungeoncrawler-release-r

20260412-dungeoncrawler-release-r

Add the Chameleon Gnome heritage, granting mutable coloration and a +2 circumstance bonus to Stealth checks when the gnome is in terrain that roughly matches their current coloration. A single action allows minor color shifts; dramatic full-body shifts take up to an hour. This feeds the combat/exploration stealth system.

## Source reference

> "When you're in an area where your coloration is roughly similar to the environment (for instance, forest green in a forest), you can use the single action to make minor localized shifts designed to help you blend into your surroundings. This grants you a +2 circumstance bonus to Stealth checks until your surroundings shift in coloration or pattern."

## Implementation hint

Heritage node in `dungeoncrawler_content` linked to Gnome ancestry. Adds a conditional passive Stealth bonus: when character is in a terrain-tag-matched environment, apply +2 to Stealth rolls. Requires terrain-tag concept in dungeon/encounter data (forest, stone, shadow, etc.). AI GM prompt should describe color-shifting flavor text during Stealth attempts.

## Latest updates

- 2026-04-19: Surfaced Chameleon Gnome actions in the live handler APIs so `EncounterPhaseHandler::getAvailableActions()` exposes `minor_color_shift` on the actor's turn for Chameleon Gnomes and `DowntimePhaseHandler::getAvailableActions()` exposes `dramatic_color_shift` during downtime.
- 2026-04-19: Hardened Chameleon Gnome behavior coverage with unit tests for `minor_color_shift` and `dramatic_color_shift` processing, including coloration-state mutation, encounter action consumption, and non-Chameleon heritage rejection.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria
- Security AC exemption: static data content only — no new routes, no user input, no PII. Data added to CharacterManager.php constants.
- 2026-04-19 (session cont.): Added passive Stealth bonus behavior tests in EncounterPhaseHandlerChameleonStealthTest (4 tests/7 assertions): terrain-match applies bonus, terrain-mismatch blocks bonus, non-chameleon heritage blocked, circumstance stacking guard. Commit 535cad88e.
