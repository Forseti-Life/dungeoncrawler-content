# Feature Brief: Halfling Heritage — Hillock Halfling

- Work item id: dc-cr-halfling-heritage-hillock
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 7684–7983
- Category: game-mechanic
- Release: (set by PM at activation)
- Created: 2026-04-12

## Goal

Implements the Hillock Halfling heritage: when the character regains HP overnight (long rest), they additionally regain HP equal to their level. When another character uses the Medicine skill to Treat Wounds for them, the hillock halfling can eat a snack to also add their level to the HP regained. This creates a durable, self-sustaining support-friendly archetype that rewards downtime and allied healer interactions.

## Source reference

> Hillock Halfling
> Accustomed to a calm life in the hills, your people find rest and relaxation especially replenishing, particularly when indulging in creature comforts. When you regain Hit Points overnight, add your level to the Hit Points regained. When anyone uses the Medicine skill to Treat your Wounds, you can eat a snack to add your level to the Hit Points you regain from their treatment.

## Implementation hint

- Long rest HP recovery handler: if character has Hillock Halfling heritage, add character level to total HP regained.
- Treat Wounds result handler: if patient has this heritage and triggers the "eat a snack" optional action (1 action?), add patient's level to HP recovered from the Medicine check.
- "Eat a snack" may be modeled as a free optional rider on the Treat Wounds outcome — simple boolean flag at resolution time.
- One of 6 halfling heritage options.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: rest and Treat Wounds resolution remain server-authoritative; snack rider is evaluated on the patient state only.
- CSRF expectations: all POST/PATCH healing and rest actions require `_csrf_request_header_mode: TRUE`.
- Input validation: bonus healing equals character level and applies only on overnight recovery or Treat Wounds; clients cannot inject arbitrary bonus values.
- PII/logging constraints: no PII logged; log character_id, recovery_source, base_healing, bonus_healing only.
