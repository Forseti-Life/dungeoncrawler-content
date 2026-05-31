# Feature Brief: Halfling Feat — Ceaseless Shadows

- Work item id: dc-cr-ceaseless-shadows
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

Implements Ceaseless Shadows (Halfling Feat 13, prereq: Distracting Shadows): the character no longer requires cover or concealment as a precondition for the Hide or Sneak actions. In addition, creature bodies grant upgraded cover — if creatures would give the character lesser cover, they instead gain full cover and can Take Cover; if full cover already, this becomes greater cover. This feat transforms halflings into master ambush predators who can vanish in a crowd without relying on terrain.

## Source reference

> Ceaseless Shadows (Feat 13, Halfling, Prerequisites Distracting Shadows)
> You excel at going unnoticed, especially among a crowd. You no longer need to have cover or be concealed to Hide or Sneak. If you would have lesser cover from creatures, you gain cover and can Take Cover, and if you would have cover from creatures, you gain greater cover.

## Implementation hint

- Override Hide/Sneak prerequisites: remove cover/concealment check for characters with this feat.
- Creature-based cover upgrade: in cover calculation, if source is creatures and character has this feat, upgrade lesser cover → cover and cover → greater cover.
- Requires dc-cr-halfling-very-sneaky (implied prereq chain through Distracting Shadows, which is a mid-level feat not yet stubbed).
- This is a Feat 13 — high-level feature; may be deferred until character progression system reaches level 13.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access
## Security acceptance criteria

- Security AC exemption: Pure rules reference content; no new routes, no user input, no data processing

