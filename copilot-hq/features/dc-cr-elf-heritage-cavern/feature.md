# Feature Brief: Cavern Elf Heritage

- Work item id: dc-cr-elf-heritage-cavern
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-elf-ancestry, dc-cr-heritage-system, dc-cr-darkvision
- Source: PF2E Core Rulebook (Fourth Printing), lines 6155–6159
- Category: game-mechanic
- Schema changes: no
- Cross-site modules: none
- Release: 
- Created: 2026-04-06

## Goal

Implement the Cavern Elf heritage granting darkvision in place of the standard elf low-light vision. Elves who were born or spent many years in underground tunnels and caverns replace their low-light vision with the full darkvision sense (see in darkness as dim light, in black-and-white). This is the underground elf variant that overlaps mechanically with dwarven darkvision.

## Source reference

> Cavern Elf — You were born or spent many years in underground tunnels or caverns where light is scarce. You gain darkvision.

## Implementation hint

When this heritage is selected, override the elf ancestry default sense: set `low_light_vision: false` and `darkvision: true` on the character. Both sense flags should not be active simultaneously — the heritage replaces rather than adds. Reuses the same darkvision rule-system already implemented for dwarves (`dc-cr-darkvision`).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- All write endpoints (POST/PATCH heritage/ancestry assignment) require `_csrf_request_header_mode: TRUE`.
- All read endpoints (GET sense flags, ancestry data) use `_csrf_token: FALSE`.
- Anonymous users receive 403 on all character write paths.
- Character data is scoped to the owning user's session; no cross-character data exposure.
