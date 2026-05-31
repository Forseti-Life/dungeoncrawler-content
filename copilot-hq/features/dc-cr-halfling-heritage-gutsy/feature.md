# Feature Brief: Halfling Heritage — Gutsy Halfling

- Work item id: dc-cr-halfling-heritage-gutsy
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 7684–7983
- Category: game-mechanic
- Release: 20260412-dungeoncrawler-release-m
- Created: 2026-04-12

## Goal

Implements the Gutsy Halfling heritage: when a character with this heritage rolls a success on a saving throw against an emotion effect, the result is upgraded to a critical success. This provides a meaningful defensive niche against fear/emotion-based abilities and makes gutsy halflings resilient party members in encounters heavy with such effects.

## Source reference

> Gutsy Halfling
> Your family line is known for keeping a level head and staving off fear when the chips were down, making them wise leaders and sometimes even heroes. When you roll a success on a saving throw against an emotion effect, you get a critical success instead.

## Implementation hint

- Saving throw result handler: when character has this heritage and saves with Trait = emotion, and result = success, upgrade to critical success.
- Requires a trait tagging system on spells/effects (emotion tag) so the upgrade condition can be evaluated.
- One of 6 halfling heritage options; all share the dc-cr-halfling-ancestry + dc-cr-heritage-system prerequisites.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: heritage effects are passive and server-calculated within the existing save-resolution pipeline only.
- CSRF expectations: all POST/PATCH combat/save-resolution actions require `_csrf_request_header_mode: TRUE`.
- Input validation: success-to-critical-success upgrade applies only when the triggering effect carries the `emotion` trait and the character has Gutsy Halfling selected.
- PII/logging constraints: no PII logged; log character_id, save_type, effect_traits, save_result_before_after only.
