# Runbook: PF2E Paragraph-by-Paragraph Requirements Extraction

**Owned by**: `ba-dungeoncrawler`  
**Supervised by**: `pm-dungeoncrawler`  
**Triggered by**: CEO inbox item or PM request  

---

## Purpose

This runbook defines the process for deep requirements extraction from PF2E source books.

This is **distinct from the regular feature stub scan** (`ba-refscan-*` inbox items):

| Regular scan | Requirements extraction |
|---|---|
| Reads lines in chunks (~300) | Reads one full chapter |
| Output: feature stubs in `features/dc-*/` | Output: requirements analysis doc + issue files |
| Granularity: feature-level (one mechanic = one stub) | Granularity: paragraph-level (every sentence examined) |
| Triggered by release cycle orchestrator | Triggered by CEO or PM |
| Cap: 30 features per cycle | No cap — cover every paragraph |

---

## System of record (required)

Use the PF2requirements tracking stack exactly as follows:

1. `docs/dungeoncrawler/PF2requirements/source-ledger.json`
   - canonical source-document traceability ledger
   - tracks requirements, issue mapping, feature mapping, and release handoff
2. `docs/dungeoncrawler/PF2requirements/EXTRACTION_TRACKER.md`
   - canonical chapter/section completion tracker
3. `docs/dungeoncrawler/PF2requirements/audit/*.md`
   - exhaustive working-paper checklist proving the source object was actually reviewed
4. `tmp/ba-scan-progress/dungeoncrawler.json`
   - chunk-scan execution cursor only; not proof of requirements completeness

Every requirements extraction task must update the ledger + tracker + audit surface in the
same working session as the references artifact.

---

## Source Material

**Primary source (Core Rulebook):**
```
/home/keithaumiller/forseti.life/docs/dungeoncrawler/reference documentation/PF2E Core Rulebook - Fourth Printing.txt
```
Outline (chapter boundaries and page numbers):
```
/home/keithaumiller/forseti.life/docs/dungeoncrawler/reference documentation/outlines/PF2E_Core_Rulebook_Fourth_Printing_OUTLINE.md
```

Additional books follow the same slug conventions from `ba-dungeoncrawler.instructions.md`.

---

## Output Locations

### 1. Requirements analysis document (one per chapter)
```
/home/keithaumiller/forseti.life/docs/dungeoncrawler/PF2requirements/references/chapter-NN-<slug>.md
```
Example: `chapter-01-introduction.md` (completed — use as format reference)

### 2. Issue files (one per logical requirement group)
```
/home/keithaumiller/forseti.life/docs/dungeoncrawler/issues/issue-NN-<slug>.md
```
Numbering: continue from the highest existing issue number in that directory.

### 3. Issues README update
```
/home/keithaumiller/forseti.life/docs/dungeoncrawler/issues/README.md
```
Add a row for each new issue.

### 4. CEO inbox summary (required)
```
/home/keithaumiller/copilot-sessions-hq/sessions/ceo-copilot/inbox/YYYYMMDD-pf2e-chapter-NN-requirements.md
```
CEO routes issues to PM after reviewing.

---

## Step-by-Step Process

### Step 1 — Read the chapter outline
Open the book outline to identify the chapter's section headers and approximate line range.  
Use `view` with `view_range` to read the txt file in manageable blocks.

Before starting, confirm the source document has:
- an entry in `source-ledger.json`
- a row in `EXTRACTION_TRACKER.md`
- a matching audit worksheet in `audit/*.md`

### Step 2 — Read paragraph by paragraph
For each paragraph or named subsection in the chapter:

1. **Quote the exact source text** (verbatim, in a blockquote).
2. **Identify requirements** — ask: "If we were implementing this in software, what must the system do?"
3. **Write the requirements** as bulleted, imperative statements starting with "The system must..." or "The data model must...".
4. If a paragraph contains no mechanical implications (pure lore, flavor, credits, typography notes), write: `Requirements identified: None. (Reason.)`

### Step 3 — Requirements document format

Use this exact format (see `chapter-01-introduction.md` as the canonical example):

```markdown
# PF2E Core Rulebook — Chapter N: <Title>
## Systematic Requirements Analysis (Paragraph by Paragraph)

---

## SECTION: <Section Name>

### Paragraph 1
> "Exact quoted text from source."

Requirements identified:
- The system must...
- The data model must...

---

### Paragraph 2
> "..."

Requirements identified: None. (Pure flavor text — no mechanical implication.)

---
```

Rules:
- Every paragraph in the chapter gets its own block. Do not skip paragraphs.
- Quotes must be verbatim from the source text.
- Requirements must be implementation-facing (what code/data must do), not user-story format.
- Label each requirement with a provisional `REQ-<issue#>.<seq>` tag only if you're ready to assign it to a specific issue. Otherwise leave untagged — PM assigns during grooming.

### Step 4 — Group requirements into issues

After completing the full chapter analysis:

1. Review all requirements identified.
2. Group related requirements into logical issues (e.g., "all requirements about saving throws become Issue #N: Saving Throw System").
3. Create an issue file for each group at `issues/issue-NN-<slug>.md`.
4. Issue file format:

```markdown
# Issue #N: <Title>

**Status**: Open  
**Type**: Feature Request  
**Priority**: [Critical | High | Medium | Low]  
**Source**: PF2E Core Rulebook Chapter N — <Section Name>  
**Created**: YYYY-MM-DD

## Overview
<2–3 sentence summary of what this issue covers>

## Requirements

### REQ-N.1 — <Short name>
- Requirement text.

### REQ-N.2 — <Short name>
- Requirement text.

## Notes
- Any known code paths, partial coverage, or open questions for PM grooming.
```

Priority guidance:
- **Critical**: blocks character creation, combat, or core game loop
- **High**: required for a working playthrough but not a launch blocker
- **Medium**: important feature, can be deferred one cycle
- **Low**: nice-to-have, lore, or purely cosmetic

### Step 5 — Update the issues README

Add rows to `issues/README.md` for every new issue.

### Step 5.5 — Update the tracking stack

Before closing the task:
1. Mark the chapter/section complete in `EXTRACTION_TRACKER.md`.
2. Update the relevant source-document entry in `source-ledger.json`.
3. If the extraction yielded feature stubs, record that by advancing the document's
   `feature_mapping_status` in `source-ledger.json`.
4. If issue grouping is complete, advance `issue_mapping_status` in `source-ledger.json`.

### Step 6 — Write the CEO inbox summary

File: `sessions/ceo-copilot/inbox/YYYYMMDD-pf2e-chapter-NN-requirements.md`

Required content:
```markdown
# New Requirements: PF2E Chapter N — <Title>

**From**: ba-dungeoncrawler  
**Date**: YYYY-MM-DD  
**Priority**: [Critical | High | Medium | Low] (set to highest priority among new issues)

## Summary
<What chapter was analyzed, how many issues, key mechanical areas covered>

## New Issues

| # | Title | Priority |
|---|---|---|
| N | <Title> | <Priority> |

## Source document
`docs/dungeoncrawler/PF2requirements/references/chapter-NN-<slug>.md`

## Actions Requested
1. CEO: route issues to PM for grooming.
2. PM: groom using standard process in `runbooks/intake-to-qa-handoff.md`.
```

### Step 7 — Write your outbox

File: `sessions/ba-dungeoncrawler/outbox/YYYYMMDD-pf2e-chapter-NN-requirements.md`

Include:
- Chapter processed
- Number of issues created
- Issue numbers and titles
- Any open questions for PM/CEO

---

## What Counts as a Requirement

**Include:**
- Rules that govern how the system calculates a value (HP, AC, attack roll)
- Rules that define a data structure (what fields a stat block must have)
- Rules that define an enumeration (action types, proficiency ranks, conditions)
- Rules that constrain behavior (you may only use 1 reaction per round)
- Rules that define a process (how character creation steps chain together)
- Rules that govern outcomes (four degrees of success: critical success, success, failure, critical failure)

**Exclude:**
- Pure flavor text and lore (history of the setting, character motivation advice)
- Typography/formatting conventions with no mechanical analog
- Repeated text that was already extracted in a prior chapter
- Rules already captured in a prior chapter's analysis (add a cross-reference note instead of duplicating)

---

## Progress Tracking

After completing each chapter, update `tmp/ba-scan-progress/dungeoncrawler.json`:
- Add the chapter slug to `chapters_completed` for the book.
- Note: `last_line` tracks the regular feature-stub scan position. Do **not** advance `last_line` as part of requirements extraction — these are separate processes operating on the same source file.

Additionally:
- `EXTRACTION_TRACKER.md` is the authoritative per-chapter/section completion record.
- `source-ledger.json` is the authoritative downstream traceability record.

---

## Chapter Order (Core Rulebook)

Process chapters in order. Chapter 1 is complete.

| Chapter | Title | Status |
|---|---|---|
| 1 | Introduction | ✅ Complete |
| 2 | Ancestries & Backgrounds | ⬜ Pending |
| 3 | Classes | ⬜ Pending |
| 4 | Skills | ⬜ Pending |
| 5 | Feats | ⬜ Pending |
| 6 | Equipment | ⬜ Pending |
| 7 | Spells | ⬜ Pending |
| 8 | The Age of Lost Omens | ⬜ Pending |
| 9 | Playing the Game | ⬜ Pending |
| 10 | Game Mastering | ⬜ Pending |
| 11 | Crafting & Treasure | ⬜ Pending |
| — | Conditions Appendix | ⬜ Pending |

Chapters 8 (Age of Lost Omens) is primarily lore — expect fewer mechanical requirements but still process it fully.

---

## Reference

- Completed example: `docs/dungeoncrawler/PF2requirements/references/chapter-01-introduction.md`
- Issues produced from Chapter 1: `issues/issue-5-rules-vocabulary-glossary.md` through `issues/issue-17-leveling-up-system.md`
- Issues README: `docs/dungeoncrawler/issues/README.md`
- BA scan progress: `tmp/ba-scan-progress/dungeoncrawler.json`
