# Feature Brief: Nature — Command an Animal

- Work item id: dc-cr-skills-nature-command-animal
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260410-dungeoncrawler-release-d
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Nature (Wis)
- Depends on: dc-cr-skill-system, dc-cr-npc-system

## Goal

Implement Nature (Wis) skill action handlers — Command an Animal, Identify Magic (primal), Recall Knowledge (Nature), and Treat Wounds — with animal companion integration and appropriate trained-only gating.

## Source reference

> "Command an Animal: You issue a command to an animal using Nature. Attempt a Nature check against the animal's Will DC; on a success, the animal follows your command until the end of its next turn."

## Implementation hint

`CommandAnAnimalAction` rolls Nature vs the animal's Will DC; on success the animal entity executes a specified action (Move/Strike/Aid) on its next turn as directed. For animal companions, this action is replaced by the companion's built-in action economy (free command once per turn). `IdentifyMagicNature` resolves against primal spell/item DCs only; cross-check the item/spell tradition and reject non-primal targets with an appropriate error. `TreatWoundsNature` is identical in mechanics to Medicine's Treat Wounds but uses the Nature modifier; both route through `TreatWoundsService` with the modifier as a parameter.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; animal companion commands only valid for the owning character; Treat Wounds healing is server-computed.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Animal command target must be a valid animal entity; command action must be from [Move, Strike, Aid, Other]; Treat Wounds target must be a living entity with less than max HP.
- PII/logging constraints: no PII logged; log character_id, action_type, target_id, outcome; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1705, 1706, 1707, 1708, 1709, 1710, 1711, 1712, 1713, 1714
- See `runbooks/roadmap-audit.md` for audit process.
