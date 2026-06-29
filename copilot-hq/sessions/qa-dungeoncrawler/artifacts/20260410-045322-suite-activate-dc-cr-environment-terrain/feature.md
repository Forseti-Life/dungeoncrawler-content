# Feature Brief: Environment and Terrain System

- Work item id: dc-cr-environment-terrain
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch10
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: core/ch10/Environment
- Depends on: dc-cr-encounter-rules, dc-cr-skill-system

## Goal

Implement environment and terrain rules — difficult terrain movement costs, greater difficult terrain, hazardous terrain damage on entry, flanking detection, and terrain type definitions — as the spatial modifier layer for encounter and exploration phases.

## Source reference

> "Difficult terrain costs 1 extra foot of movement for every foot moved; greater difficult terrain costs 2 extra feet per foot. Moving into a square of hazardous terrain deals damage as described."

## Implementation hint

Terrain type is stored on each grid cell in the `EncounterMap` entity as an enum (Normal/Difficult/GreaterDifficult/Hazardous/Impassable plus subtypes). The `MovementResolver` reads terrain costs and deducts from remaining movement points: difficult=×2 cost, greater difficult=×3 cost. Hazardous terrain triggers a `HazardousDamageEvent` on entry with damage type and amount from the terrain definition. Flanking is computed in `FlankingDetector`: check if two allied attackers occupy opposite sides of the target (diagonal counts); if so, apply the `flat-footed` condition to the target for those attacks.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; terrain maps set by GM; movement validation server-authoritative.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Grid coordinates must be within map bounds; terrain type must be a valid terrain enum; hazardous damage values loaded from server-side terrain definitions.
- PII/logging constraints: no PII logged; log character_id, movement_path, terrain_events_triggered; no PII logged.

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2350, 2351, 2352, 2353, 2354, 2355, 2356, 2357, 2358, 2359, 2360, 2361,
         2362, 2363, 2364, 2365, 2366, 2367, 2368, 2369, 2370, 2371, 2372
- See `runbooks/roadmap-audit.md` for audit process.
