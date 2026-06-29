# Feature Brief: Conditions System

- Work item id: dc-cr-conditions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 
- Priority: P1 (combat dependency; ConditionManager partial impl exists)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Goal

Implement the full conditions system that tracks beneficial and harmful states applied to characters and creatures (e.g., dying, unconscious, frightened, flat-footed, grabbed, slowed, stunned, enfeebled, clumsy, drained, sickened). Each condition has a defined mechanical effect and rules for removal. Conditions are applied by spells, abilities, and hazards, and are central to tactical play.

## Source reference

> "Read up on conditions, from dying to slowed to frightened. These rules cover common benefits and drawbacks that typically come from spells or special actions." (Conditions Appendix)

## Implementation hint

Content type: `condition` with fields for name, value (for valued conditions like frightened 1–4), effects (stat modifiers, action restrictions), and end trigger (end of turn, save, action). Character/creature entity: `conditions[]` list. Conditions engine: apply on trigger, modify stat calculations, process removals at end of turn or on relevant events. Dying condition drives the death/recovery rules.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; condition mutations require character or encounter ownership
- CSRF expectations: all POST/PATCH condition routes require `_csrf_request_header_mode: TRUE`
- Input validation: condition keys validated against allowed enum; valued conditions (frightened 1–4) validated as integers within range
- PII/logging constraints: no PII logged; character id + condition key + value only
