# Feature Brief: Polyhedral Dice Engine

- Work item id: dc-cr-dice-system
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P0 (foundational — every resolution system depends on this)
- Release: 
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 301–600
- Category: rule-system
- Created: 2026-02-27

## Goal

Implement a virtual polyhedral dice engine supporting all die types used by PF2E: d4, d6, d8, d10, d12, d20, and d% (percentile, 1–100 via two d10s). Every attack roll, skill check, damage roll, save, and initiative roll in the dungeoncrawler game resolves through this engine. It must be a single canonical service so results are deterministic, auditable, and can be logged for replay/debug.

## Source reference

> "Pathfinder requires a set of polyhedral dice. Each die has a different number of sides—four, six, eight, or more. When these dice are mentioned in the text, they're indicated by a 'd' followed by the number of sides on the die. Pathfinder uses 4-sided dice (or d4), 6-sided dice (d6), 8-sided dice (d8), 10-sided dice (d10), 12-sided dice (d12), and 20-sided dice (d20). If you need to roll multiple dice, a number before the 'd' tells you how many. For example, '4d6' means you should roll four dice, all 6-sided. If a rule asks for d%, you generate a number from 1 to 100 by rolling two 10-sided dice, treating one as the tens place and the other as the ones place."

## Implementation hint

Service/API endpoint: `POST /dice/roll` accepting `expression` (e.g., `"4d6"`, `"1d20+5"`, `"d%"`) and returning individual die results plus total. Supports NdX notation (N dice of X sides) plus flat modifiers. Must handle keep-highest/keep-lowest for ability score generation if needed. Log each roll with timestamp and context (character id, roll type) for session replay. Used by every resolution system (attack, skill check, damage, save, initiative).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
