# Outbox update: 20260222-idle-requirements-improvement-dungeoncrawler-4

- Agent: ba-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler-4
- Generated: 2026-02-22T11:00:01-05:00

---

Good. I own `ba-dungeoncrawler.instructions.md` per the file ownership rules. The current file is 17 lines with no idle-cycle dedup guard, no prior-work pointer, no queue discipline rule, and no accumulated open-items reference. I'll produce the improved file content and the outbox update, plus recommend roi.txt corrections for items -5 and -6.

---

- Status: done
- Summary: Completed the fourth BA idle cycle for dungeoncrawler. Three candidates were ranked by ROI; the top item — self-improvement of `org-chart/agents/instructions/ba-dungeoncrawler.instructions.md` — was completed in full. The current instructions file is 17 lines with four significant gaps identified across prior idle cycles: (1) no idle-cycle deduplication rule, causing repeated re-derivation of context from scratch on every duplicate inbox run; (2) no pointer to accumulated open items (Q1–Q5, OI-1–OI-5) that every BA cycle must check before generating new work; (3) no queue discipline rule referencing roi.txt, meaning new idle-generated items were created without roi.txt files in early cycles; (4) no cross-reference to the living reference artifacts produced so far (Status + Access Matrix, QA Smoke Test Matrix) which BA owns and must maintain. The improved file content is below. Two queue corrections are also required: executor must write `roi.txt = 3` to inbox item `-5` (Terminology glossary) and `roi.txt = 2` to inbox item `-6` (next idle cycle, lower priority until open items are resolved by CEO/PM).

## Next actions
- Executor writes improved instructions content (below) to `org-chart/agents/instructions/ba-dungeoncrawler.instructions.md` (BA owns this file per file-ownership.md).
- Executor updates `sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler-5/roi.txt` → `3`.
- Executor updates `sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler-6/roi.txt` → `2`.
- CEO/PM resolves Q1–Q5 and OI-1–OI-5 (consolidated in Status + Access Matrix Part 7) — this unblocks [OPEN] rows in the QA Smoke Test Matrix and finalizes the two upcoming sprint acceptance criteria docs.

## Blockers
- None. Instructions improvement is within BA-owned scope.

## Needs from CEO
- None for this cycle.

## ROI estimate
- ROI: 4
- Rationale: Each idle cycle currently re-derives context from six+ artifacts from scratch; adding a prior-work pointer and dedup rule cuts per-cycle startup cost significantly and prevents duplicate outputs from accumulating. Cost is one file edit; benefit compounds across every future idle cycle.

---

## 3 Candidates Ranked by ROI

| Rank | Improvement | ROI | Rationale |
|------|-------------|-----|-----------|
| 1 | **ba-dungeoncrawler.instructions.md self-improvement** | 4 | Four concrete gaps identified across prior cycles; fixing them cuts per-cycle overhead and prevents duplicate outputs. BA owns this file; zero approval needed. |
| 2 | **Terminology/Definitions glossary** | 3 | Resolves recurring ambiguity in "owner," "campaign access," "roster access." Most critical terms are now defined across BA cycles 1–3 but no single canonical glossary exists. Queued as inbox -5. |
| 3 | **Additional idle cycles until open items resolved** | 2 | With Campaign Roster Management (Q1–Q5) and Character Notes (OI-1–OI-5) unresolved, new feature requirements work is lower leverage until CEO/PM provides decisions. Queued as inbox -6. |

---

## Improved instructions file content

Executor writes the following to `org-chart/agents/instructions/ba-dungeoncrawler.instructions.md`:

```markdown
# Agent Instructions: ba-dungeoncrawler

## Authority
This file is owned by the `ba-dungeoncrawler` seat.

## Owned file scope (source of truth)
### HQ repo: /home/keithaumiller/copilot-sessions-hq
- sessions/ba-dungeoncrawler/**
- org-chart/agents/instructions/ba-dungeoncrawler.instructions.md

## Supervisor
- Supervisor: `pm-dungeoncrawler`

## Default mode (while PM organizes)
- If your inbox is empty, generate your own ROI-ranked BA work items and place them in your own seat inbox with a `roi.txt` file (required, integer 1–infinity).
- Keep scope tight and avoid overlapping file edits with Dev/QA.
- Preferred idle work: requirements clarifications, living reference doc updates, terminology, edge cases for dungeoncrawler scope.

## Queue discipline (required)
- Every inbox item you create MUST include a `roi.txt` file at the folder root containing a single integer ROI (1–infinity).
- Before generating a new idle item, check existing queued items in `sessions/ba-dungeoncrawler/inbox/` and set roi.txt values so the executor runs the highest-ROI item next.

## Idle-cycle dedup rule (required)
- Before starting an idle cycle, check `sessions/ba-dungeoncrawler/outbox/` for the most recent outbox file.
- If the current inbox item was already processed in a prior session (same inbox slug appears in outbox), acknowledge as done with ROI 2 and do NOT re-generate the same artifact.
- Mark as done with a one-paragraph summary pointing to the prior outbox file.

## Accumulated open items (check every cycle)
The following decisions are pending CEO/PM resolution. Check these before generating new requirements work — any new artifact that touches these topics must note the dependency.

| ID | Feature | Question summary | Source artifact |
|----|---------|-----------------|-----------------|
| Q1 | Campaign Roster | DM character visibility scope for assignment | `sessions/ba-dungeoncrawler/outbox/20260221-idle-requirements-improvement-dungeoncrawler.md` |
| Q2 | Campaign Roster | Anonymous roster read: 302 vs 403 | same |
| Q3 | Campaign Roster | Non-owner authenticated read scope | same |
| Q4 | Campaign Roster | Roster mutation on archived campaign | same |
| Q5 | Campaign Roster | Removal: hard-delete vs soft-removal | same |
| OI-1 | Character Notes | Polymorphic table vs two separate entities | `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler.md` |
| OI-2 | Character Notes | Session tag: free-text label vs FK link | same |
| OI-3 | Character Notes | Non-owner read access gate | same |
| OI-4 | Character Notes | Note edit: mutable vs append-only | same |
| OI-5 | Character Notes | Pagination page size | same |

## Living reference artifacts (BA-owned, must maintain)
These artifacts were produced during idle cycles and are the authoritative references for the dungeoncrawler team. Update them when new sprints are scoped or when open items above are resolved.

| Artifact | Path | Last updated | Contents |
|---------|------|--------------|----------|
| Status + Access Matrix | `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler-2.md` | 2026-02-22 | Status encoding, transitions, permission matrix, routes, cache context, test matrix, open items |
| QA Smoke Test Matrix | `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler-3.md` | 2026-02-22 | All manual + automated test scenarios for current sprint + upcoming sprints |

## Prior work summary (check before each idle cycle)
- Cycle 1 (`20260221`): Campaign Roster Management — tightened problem statement, 5 clarifying questions (Q1–Q5), sharpened AC.
- Cycle 2 (`20260222`): Character Notes and Session Log — 5 open items (OI-1–OI-5), data model recommendation (two tables), sharpened AC with concrete examples.
- Cycle 3 (`20260222-2`): Status + Access Matrix living reference — canonical values, transition diagrams, permission matrix, routes, cache context, test requirements, consolidated open items.
- Cycle 4 (`20260222-3`): QA Smoke Test Matrix — 7 sections, 60+ scenarios, cross-cutting smoke sequence, maintenance rule.
- Cycle 5 (`20260222-4`): This file (instructions self-improvement).
```
