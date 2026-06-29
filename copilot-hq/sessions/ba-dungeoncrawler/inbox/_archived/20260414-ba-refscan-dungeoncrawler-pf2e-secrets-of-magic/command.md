# Reference Document Scan — PF2E Secrets of Magic

**Site:** dungeoncrawler  
**Next release:** 20260412-dungeoncrawler-release-n  
**Book:** PF2E Secrets of Magic (rulebook)  
**Progress:** start from `books[6].last_line + 1` in `tmp/ba-scan-progress/dungeoncrawler.json`  
**Progress state file:** `tmp/ba-scan-progress/dungeoncrawler.json`  
**Book entry:** `books[6]`  

## Your task

Read the source material and extract implementable Dungeoncrawler features, especially magic systems, classes, archetypes, spells, rituals, items, and subsystems that can be built into gameplay.

The outlines and generated inventories are not sufficient by themselves. Because the manuals appear only partially vetted, confirm each mechanic against the source text before creating a feature stub.

Skip pure setting lore unless it introduces an implementable spell, subsystem, item, or class mechanic.

## Creating a feature stub

Create `features/dc-som-<descriptor>/feature.md` using the standard Dungeoncrawler feature stub format with source `PF2E Secrets of Magic`.

## After generating features

1. Update `tmp/ba-scan-progress/dungeoncrawler.json` for `books[6]`.
2. Write an outbox listing created features, lines covered, notable skips, and PM-relevant follow-ups.

## Source aids

- Outline: `docs/dungeoncrawler/reference documentation/outlines/PF2E_Secrets_of_Magic_OUTLINE.md`
- Source text: `docs/dungeoncrawler/reference documentation/PF2E Secrets of Magic.txt`
- Spell inventory (orientation only): `docs/dungeoncrawler/reference documentation/comprehensive_spell_inventory_filtered.json`
- Item inventory (orientation only): `docs/dungeoncrawler/reference documentation/comprehensive_item_inventory.json`
- Agent: ba-dungeoncrawler
- Status: pending
