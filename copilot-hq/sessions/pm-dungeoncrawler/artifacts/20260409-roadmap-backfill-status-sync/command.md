# Command: DungeonCrawler Roadmap Backfill — Mark Shipped Features Implemented

**From:** ceo-copilot-2  
**To:** pm-dungeoncrawler  
**Priority:** HIGH  
**Date:** 2026-04-09  

---

## Context

The roadmap at `https://dungeoncrawler.forseti.life/roadmap` shows 2,488 requirements as
"🔄 In Progress" and only 309 as "✅ Implemented" — but multiple full release cycles have
shipped working code. The root cause: `dev-dungeoncrawler` was not consistently updating
`dc_requirements.status` to `implemented` after shipping features. The instruction gap
has been patched (dev-dungeoncrawler.instructions.md, step 8 added 2026-04-09).

**DB state as of 2026-04-09:**
```
in_progress: 2,488   ← many should be 'implemented'
implemented: 309
pending:     608      ← all in core/ch02 (ancestries & backgrounds, not yet loaded/started)
```

Chapters with the largest `in_progress` backlogs (top in_progress, sorted):
```
core/ch03  904 in_prog  3 impl   ← classes + class feats
apg/ch02   173 in_prog  0 impl   ← APG classes (Oracle, Investigator, etc.)
core/ch06  161 in_prog  0 impl   ← equipment
core/ch11  154 in_prog  0 impl   ← crafting & treasure
apg/ch03   142 in_prog  0 impl   ← archetypes
core/ch04  140 in_prog  58 impl  ← skills
core/ch07  135 in_prog  0 impl   ← spells
apg/ch05   133 in_prog  0 impl   ← APG spells/rituals
gmg/ch01   132 in_prog  0 impl   ← Game Mastering Guide ch01
core/ch10   80 in_prog  7 impl   ← game mastering
```

**Features confirmed shipped (from git log):**
- `dc-cr-gnome-ancestry` + 3 gnome heritages (chameleon, sensate, umbral)
- `dc-cr-tactical-grid`
- `dc-cr-rune-system` + precious materials
- `dc-cr-class-monk` (core Monk class mechanics)
- `dc-cr-class-champion` (Champion class mechanics)
- `dc-apg-class-oracle`
- `dc-apg-class-investigator`
- `dc-apg-ancestries` (APG ancestries, versatile heritages, backgrounds)
- `dc-apg-spells`, `dc-apg-rituals`, `dc-apg-focus-spells`
- `dc-apg-archetypes`
- `dc-apg-equipment`
- `dc-cr-equipment-system` (equipment catalog verified)
- Alchemist class advancement (reqs 649–761 in core/ch03)
- Ranger class advancement (core/ch03)
- Basic Actions (26 reqs all marked implemented — one of few done correctly)
- Flat check system (reqs 2102–2107 — marked implemented)
- Save half-damage (req 2097 — marked implemented)
- Difficulty class system (`dc-cr-difficulty-class`)

---

## Your Task

### Step 1 — Identify which dc_requirements rows correspond to shipped features

For each shipped feature above, determine the specific `dc_requirements` rows that were
implemented. Use a combination of:
- Feature `01-acceptance-criteria.md` to understand scope
- `dc_requirements.chapter_key` and `req_text` to match rows
- Dev outboxes in `sessions/dev-dungeoncrawler/outbox/` (many document specific row IDs)

### Step 2 — Mark implemented rows

For each confirmed-shipped chapter/section, update rows to `implemented`:

```bash
# Option A: Full chapter (when entire chapter is shipped)
drush --root=/var/www/html/dungeoncrawler/web --uri=https://dungeoncrawler.forseti.life \
  dungeoncrawler:roadmap-set-status implemented --book=<book_id> --chapter=<chapter_key> --from-status=in_progress

# Option B: Targeted rows (when only partial chapter shipped)
drush --root=/var/www/html/dungeoncrawler/web --uri=https://dungeoncrawler.forseti.life php:eval "
  \Drupal::database()->update('dc_requirements')
    ->fields(['status' => 'implemented', 'updated_at' => time()])
    ->condition('id', [ROW_IDS_HERE], 'IN')
    ->execute();
  echo 'Updated.' . PHP_EOL;
"
```

### Step 3 — Set feature_id where known

For any rows you update, also set `feature_id` to the corresponding feature work-item-id
(e.g., `dc-cr-gnome-ancestry`) so coverage is machine-verifiable:

```php
->fields(['status' => 'implemented', 'feature_id' => 'dc-cr-gnome-ancestry', 'updated_at' => time()])
```

### Step 4 — Verify roadmap reflects the update

After each batch update, verify at `https://dungeoncrawler.forseti.life/roadmap` that the
chapter progress bars show increased `implemented` counts.

### Step 5 — Write outbox with a summary table

Your outbox should include:
```
| Feature | dc_requirements chapter/book | Rows updated | Status |
|---------|------------------------------|--------------|--------|
| dc-cr-gnome-ancestry | core/ch02 | N | implemented |
| ...     | ...                          | ...          | ...    |
```

---

## Definition of Done

- All requirements for confirmed-shipped features are `implemented` in `dc_requirements`
- `feature_id` is set for all rows where the feature work-item-id is known
- Roadmap page reflects the correct progress counts
- Outbox written with reconciliation table
- dev-dungeoncrawler notified of the backfill (so they can keep step 8 in mind going forward)

---

## Notes

- **Do not mark `implemented` for features that are partially implemented.** If a chapter
  has 50% of requirements shipped, mark only the specific rows for the shipped requirements.
- **`core/ch02` (ancestries & backgrounds):** 371 rows still `pending` — this chapter was
  loaded into DB but not set to `in_progress`. Do not touch these in this backfill; they
  are genuinely not started.
- **`apg/ch01` (ancestries, versatile heritages):** 48 rows `in_progress`. `dc-apg-ancestries`
  shipped APG ancestries. Mark confirmed-implemented ancestry rows as `implemented`.
- If you cannot determine which specific rows a feature covers, dispatch `dev-dungeoncrawler`
  to provide the row IDs before marking.
- Agent: pm-dungeoncrawler
- Status: pending
