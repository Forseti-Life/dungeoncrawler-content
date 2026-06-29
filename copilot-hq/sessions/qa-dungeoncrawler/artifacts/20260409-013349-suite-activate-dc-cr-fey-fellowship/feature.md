# Feature Brief: Fey Fellowship

- Work item id: dc-cr-fey-fellowship
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6484–6783
- Category: game-mechanic
- Release: (set by PM at activation)
- Created: 2026-04-08

## Goal

Add the Fey Fellowship gnome ancestry feat (Feat 1), granting +2 circumstance bonus to Perception checks and saving throws against fey creatures, and enabling immediate (1-action) Diplomacy attempts to Make an Impression on fey in social situations (normally takes 1 minute, with -5 penalty, retriable at no penalty with Glad-Hand). Feeds the social/diplomacy subsystem and creature-type awareness.

## Source reference

> "You gain a +2 circumstance bonus to both Perception checks and saving throws against fey. In addition, whenever you meet a fey creature in a social situation, you can immediately attempt a Diplomacy check to Make an Impression on that creature rather than needing to converse for 1 minute. You take a –5 penalty to the check. If you fail, you can engage in 1 minute of conversation and attempt a new check... Special: If you have the Glad-Hand skill feat, you don't take the penalty on your immediate Diplomacy check if the target is a fey."

## Implementation hint

Ancestry feat node in `dungeoncrawler_content` (Gnome, level 1). Adds two passives: (1) +2 vs fey creatures on Perception and saves — requires creature-type tag `fey` on encounter entities; (2) immediate Diplomacy shortcut in social encounter scenes. AI GM prompt should trigger social-shortcut path when fey creature is present and character has this feat. Interacts with social encounter system.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access

## Security acceptance criteria
- Security AC exemption: static data content only — no new routes, no user input, no PII. Data added to CharacterManager.php constants.
