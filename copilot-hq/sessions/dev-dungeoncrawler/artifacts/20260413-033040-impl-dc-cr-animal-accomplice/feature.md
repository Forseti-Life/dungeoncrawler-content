# Feature Brief: Animal Accomplice (Gnome Ancestry Feat 1)

- Work item id: dc-cr-animal-accomplice
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 20260412-dungeoncrawler-release-h
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6784–7083
- Category: game-mechanic
- Created: 2026-04-09

## Goal

Gnome-specific ancestry feat that grants the character a bonded animal familiar (using the Familiar rules). Distinguishes gnome familiars as magically bonded animals chosen by the player; gnomes typically prefer animals with burrow Speed. Unlocks the familiar system for gnome characters without requiring a spellcasting class.

## Source reference

> You build a rapport with an animal, which becomes magically bonded to you. You gain a familiar using the rules on page 217. The type of animal is up to you, but most gnomes choose animals with a burrow Speed.

## Implementation hint

Ancestry feat that invokes the familiar grant flow (dc-cr-familiar) for non-spellcasting characters. Requires the familiar system to be accessible independently of class. Flavor note: defaults to recommending burrow-Speed animals in the character creation UI, but any familiar type should be allowed.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: familiar grant and selection are character-scoped writes only; familiar ownership is enforced server-side.
- CSRF expectations: all POST/PATCH requests in feat-selection and familiar-management flows require `_csrf_request_header_mode: TRUE`.
- Input validation: granted familiar types must come from the valid familiar catalog; recommendation for burrow-speed animals is informational only and cannot bypass catalog rules.
- PII/logging constraints: no PII logged; log character_id, feat_id, familiar_type only.
