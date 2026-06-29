# Feature Brief: Crafting Skill Actions

- Work item id: dc-cr-skills-crafting-actions
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Crafting (Int)
- Depends on: dc-cr-skill-system, dc-cr-equipment-ch06, dc-cr-economy

## Description
Implement Crafting skill action handlers (REQs 1644–1656, 2325) in
DowntimePhaseHandler. Currently Repair/Craft/Identify Alchemy are stubs only.

**Craft** (downtime, trained): item level ≤ character level; DC = item level
via Table 10-5; rarity adjustment; spend 4 days + raw materials (half item price);
degree outcomes (crit=finish + extra materials refunded, success=finish, fail=progress
only, crit fail=waste half materials).

**Repair** (trained, 1 hr, tools required): restore HP to item;
DC = 15+item_level; degrees affect HP restored.

**Identify Alchemy** (trained, 1 action, alchemist's tools): DC = item level via
Table 10-5 with rarity adjustment.

**Craft DC** (REQ 2325): item level → Table 10-5; rarity adjustment applied.
Coordinates with dc-cr-dc-rarity-spell-adjustment for DC calculations.

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1644 (partial), 1645–1656 (Craft/Repair/Identify Alchemy actions), 2325
- See `runbooks/roadmap-audit.md` for audit process.
