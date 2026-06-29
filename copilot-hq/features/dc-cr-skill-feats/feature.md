# Feature Brief: Skill Feats

- Work item id: dc-cr-skill-feats
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: 20260228-dungeoncrawler-release-next focuses on core MVP (dice, DC, encounter, conditions, character creation, class, background, skill, equipment); this feature is secondary priority and will be re-evaluated next grooming cycle.
- Consolidated into: dc-cr-feats-ch05 (requirements covered in that feature's acceptance criteria)
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: game-mechanic
- Created: 2026-02-26

## Goal

Provide the catalog of skill feats that expand what a character can do with each skill (e.g., Battle Medicine for Medicine, Kip Up for Acrobatics, Intimidating Glare for Intimidation). Skill feats are taken at even levels for most classes and require a specific skill proficiency as a prerequisite. They make skill investment feel meaningful and rewarding.

## Source reference

> "This chapter includes skill feats, which are tied directly to your skills." (Chapter 5: Feats)

## Implementation hint

Reuses the `feat` content type (type = skill) with a required skill and minimum proficiency rank as prerequisite fields. Skill feat selection UI must filter by trained skill and rank. Background system also grants one skill feat at character creation. Each of the 17 skills has multiple associated skill feats spanning levels 1–7.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
