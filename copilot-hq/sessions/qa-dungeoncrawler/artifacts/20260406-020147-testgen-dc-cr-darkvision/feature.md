# Feature Brief: Darkvision Sense

- Work item id: dc-cr-darkvision
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: medium
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 5584–5883
- Category: rule-system
- Schema changes: no
- Cross-site modules: none
- Created: 2026-04-05

## Goal

Implement Darkvision as a reusable character sense. Characters with Darkvision see in darkness and dim light as well as in bright light, except vision in darkness is black and white (no color). This affects encounter and exploration visibility checks: a Darkvision character ignores the Concealed condition from darkness/dim light and is not flat-footed in those conditions. Darkvision is shared across multiple ancestries (dwarves, goblins, and others), so it must be a standalone sense entity referenced from ancestry data rather than duplicated per ancestry.

## Source reference

> "You can see in darkness and dim light just as well as you can see in bright light, though your vision in darkness is in black and white."

## Implementation hint

Create a `sense` entity: `id: darkvision`, `type: vision`, `effect: no_concealment_from_darkness, no_flat_footed_from_darkness, black_and_white_in_darkness`. Ancestry data model references senses by id (e.g., `dwarf.senses: [darkvision]`). The visibility/lighting system must check character senses before applying the Concealed condition or flat-footed from low light. Distinct from Low-Light Vision (sees in dim light as bright, still blind in darkness) — both should be separate sense entities. No dependency on a specific ancestry; this is a shared mechanic.

## Acceptance Criteria

See `features/dc-cr-darkvision/01-acceptance-criteria.md`.

## Latest updates

- 2026-04-06: Accepted at triage — standalone sense entity, no blocking deps. Status: planned, Priority: medium. AC written. Handed off to QA for test generation.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
