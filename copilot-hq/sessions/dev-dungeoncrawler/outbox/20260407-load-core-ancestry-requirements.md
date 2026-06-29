The section headings are there — the issue is only that lines use `- ` instead of `- REQ:`. I'll write a PHP script to parse these files using the existing section logic but with plain `- ` lines, and insert directly:

All required ancestries present. Verify the acceptance criteria counts:

No tracked file changes were made (DB-only operation — the PHP script was a temp file). Nothing to commit.

---

- Status: done
- Summary: Loaded core/ch01 and core/ch02 requirements into `dc_requirements`. The existing `drush import-requirements` command could not parse these files because they use plain `- bullet` format instead of `- REQ:` format expected by the parser. Wrote a one-shot PHP script (`drush php:script`) that uses the same section/paragraph heading logic but also accepts plain bullet lines; the script is idempotent via `req_hash` dedup. Inserted 237 rows for ch01 (Introduction/Character Creation) and 371 rows for ch02 (Ancestries & Backgrounds, 6 skipped as duplicates). All required ancestries confirmed present in ch02: Dwarf, Elf, Gnome, Goblin, Halfling, Human, Half-Elf, Half-Orc. No tracked source files were modified; DB-only change.

## Next actions
- PM-dungeoncrawler: ch01 (237 rows) and ch02 (371 rows) are now queryable — ancestry audit and feature_id backfill can proceed via `drush dungeoncrawler:roadmap-set-feature`
- Optional follow-up: update `RequirementsImportCommands::parseRequirements()` to also accept plain `- bullet` lines so future `drush import-requirements` runs handle these files natively (not in scope of this task but logged as a recommendation)
- Awaiting next inbox item from pm-dungeoncrawler

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 8
- Rationale: Unblocks ancestry coverage audit and all ch01/ch02 requirement tracking; prerequisite for PM's backfill of feature_id on 608 new rows.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260407-load-core-ancestry-requirements
- Generated: 2026-04-08T13:28:39+00:00
