# Feature Brief: Gnome Heritage — Umbral Gnome

- Work item id: dc-cr-gnome-heritage-umbral
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6484–6783
- Category: game-mechanic
- Release:

## Goal

Add the Umbral Gnome heritage, granting darkvision (see in complete darkness with no penalty). Simple, high-value heritage that reuses the darkvision sense already stubbed for Dwarf and the existing `dc-cr-darkvision` feature. Cross-ancestry reuse confirms darkvision as a shared sense type in the character data model.

## Source reference

> "Whether from a connection to dark or shadowy fey, from the underground deep gnomes also known as svirfneblin, or another source, you can see in complete darkness. You gain darkvision."

## Implementation hint

Heritage node in `dungeoncrawler_content` linked to Gnome ancestry. Sets character sense field to `darkvision`. No new logic needed if `dc-cr-darkvision` is already implemented — just a data record linking this heritage to the darkvision sense type.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access

## Security acceptance criteria
- Security AC exemption: static data content only — no new routes, no user input, no PII. Data added to CharacterManager.php constants.
