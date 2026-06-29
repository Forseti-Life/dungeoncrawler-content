# Feature Brief: Ritual Magic System

- Work item id: dc-cr-rituals
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: 20260228-dungeoncrawler-release-next focuses on core MVP (dice, DC, encounter, conditions, character creation, class, background, skill, equipment); this feature is secondary priority and will be re-evaluated next grooming cycle.
- Consolidated into: dc-cr-spells-ch07 (requirements covered in that feature's acceptance criteria)
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Goal

Implement rituals as a distinct form of magic separate from standard spellcasting: they require extended casting time (minutes to days), do not consume spell slots, require skill checks (primary and secondary casters), and have meaningful failure/critical-failure consequences. Rituals enable narrative-scale magic (planar travel, resurrection, commune) that changes the course of a dungeoncrawler campaign.

## Source reference

> "This section includes the rules for spellcasting, hundreds of spell descriptions, focus spells used by certain classes, and rituals." (Chapter 7: Spells)

## Implementation hint

Content type: `ritual` with fields for spell level, cast time, primary check (skill + DC), secondary check (skill + DC), cost (material components), and outcomes (critical success / success / failure / critical failure). Ritual resolution API: roll primary + secondary caster checks, look up outcome, apply effect. Separate from standard `spell` type.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
