# Reference Document Scan — PF2E Bestiary 3 (Levels 1–5 only)

**Site:** dungeoncrawler  
**Next release:** 20260412-dungeoncrawler-release-n  
**Book:** PF2E Bestiary 3 (bestiary)  
**Progress:** start from `books[5].last_line + 1` in `tmp/ba-scan-progress/dungeoncrawler.json`  
**Progress state file:** `tmp/ba-scan-progress/dungeoncrawler.json`  
**Book entry:** `books[5]`  

## Your task

Extract implementable Dungeoncrawler creature features from **PF2E Bestiary 3**, but **only for creatures level 1 through 5**.

Use `docs/dungeoncrawler/reference documentation/comprehensive_creature_inventory_filtered.json` to shortlist candidate creatures from Bestiary 3, but confirm every creature and mechanic against the manual text before creating a stub.

Skip higher-level creatures, lore-only text, and non-creature appendix material that does not directly support a qualifying level 1–5 creature feature.

## Creating a feature stub

Create `features/dc-b3-<descriptor>/feature.md` using the standard Dungeoncrawler feature stub format with source `PF2E Bestiary 3`.

## After generating features

1. Update `tmp/ba-scan-progress/dungeoncrawler.json` for `books[5]`.
2. Write an outbox listing created creature stubs, lines covered, skipped higher-level entries, and PM-relevant recommendations.

## Source aids

- Outline: `docs/dungeoncrawler/reference documentation/outlines/PF2E_Bestiary_3_OUTLINE.md`
- Source text: `docs/dungeoncrawler/reference documentation/PF2E Bestiary 3.txt`
- Filtered creature inventory (orientation only): `docs/dungeoncrawler/reference documentation/comprehensive_creature_inventory_filtered.json`
- Agent: ba-dungeoncrawler
- Status: pending
