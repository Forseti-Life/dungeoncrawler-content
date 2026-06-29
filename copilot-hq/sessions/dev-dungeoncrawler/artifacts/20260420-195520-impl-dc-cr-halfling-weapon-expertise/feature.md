# Feature Brief: Halfling Weapon Expertise

- Work item id: dc-cr-halfling-weapon-expertise
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 7984–8283
- Category: game-mechanic
- Release: (set by PM at activation)
- Created: 2026-04-14

## Goal

Implements Halfling Weapon Expertise (Halfling Feat 13, prereq: Halfling Weapon Familiarity): whenever the character's class grants them expert or greater proficiency in a weapon group, they also automatically gain that proficiency rank in the sling, halfling sling staff, shortsword, and all halfling weapons they are trained in. This cascades class weapon proficiency improvements onto the halfling weapon set, rewarding players who invested in the Halfling Weapon Familiarity chain.

## Source reference

> Halfling Weapon Expertise (Feat 13, Halfling, Prerequisites Halfling Weapon Familiarity)
> Your halfling affinity blends with your class training, granting you great skill with halfling weapons. Whenever you gain a class feature that grants you expert or greater proficiency in a given weapon or weapons, you also gain that proficiency in the sling, halfling sling staff, shortsword, and all halfling weapons in which you are trained.

## Implementation hint

- Proficiency cascade hook: when a class feature grants expert/master/legendary proficiency in weapons, check if character has this feat; if yes, also apply that proficiency level to sling, halfling sling staff, shortsword, and all halfling-tagged weapons where character is at least Trained.
- Similar pattern to dc-cr-gnome-weapon-expertise (gnome chain) — reuse proficiency cascade logic.
- Halfling weapon chain: dc-cr-halfling-weapon-familiarity → dc-cr-halfling-weapon-expertise (Feats 1 and 13). Note: dc-cr-halfling-weapon-familiarity is not yet stubbed as a prerequisite.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access
## Security acceptance criteria

- Security AC exemption: Pure rules reference content; no new routes, no user input, no data processing

