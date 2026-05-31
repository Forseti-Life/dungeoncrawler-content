# Feature Brief: Burrow Elocutionist (Gnome Ancestry Feat 1)

- Work item id: dc-cr-burrow-elocutionist
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260412-dungeoncrawler-release-i
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6784–7083
- Category: game-mechanic
- Created: 2026-04-09

## Goal

Gnome ancestry feat enabling comprehension of and communication with burrowing creatures (the "chittering of ground creatures" as its own language). Allows the character to ask questions and receive answers from burrowing animals. Extends the gnome's natural affinity for the First World into the mundane animal kingdom.

## Source reference

> You recognize the chittering of ground creatures as its own peculiar language. You can ask questions of, receive answers [from burrowing creatures]...

## Implementation hint

Ancestry feat that adds a special language/communication capability: can speak to and understand burrowing creatures (e.g., rats, moles, badgers). Requires the NPC/creature interaction system to support "burrowing creature" trait filter for this communication ability. Related to the broader creature-trait and language systems.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: communication effect uses existing NPC/creature interaction flows only; no new permission surface beyond character-owned actions.
- CSRF expectations: all POST/PATCH requests for dialogue or interaction actions require `_csrf_request_header_mode: TRUE`.
- Input validation: the communication unlock applies only to creatures tagged as burrowing creatures; clients cannot spoof trait matches.
- PII/logging constraints: no PII logged; log character_id, creature_id, communication_mode only.
