# Feature Brief: General Feats

- Work item id: dc-cr-general-feats
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: 20260228-dungeoncrawler-release-next focuses on core MVP (dice, DC, encounter, conditions, character creation, class, background, skill, equipment); this feature is secondary priority and will be re-evaluated next grooming cycle.
- Consolidated into: dc-cr-feats-ch05 (requirements covered in that feature's acceptance criteria)
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: game-mechanic
- Created: 2026-02-26

## Goal

Provide the catalog of general feats that any character can select at levels 3, 7, 11, 15, and 19 regardless of class. General feats improve statistics or grant new actions (e.g., Armor Proficiency, Shield Block, Toughness, Incredible Initiative). This layer of cross-class customization lets players fine-tune any character build.

## Source reference

> "Expand your capabilities by selecting general feats that improve your statistics or give you new actions." (Chapter 5: Feats)

## Implementation hint

Content type: `feat` with fields for feat type (general/skill/class/ancestry), level requirement, prerequisites, benefits, and any granted actions/reactions. Feat selection UI must filter by feat type = general and check prerequisites against current character stats. Character leveling endpoint must offer general feat choices at correct levels.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
