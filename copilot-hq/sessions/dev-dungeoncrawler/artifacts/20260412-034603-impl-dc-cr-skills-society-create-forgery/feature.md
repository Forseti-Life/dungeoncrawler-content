# Feature Brief: Society — Create Forgery

- Work item id: dc-cr-skills-society-create-forgery
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Society (Int)
- Depends on: dc-cr-skill-system

## Goal

Implement Society (Int) skill action handlers — Create Forgery, Decipher Writing (mundane), Recall Knowledge (Society), and Subsist (settlements) — supporting social infiltration, downtime income, and urban navigation.

## Source reference

> "Create Forgery: You create a forged document. The GM rolls a secret Society check against the Perception DC of anyone who examines the document; only on a critical failure does the examiner detect the forgery."

## Implementation hint

`CreateForgeryAction` is a downtime activity; roll Society secretly (GM-side) and store the result as an opaque `ForgeryEntity` with the roll baked in; when an NPC examines the document, compare the stored roll vs that NPC's Perception DC server-side. `DecipherWritingSociety` handles mundane (non-magical) texts with Society; magical texts require Arcana/Nature/Occultism/Religion. `SubsistUrban` routes through shared `SubsistService` using Society in settlement contexts (vs Survival in wilderness). Recall Knowledge (Society) covers governments, people, and history; DC by subject rarity/level table.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; forgery check result is stored server-side and never exposed to the forger; examiner check resolved server-side.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Forgery subject must reference a valid document/NPC entity type; Subsist environment must be [urban, settlement]; DC lookups use server-side tables only.
- PII/logging constraints: no PII logged; log character_id, action_type, target_type, outcome; no PII logged; forgery roll result never logged to player-accessible log.

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1731, 1732, 1733, 1734, 1735, 1736, 1737, 1738
- See `runbooks/roadmap-audit.md` for audit process.
