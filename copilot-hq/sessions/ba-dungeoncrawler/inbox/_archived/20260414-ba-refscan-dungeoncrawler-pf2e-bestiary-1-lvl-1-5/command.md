# Reference Document Scan — PF2E Bestiary 1 (Levels 1–5 only)

**Site:** dungeoncrawler  
**Next release:** 20260412-dungeoncrawler-release-n  
**Book:** PF2E Bestiary 1 (bestiary)  
**Progress:** start from `books[3].last_line + 1` in `tmp/ba-scan-progress/dungeoncrawler.json`  
**Progress state file:** `tmp/ba-scan-progress/dungeoncrawler.json`  
**Book entry:** `books[3]`  

## Your task

Extract implementable Dungeoncrawler creature features from **PF2E Bestiary 1**, but **only for creatures level 1 through 5**.

Use `docs/dungeoncrawler/reference documentation/comprehensive_creature_inventory_filtered.json` to shortlist candidate creatures from Bestiary 1, but treat it as an orientation aid only. The book text is authoritative, and each creature/mechanic must be manually vetted against the source before a stub is created.

Skip:
- creatures above level 5
- appendix-only reference material unless it directly supports a qualifying creature feature
- lore-only text without concrete combat, stat-block, ability, trait, or encounter mechanics

## Creating a feature stub

Create `features/dc-b1-<descriptor>/feature.md` using the standard Dungeoncrawler feature stub format with source `PF2E Bestiary 1`.

Each qualifying creature should produce one feature stub centered on the implementable creature package, including any standout abilities that materially affect implementation.

## After generating features

1. Update `tmp/ba-scan-progress/dungeoncrawler.json` for `books[3]`:
   - advance `last_line` to the highest line vetted this cycle
   - keep `status` as `in_progress` until all qualifying level 1–5 creatures from Bestiary 1 are covered
2. Write an outbox listing:
   - creatures/features created
   - source lines covered
   - skipped higher-level creatures encountered
   - follow-up recommendations for PM triage

## Source aids

- Outline: `docs/dungeoncrawler/reference documentation/outlines/PF2E_Bestiary_1_OUTLINE.md`
- Source text: `docs/dungeoncrawler/reference documentation/PF2E Bestiary 1.txt`
- Filtered creature inventory (orientation only): `docs/dungeoncrawler/reference documentation/comprehensive_creature_inventory_filtered.json`
- Agent: ba-dungeoncrawler
- Status: pending
