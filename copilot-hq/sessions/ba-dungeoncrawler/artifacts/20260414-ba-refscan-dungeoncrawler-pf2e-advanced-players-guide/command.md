- Status: done
- Completed: 2026-04-16T16:55:06Z

# Reference Document Scan — PF2E Advanced Players Guide

**Site:** dungeoncrawler  
**Next release:** 20260412-dungeoncrawler-release-n  
**Book:** PF2E Advanced Players Guide (rulebook)  
**Progress:** start from `books[1].last_line + 1` in `tmp/ba-scan-progress/dungeoncrawler.json`  
**Progress state file:** `tmp/ba-scan-progress/dungeoncrawler.json`  
**Book entry:** `books[1]`  

## Your task

Read the source material and extract **implementable game features** for the Dungeoncrawler product.

The outlines and inventories in `docs/dungeoncrawler/reference documentation/` are helpful orientation aids, but **they are not authoritative by themselves**. Most manuals appear only partially vetted, so confirm each mechanic against the source text before creating a feature stub.

For each distinct mechanic, rule, ancestry option, class option, feat, spell, item, or subsystem described in the text:
1. Decide if it is relevant to Dungeoncrawler.
2. If relevant and not already implemented in `features/`, create a feature stub.
3. Skip pure lore, credits, typography, and duplicate mechanics already captured elsewhere.
4. Work a tractable chunk, then stop and write an outbox so the next scan can resume cleanly.

## Creating a feature stub

Create `features/dc-apg-<descriptor>/feature.md` with:

- Work item id: `dc-apg-<descriptor>`
- Website: `dungeoncrawler`
- Status: `pre-triage`
- PM owner: `pm-dungeoncrawler`
- Dev owner: `dev-dungeoncrawler`
- QA owner: `qa-dungeoncrawler`
- Source: `PF2E Advanced Players Guide`
- Category: `game-mechanic|spell|item|rule-system|creature|world-building`

Include: Goal, Source reference, Implementation hint, and Mission alignment checkboxes, following the normal Dungeoncrawler feature stub format.

## After generating features

1. Update `tmp/ba-scan-progress/dungeoncrawler.json` for `books[1]`:
   - advance `last_line` to the highest source line vetted this cycle
   - set `status` to `in_progress` or `complete`
2. Write an outbox with:
   - features created (id + one-line description)
   - source lines covered
   - key skips / duplicates noted
   - any follow-up recommendation for PM triage

## Source aids

- Outline: `docs/dungeoncrawler/reference documentation/outlines/PF2E_Advanced_Players_Guide_OUTLINE.md`
- Source text: `docs/dungeoncrawler/reference documentation/PF2E Advanced Players Guide.txt`
- Item inventory (orientation only): `docs/dungeoncrawler/reference documentation/comprehensive_item_inventory.json`
- Spell inventory (orientation only): `docs/dungeoncrawler/reference documentation/comprehensive_spell_inventory_filtered.json`
- Agent: ba-dungeoncrawler
