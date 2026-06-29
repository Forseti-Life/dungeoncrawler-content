# Reference Document Scan — PF2E Gamemastery Guide

**Site:** dungeoncrawler  
**Next release:** 20260412-dungeoncrawler-release-n  
**Book:** PF2E Gamemastery Guide (rulebook)  
**Progress:** start from `books[2].last_line + 1` in `tmp/ba-scan-progress/dungeoncrawler.json`  
**Progress state file:** `tmp/ba-scan-progress/dungeoncrawler.json`  
**Book entry:** `books[2]`  

## Your task

Read the source material and extract implementable Dungeoncrawler features, especially GM tools, encounter systems, hazards, subsystems, NPC utilities, and reusable gameplay rules.

Outlines and generated inventories are aids only. Most manuals appear not fully vetted, so verify mechanics against the source text before creating stubs.

Create feature stubs for relevant mechanics not already represented in `features/`. Skip pure lore, narrative examples, credits, and duplicate material already captured from the Core Rulebook unless the GMG adds a new implementable mechanic.

## Creating a feature stub

Create `features/dc-gmg-<descriptor>/feature.md` using the standard Dungeoncrawler feature stub format with source `PF2E Gamemastery Guide`.

## After generating features

1. Update `tmp/ba-scan-progress/dungeoncrawler.json` for `books[2]`:
   - advance `last_line`
   - set `status` to `in_progress` or `complete`
2. Write an outbox listing created features, lines covered, notable skips, and PM-relevant recommendations.

## Source aids

- Outline: `docs/dungeoncrawler/reference documentation/outlines/PF2E_Gamemastery_Guide_OUTLINE.md`
- Source text: `docs/dungeoncrawler/reference documentation/PF2E Gamemastery Guide.txt`
- Item inventory (orientation only): `docs/dungeoncrawler/reference documentation/comprehensive_item_inventory.json`
- Agent: ba-dungeoncrawler
- Status: pending
