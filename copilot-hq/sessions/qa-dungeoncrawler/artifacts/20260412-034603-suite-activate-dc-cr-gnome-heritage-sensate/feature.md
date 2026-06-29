# Feature Brief: Gnome Heritage — Sensate Gnome

- Work item id: dc-cr-gnome-heritage-sensate
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

Add the Sensate Gnome heritage, granting imprecise scent with a 30-foot range (doubled downwind, halved upwind) and a +2 circumstance bonus to Perception checks to locate undetected creatures within scent range. Feeds the senses/detection subsystem alongside darkvision and low-light vision.

## Source reference

> "You gain a special sense: imprecise scent with a range of 30 feet... The GM will usually double the range if you're downwind from the creature or halve the range if you're upwind. In addition, you gain a +2 circumstance bonus to Perception checks whenever you're trying to locate an undetected creature that is within the range of your scent."

## Implementation hint

Heritage node in `dungeoncrawler_content`. Adds imprecise scent sense with 30-ft base range. Dungeon/encounter engine must track wind direction (or approximate with a simple binary) to apply range modifier. Adds conditional +2 to Perception rolls flagged as "locate undetected creature" within scent range. Interacts with `dc-cr-darkvision` and `dc-cr-low-light-vision` sense framework.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access

## Security acceptance criteria
- Security AC exemption: static data content only — no new routes, no user input, no PII. Data added to CharacterManager.php constants.
