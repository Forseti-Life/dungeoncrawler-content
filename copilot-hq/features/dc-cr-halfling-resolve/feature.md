# Feature Brief: Halfling Resolve

- Work item id: dc-cr-halfling-resolve
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 7984–8283
- Category: game-mechanic
- Release: 20260412-dungeoncrawler-release-s
- Created: 2026-04-14

## Goal

Implements the Halfling Resolve ancestry feat (Feat 9): when a halfling rolls a success on a saving throw against an emotion effect, the result is upgraded to a critical success. Additionally, if the halfling has the Gutsy Halfling heritage, rolling a critical failure on an emotion saving throw is instead treated as a failure (removing dying/incapacitated outcomes from bad luck against fear/dread). This makes higher-level halflings significantly more resilient to emotion-based magic and fear effects.

## Source reference

> You are easily able to ward off attempts to play on your fears and emotions. When you roll a success on a saving throw against an emotion effect, you get a critical success instead. If your heritage is gutsy halfling, when you roll a critical failure on a saving throw against an emotion effect, you get a failure instead.

## Implementation hint

- Saving throw post-resolution hook: if character has this feat and saving throw trait includes "emotion," upgrade success → critical success.
- If character also has dc-cr-halfling-heritage-gutsy: additionally downgrade critical failure → failure on emotion saves.
- Requires trait tagging on effects (emotion) so the conditional can be evaluated at resolution time.
- Companion feat to dc-cr-halfling-heritage-gutsy (Gutsy adds its own success upgrade at heritage level; Resolve adds the crfail→fail pathway when combined).

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access
## Security acceptance criteria

- Security AC exemption: Pure rules reference content; no new routes, no user input, no data processing

