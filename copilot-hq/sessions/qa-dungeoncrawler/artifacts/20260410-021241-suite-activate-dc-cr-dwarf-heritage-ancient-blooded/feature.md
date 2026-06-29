# Feature Brief: Dwarf Heritage — Ancient-Blooded

- Work item id: dc-cr-dwarf-heritage-ancient-blooded
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 20260406-dungeoncrawler-release-b
- Priority: P3 (depends on dc-cr-heritage-system and dc-cr-dwarf-ancestry, neither yet shipped; deferred to next cycle)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 5284–5583
- Category: game-mechanic
- Created: 2026-02-28

## Goal

Implement the Ancient-Blooded heritage for the Dwarf ancestry. When selected, this heritage grants the **Call on Ancient Blood** reaction: when the character attempts a saving throw against a magical effect (before rolling), they gain a +1 circumstance bonus to saving throws until the end of that turn (including the triggering save). This represents dwarven heroes' innate resistance to magic passed down through bloodlines. This is the first specific heritage implementation; it validates the heritage system data model.

## Source reference

> "Dwarven heroes of old could shrug off their enemies' magic, and some of that resistance manifests in you. You gain the Call on Ancient Blood reaction."
> "CALL ON ANCIENT BLOOD [reaction] — Trigger: You attempt a saving throw against a magical effect, but you haven't rolled yet. Your ancestors' innate resistance to magic surges, before slowly ebbing down. You gain a +1 circumstance bonus until the end of this turn. This bonus also applies to the triggering save."

## Implementation hint

Create a `heritage` entity: `id: ancient-blooded-dwarf`, `parent_ancestry: dwarf`, `granted_abilities: [call-on-ancient-blood-reaction]`. Create a `reaction` action entity: `id: call-on-ancient-blood`, `trigger: saving_throw_before_roll + magical_effect`, `effect: +1 circumstance bonus to saving throws until end of turn (including trigger)`. The AI combat engine must recognize circumstance bonuses on saving throws and trigger the reaction prompt when the condition is met. Depends on dc-cr-dwarf-ancestry, dc-cr-heritage-system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Heritage selection POST route (`/dungeoncrawler/character/{id}/heritage`) requires `_csrf_request_header_mode: TRUE`.
- Heritage assignment validates authenticated user owns the character (no cross-character mutation).
- Anonymous users receive 403 on all heritage write paths.
- Heritage ID validated server-side against permitted list for the ancestry before application.
