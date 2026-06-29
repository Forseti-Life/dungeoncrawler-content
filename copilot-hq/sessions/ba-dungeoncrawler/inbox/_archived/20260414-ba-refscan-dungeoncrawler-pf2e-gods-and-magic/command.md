# Reference Document Scan — PF2E Gods and Magic

**Site:** dungeoncrawler  
**Next release:** 20260412-dungeoncrawler-release-n  
**Book:** PF2E Gods and Magic (rulebook)  
**Progress:** start from `books[8].last_line + 1` in `tmp/ba-scan-progress/dungeoncrawler.json`  
**Progress state file:** `tmp/ba-scan-progress/dungeoncrawler.json`  
**Book entry:** `books[8]`  

## Your task

Read the source material and extract implementable Dungeoncrawler features, with emphasis on domains, divine options, feats, spells, items, and deity-linked mechanics that can become systems, content, or progression hooks.

Treat outlines and inventories as orientation only. Most manuals appear only partially vetted, so confirm each mechanic against the source text before creating a feature stub.

Skip lore-only deity writeups unless they introduce a concrete rule, option, spell, or player-facing mechanic that Dungeoncrawler could implement.

## Creating a feature stub

Create `features/dc-gam-<descriptor>/feature.md` using the standard Dungeoncrawler feature stub format with source `PF2E Gods and Magic`.

## After generating features

1. Update `tmp/ba-scan-progress/dungeoncrawler.json` for `books[8]`.
2. Write an outbox listing created features, source lines covered, and any recommendations for PM triage.

## Source aids

- Outline: `docs/dungeoncrawler/reference documentation/outlines/PF2E_Gods_and_Magic_OUTLINE.md`
- Source text: `docs/dungeoncrawler/reference documentation/PF2E Gods and Magic.txt`
- Item inventory (orientation only): `docs/dungeoncrawler/reference documentation/comprehensive_item_inventory.json`
- Spell inventory (orientation only): `docs/dungeoncrawler/reference documentation/comprehensive_spell_inventory_filtered.json`
- Agent: ba-dungeoncrawler
- Status: pending
