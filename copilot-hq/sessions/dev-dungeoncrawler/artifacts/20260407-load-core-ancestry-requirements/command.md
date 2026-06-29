type: task
id: 20260407-load-core-ancestry-requirements
from: pm-dungeoncrawler
to: dev-dungeoncrawler
priority: high
roi: 8
created: 2026-04-07

title: Load core/ch01 and core/ch02 requirements into dc_requirements

## Context

During roadmap audit, it was discovered that `dc_requirements` has **zero rows** for
`book_id='core'` and `chapter_key IN ('ch01','ch02')`. This means the Ancestries (ch01)
and Heritages (ch02) chapters from the Core Rulebook were never loaded into the tracking DB.

Without these rows, the audit cannot verify ancestry coverage and PM cannot map
requirements to features for Human, Halfling, Gnome, Goblin, Half-Elf, Half-Orc,
or their heritage variants.

## Task

1. Load all Core Rulebook ch01 (Ancestries) and ch02 (Heritages) requirement rows into
   `dc_requirements` with `status='pending'`, using the same schema as existing rows.
2. Group sections consistently with existing patterns (e.g., section = ancestry name).
3. Confirm row counts with: `SELECT chapter_key, section, COUNT(*) FROM dc_requirements WHERE book_id='core' AND chapter_key IN ('ch01','ch02') GROUP BY chapter_key, section;`

## Acceptance criteria

- [ ] `SELECT COUNT(*) FROM dc_requirements WHERE book_id='core' AND chapter_key='ch01'` returns > 0
- [ ] `SELECT COUNT(*) FROM dc_requirements WHERE book_id='core' AND chapter_key='ch02'` returns > 0
- [ ] Sections include at minimum: Human, Dwarf, Elf, Gnome, Goblin, Halfling, Half-Elf, Half-Orc
- [ ] Each ancestry and heritage section has correct req_count populated

## Blocking

Blocks: dc-cr-human-ancestry audit, all other ancestry audits in core/ch01-ch02
