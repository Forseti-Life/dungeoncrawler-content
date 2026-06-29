# Acceptance Criteria — dc-cr-clan-dagger

- Feature: Clan Dagger (Dwarven Starting Equipment)
- Release target: 20260308-dungeoncrawler-release-b
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-03-08

## Scope

Implement the Clan Dagger as a special item unique to Dwarf characters. Every dwarf receives one free Clan Dagger at character creation, which bears their clan gemstone. Selling it violates a cultural taboo with in-game consequences.

## Prerequisites satisfied

- dc-cr-dwarf-ancestry: complete
- dc-cr-equipment-system: complete (ItemCombatDataService, weapons/armor/gear catalog)

## Knowledgebase check

None found for ancestry-granted items specifically. This is the prototype for the "ancestry starting equipment" pattern — the implementation establishes the data model for future ancestry-granted items.

## Happy Path

- [ ] `[NEW]` A `clan-dagger` item entity is defined in the item catalog with: damage `1d4`, damage type `piercing or slashing`, bulk `L`, traits `[Agile, Dwarf, Versatile S]`, level 0 (starting item), price 0 (free at creation).
- [ ] `[NEW]` When a character with Dwarf ancestry completes character creation, one Clan Dagger is automatically added to their inventory (no player selection required; it is always granted).
- [ ] `[NEW]` The Clan Dagger in inventory is tagged with `ancestry_granted: true` and `sell_taboo: true`.
- [ ] `[NEW]` Attempting to sell or trade away the Clan Dagger triggers an in-game consequence (at minimum: a warning/narrative note from the GM that this violates dwarven cultural norms; the sale is blocked or flagged for GM confirmation).
- [ ] `[NEW]` The Clan Dagger functions as a standard weapon in combat (can be wielded, attacks are resolved normally via dc-cr-encounter-rules).
- [ ] `[VERIFY]` The Agile trait means the Clan Dagger uses −4/−8 MAP (not the standard −5/−10).
- [ ] `[VERIFY]` The Versatile S trait means the Clan Dagger can deal slashing damage instead of piercing damage when the attacker chooses.

## Edge Cases

- [ ] `[NEW]` Only Dwarf characters receive the Clan Dagger automatically; non-dwarf characters cannot obtain one through normal creation flow.
- [ ] `[NEW]` Creating a second Dwarf character results in a new Clan Dagger for that character (not the same item instance as a prior character).
- [ ] `[NEW]` If the item is lost (not sold, but dropped in a dungeon for example), the sell taboo consequence does not apply.
- [ ] `[NEW]` The sell taboo check fires only when a character initiates an explicit sell or trade action targeting the Clan Dagger.

## Failure Modes

- [ ] `[NEW]` Attempting to duplicate a Clan Dagger (e.g., bypassing creation via admin API) should not grant two — the creation grants exactly one.
- [ ] `[NEW]` If the equipment catalog cannot be queried at creation time, creation proceeds but logs an error; the Clan Dagger can be granted in a follow-up step.

## Permissions / Access Control

- [ ] Item grants at creation are server-side; clients cannot bypass or skip the grant.
- [ ] The sell taboo check runs server-side; the client may display the warning but the block/flag is enforced on the server.
- [ ] GM may override the sell taboo for narrative reasons (admin-level bypass with explicit flag).

## Gameplay-rule alignment

- PF2e Source (Dwarf ancestry section): "You begin play with a clan dagger. If it's the dagger that was forged for you, you don't pay for it. Selling your clan dagger is a social taboo and shameful act."
- Traits: Agile (MAP −4/−8), Versatile S (can deal slashing), Dwarf (trait tag for future trait-based interactions).
- Level 0 item: treated as a starting item per PF2e gear rules; no rune slots at creation level.

## Test path guidance (for QA)

| Requirement | Test path |
|---|---|
| Auto-grant at creation | Create dwarf character; verify Clan Dagger in inventory |
| Non-dwarf no auto-grant | Create elf character; verify no Clan Dagger in inventory |
| Sell taboo trigger | Attempt sell of Clan Dagger; verify consequence response |
| Combat usage | Wield Clan Dagger; make attack; verify attack resolved using item stats |
| Agile MAP | Make 2nd attack with Clan Dagger; verify MAP = −4, not −5 |
| Versatile S | Request slashing damage; verify roll uses slashing type |
