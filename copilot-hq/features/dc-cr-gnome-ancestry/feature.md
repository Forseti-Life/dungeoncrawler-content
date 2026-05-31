# Feature Brief: Gnome Ancestry

- Work item id: dc-cr-gnome-ancestry
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6484–6783
- Category: game-mechanic
- Release:

20260409-dungeoncrawler-release-e

Add the Gnome as a playable ancestry in the character creation system. Gnomes are Small humanoids with 8 HP, Speed 25, ability boosts to Constitution and Charisma, an ability flaw to Strength, Low-Light Vision, and access to Common, Gnomish, and Sylvan languages plus additional languages based on Intelligence modifier. Selecting Gnome at character creation unlocks the five gnome heritages and gnome ancestry feats.

## Source reference

> Hit Points: 8 / Size: Small / Speed: 25 feet / Ability Boosts: Constitution, Charisma, Free / Ability Flaw: Strength / Languages: Common, Gnomish, Sylvan (plus Intelligence modifier additional languages) / Senses: Low-Light Vision / Traits: Gnome, Humanoid

## Implementation hint

New Ancestry content type node in `dungeoncrawler_content`. Fields: HP, size, speed, ability boosts/flaws, base languages, sense type. Feeds the character creation flow alongside Dwarf and Elf. AI prompt must recognize "gnome" as a valid ancestry and apply correct stat modifiers at character creation.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access

## Security acceptance criteria
- Security AC exemption: static data content only — no new routes, no user input, no PII. Data added to CharacterManager.php constants.
