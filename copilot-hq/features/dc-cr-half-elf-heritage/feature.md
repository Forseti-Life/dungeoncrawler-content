# Feature Brief: Half-Elf Heritage

- Work item id: dc-cr-half-elf-heritage
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-human-ancestry, dc-cr-heritage-system, dc-cr-low-light-vision, dc-cr-ancestry-feat-schedule
- Source: PF2E Core Rulebook (Fourth Printing), lines 8386-8388
- Category: game-mechanic
- Schema changes: no
- Cross-site modules: none
- Release: (set by PM at activation)
- Created: 2026-04-18

## Goal

Implement the Half-Elf heritage as a heritage overlay on the Human ancestry. This heritage grants the elf and half-elf traits, low-light vision, and expands the ancestry feat pool so the character can choose elf, half-elf, and human ancestry feats when gaining ancestry feat slots.

## Source reference

> "You gain the elf trait, the half-elf trait, and low-light vision. You can select elf, half-elf, and human feats whenever you gain an ancestry feat."

## Implementation hint

Model Half-Elf as a Human heritage record, not a separate ancestry. The heritage must augment the character's trait list and ancestry-feat eligibility rules at runtime. Feat-pool resolution should accept multiple ancestry tags so half-elf characters can browse and validate human, elf, and half-elf feat options in the same picker.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access
