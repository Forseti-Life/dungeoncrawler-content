# Feature Brief: Low-Light Vision

- Work item id: dc-cr-low-light-vision
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6140–6144
- Category: rule-system
- Schema changes: no
- Cross-site modules: none
- Release: (set by PM at activation)
- Created: 2026-04-06

## Goal

Implement Low-Light Vision as a shared sense rule-system usable by multiple ancestries (elves and others). Characters with low-light vision can see in dim light as though it were bright light, and therefore ignore the concealed condition caused by dim light. This is the elf default vision sense, analogous to how darkvision works for dwarves and underground ancestry variants.

## Source reference

> Low-Light Vision — You can see in dim light as though it were bright light, so you ignore the concealed condition due to dim light.

## Implementation hint

Implement as a character-level sense flag (`low_light_vision: true`) stored alongside the darkvision flag. During encounter lighting resolution, if the character has low-light vision, treat dim-light zones as bright light for concealment checks. This is the lighter-weight sibling of darkvision (no black-and-white darkness vision; only dim→bright upgrade). Multiple ancestry implementations (Elf, some heritages) will reference this shared rule.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- All write endpoints (POST/PATCH heritage/ancestry assignment) require `_csrf_request_header_mode: TRUE`.
- All read endpoints (GET sense flags, ancestry data) use `_csrf_token: FALSE`.
- Anonymous users receive 403 on all character write paths.
- Character data is scoped to the owning user's session; no cross-character data exposure.
