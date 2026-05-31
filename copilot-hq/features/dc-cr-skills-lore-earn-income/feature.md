# Feature Brief: Lore and Earn Income Skill Actions

- Work item id: dc-cr-skills-lore-earn-income
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260410-dungeoncrawler-release-d
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Lore (Int)
- Depends on: dc-cr-skill-system, dc-cr-economy, dc-cr-dc-rarity-spell-adjustment

## Goal

Implement Lore skill actions — Recall Knowledge (Lore-specific) and Earn Income (downtime) — as the primary mechanism for knowledge checks within a character's specific lore topic and the main downtime gold-earning activity.

## Source reference

> "Earn Income: You use a skill — most often Crafting, Lore, or Performance — to earn money during downtime. The DC and income earned are based on your level and the task difficulty."

## Implementation hint

Lore skills are dynamic sub-skills keyed by topic (e.g., "Academia Lore", "Underworld Lore"); store as `lore_skills[]` array on the character with individual proficiency ranks. `RecallKnowledgeLore` routes the check to the relevant lore topic modifier; the DC is set by creature level or item rarity table. `EarnIncomeAction` is a downtime activity; resolve with a Lore (or Crafting/Performance) check vs a level-keyed DC table, returning GP per day of downtime invested. Store earned income as a pending payout resolved at downtime end.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; Earn Income gold awards must be server-computed from the DC table, not client-submitted.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Lore topic must be a registered lore skill on the character; downtime days must be a positive integer; DC lookups use server-side level/rarity tables.
- PII/logging constraints: no PII logged; log character_id, lore_topic, dc_attempted, gp_earned; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1579, 1580, 1581, 1582, 1685, 1686, 1687, 2326
- See `runbooks/roadmap-audit.md` for audit process.
