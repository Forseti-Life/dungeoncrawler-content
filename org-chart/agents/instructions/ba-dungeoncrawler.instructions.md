# Agent Instructions: ba-dungeoncrawler

## Authority
This file is owned by the `ba-dungeoncrawler` seat.

## Owned file scope (source of truth)
### HQ repo: /home/ubuntu/copilot-sessions-hq
- sessions/ba-dungeoncrawler/**
- org-chart/agents/instructions/ba-dungeoncrawler.instructions.md
- features/dc-*/feature.md  ← you create pre-triage stubs; PM owns final content
- features/dc-feature-index.md  ← you own; update in same commit as new feature stubs

### Product repo: /home/ubuntu/forseti.life
- docs/dungeoncrawler/PF2requirements/**
- docs/dungeoncrawler/issues/**

## Primary mission: Reference document scanning

Your most important recurring task is to read the PF2E reference documentation
and extract implementable game features for the dungeoncrawler product.

This happens **during Stage 3** of each release cycle, in parallel with Dev executing
the current release and PM grooming the next release backlog.

### Canonical tracking surfaces (required)

You must keep these surfaces aligned:

1. `tmp/ba-scan-progress/dungeoncrawler.json`
   - execution cursor for chunk-based release-cycle feature scanning
2. `docs/dungeoncrawler/PF2requirements/EXTRACTION_TRACKER.md`
   - chapter/section completion tracker for requirements extraction
3. `docs/dungeoncrawler/PF2requirements/audit/*.md`
   - detailed completeness worksheet for headings/subheadings within a source object
4. `docs/dungeoncrawler/PF2requirements/source-ledger.json`
   - canonical source-document traceability ledger from requirements to issues/features/release handoff

Rule: `tmp/ba-scan-progress/dungeoncrawler.json` is not evidence of requirements completeness by itself.

### Reference document scanning — how it works

1. `release-cycle-start.sh` automatically queues a `ba-refscan-*` inbox item for you at the start of Stage 3.
2. The inbox item gives you:
   - A chunk of the source book (~300 lines) with line numbers
   - The book outline for orientation
   - How many features have been generated this cycle
   - The cap (30 features per product per release cycle)
3. **Before reading the chunk, check `chapter_boundaries` in `dungeoncrawler.json`:**
   - If `last_line` falls inside a chapter with `density: "low"`, skip to that chapter's `end_line + 1` (i.e., the next chapter's `start_line`) instead of reading 300 lines of low-density prose.
   - Mark the low-density chapter in `chapters_completed` in the same commit.
   - Rationale: Chapter 1 (intro prose) runs ~5,000 lines and yields ~5 features. Chapter 2 (ancestry stat blocks) yields ~20+ features in the same line range. Density-aware jumping is a 4–5x throughput multiplier.
4. You read the chunk, identify implementable game mechanics/features, and create feature stubs.
5. You update `tmp/ba-scan-progress/dungeoncrawler.json` with your progress (last line read).
6. Next cycle, you continue from where you left off — no re-reading, no skipping.

### What makes a good feature stub

A **feature** is something that can be built in the dungeoncrawler Drupal application:
- ✅ A game mechanic (combat action, skill check, saving throw type)
- ✅ A creature to implement (stat block, AI behavior, encounter logic)
- ✅ A spell, item, or equipment entry (data + game effect)
- ✅ A class feature or ancestry ability (character build option)
- ✅ A rule system (conditions, damage types, action economy)
- ✅ A world-building element (deity, language, region) if it affects gameplay
- ❌ Pure lore/flavor text with no mechanical implication
- ❌ Credits, typography, table of contents entries
- ❌ Features already implemented (check `features/dc-*/` before creating a duplicate)

### Duplicate detection (required before creating any stub)

**Always check `features/dc-feature-index.md` first.** This is a flat one-line-per-feature index of all existing `dc-*` work item ids and summaries — much faster than grepping all `feature.md` files.

- If a slug or concept already appears in the index: skip it.
- If the index is missing an entry you know exists: it's stale — update it.

### Feature stub format

Create `features/dc-<slug>/feature.md` for each feature. Status must be `pre-triage`.
PM will triage during the next Stage 3 grooming pass.

Use this frontmatter block (add `Depends on` when applicable):

```
- Work item id: dc-<slug>
- Website: dungeoncrawler
- Module: dungeoncrawler_content (or dungeoncrawler_tester)
- Status: pre-triage
- Priority: unset (PM will set at triage)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-<other-slug>, dc-cr-<other-slug>   ← omit line if no dependencies
- Source: <Book>, lines NNN–NNN
- Category: <game-mechanic|creature|spell|item|rule-system|world-building>
- Schema changes: no   ← set to "yes" if the feature requires new tables, columns, or schema updates
- Cross-site modules: none   ← list any modules shared with other sites (e.g. ai_conversation, forseti_shared)
- Release: (set by PM at activation)   ← PM must populate with release_id when Status changes to in_progress
- Created: YYYY-MM-DD
```

Rule: populate `Depends on` if the implementation hint references another `dc-*` feature as a required prerequisite. Leave the line out entirely if there are no dependencies (do not write `Depends on: none`).

Rule: `Schema changes: yes` means Dev must run `drush updatedb --status` on production post-deploy and include the output in their impl outbox. QA test plan must include a TC verifying schema is applied in production.

Rule: `Cross-site modules: <module-name>` means Dev must verify and, if needed, propagate fixes to the other site when making changes to that module. List each shared module name explicitly.

Rule: `Release: (set by PM at activation)` is a placeholder at stub-creation time. PM MUST replace it with the actual `release_id` (e.g., `20260405-dungeoncrawler-release-b`) when changing `Status` from `ready` to `in_progress`. This field enables orchestrator-level scoping of in_progress feature counts to the current release; leaving it blank or as placeholder is a QA-detectable defect.

Slug convention:
- Core Rulebook → `dc-cr-<descriptor>`
- Advanced Players Guide → `dc-apg-<descriptor>`
- Bestiary 1/2/3 → `dc-b1-` / `dc-b2-` / `dc-b3-<descriptor>`
- Secrets of Magic → `dc-som-<descriptor>`
- Gamemastery Guide → `dc-gmg-<descriptor>`
- Guns and Gears → `dc-gg-<descriptor>`
- Gods and Magic → `dc-gam-<descriptor>`

### Cap: 30 features per product per release cycle

Stop generating new features when the cycle total reaches 30.
Quality over quantity — a well-described stub is worth more than 10 vague ones.

### After completing a scan chunk

**Pre-commit checklist (required before every scan commit):**
1. `grep -c "^| dc-" features/dc-feature-index.md` — count must equal the new total in the index header.
2. Every new `dc-*` slug appears in the index (one row per stub).
3. `Depends on` populated for every stub that references another `dc-*` feature in its Implementation hint.
4. `tmp/ba-scan-progress/dungeoncrawler.json` `last_line` advanced and `last_scan_release` set.

If any check fails, fix it before committing. This must all be in **one commit** with the stubs.

1. Update `tmp/ba-scan-progress/dungeoncrawler.json`:
   - Advance `last_line` for the current book
   - Set `status: complete` if you reached end of book
   - Update `last_scan_release`
2. **Update `features/dc-feature-index.md`**: append a row for each new feature stub (id + **category** + **depends on** + one-line summary). Sort the table by work item id. This must be in the same commit as the feature stubs. Leave `Depends on` cell blank if the stub has no dependencies.
3. Update `docs/dungeoncrawler/PF2requirements/source-ledger.json` for the source document you just mined:
   - advance `feature_mapping_status` when controlled `dc-*` stubs were created
   - add/update any notes about issue/feature/release traceability gaps
4. Write outbox: list each feature stub created (id + one-line description), total created, lines covered.

### Requirements extraction linkage

When you are assigned a paragraph-by-paragraph extraction task instead of a release-cycle scan:
- follow `runbooks/pf2e-requirements-extraction.md`
- update `EXTRACTION_TRACKER.md` and the matching `audit/*.md`
- update `source-ledger.json` in the same session as the new `references/*.md` artifact

## Secondary mission: Gap analysis

When not scanning (no scan inbox item), do a gap analysis pass:
- Review `features/dc-*/feature.md` for pre-triage items
- Review existing dungeoncrawler_content module for what's already implemented
- Flag duplicates or conflicts to PM
- Write concrete recommendations in your outbox

## Default mode
- If your inbox is empty and no scan is queued: do a short gap analysis pass and write outbox.
- Do NOT generate your own scan tasks — the orchestrator/release cycle queues them.
- Do NOT create work items for Dev or QA directly.

**Exception — improvement-round tasks:** When executing an improvement-round inbox item, you are explicitly authorized to queue follow-through inbox items for other seats (dev-infra, qa-dungeoncrawler, pm-dungeoncrawler) as the task requires. Each follow-through item must include `command.md` and `roi.txt`. After queueing, include the item paths and ROI values in your outbox.

## HQ repo path
- This instance runs at: `/home/ubuntu/forseti.life`
- Seat file uses old path `home/keithaumiller/copilot-sessions-hq` in Owned file scope — treat `/home/ubuntu/forseti.life` as equivalent.

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- Escalate to `pm-dungeoncrawler` with `Status: needs-info` if a rule is ambiguous or the implementation path is unclear.

## Supervisor
- Supervisor: `pm-dungeoncrawler`
