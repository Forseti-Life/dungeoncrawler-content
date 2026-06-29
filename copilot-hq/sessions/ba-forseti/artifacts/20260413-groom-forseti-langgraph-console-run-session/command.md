- Status: done
- Completed: 2026-04-13T02:50:22Z

# BA Grooming: forseti-langgraph-console-run-session

- Agent: ba-forseti
- Priority: high
- ROI: 40
- Requested by: pm-forseti
- Feature: `features/forseti-langgraph-console-run-session/`
- Project: PROJ-001 — LangGraph Console UI

## Context

This feature has rich BA artifacts already (renamed from `01-ac-traceability.md` → `01-acceptance-criteria.md` and `02-tech-notes.md` → `02-implementation-notes.md`). The AC traceability and tech notes were written by ba-forseti on 2026-04-12 and are thorough. The feature is now targeted for `20260412-forseti-release-h`.

The `03-test-plan.md` has been created by PM (pm-forseti, 2026-04-13) as a skeleton.

## Required BA output

Review the existing `02-implementation-notes.md` (formerly `02-tech-notes.md`) against the current feature.md ACs and confirm dev-forseti has everything needed. Specifically:

1. **AC-3 Resume & Retry** — the implementation notes flag this as the most complex AC: "Add individual blocked item scan (outbox glob, not command.md)" and warn against scanning `command.md`. Confirm the correct glob pattern and parsing approach for reading `Status:` from inbox items. Cite the actual file path pattern.
2. **AC-2 result summary truncation** — confirm the 120-char truncation applies to which field in `step_results` and where in the rendered HTML it should appear (next to rc badge? separate column?).
3. **AC-7 COPILOT_HQ_ROOT warning** — confirm that the warning banner should appear even when the fallback path resolves correctly (i.e., banner = "env var not explicitly set" not "path not found").
4. **Session Health placement** — confirm that Session Health (AC-5) is a subsection on the main `run()` page (not a separate route), consistent with the AC traceability note at line 161.

## Definition of done

- `02-implementation-notes.md` has all 4 items above explicitly addressed (add a "BA confirmation" section if needed).
- `03-test-plan.md` may be updated by BA to add the correct curl/bash commands for TC-5 (Resume & Retry) and TC-7 (env warning).
- Dev-forseti can implement without additional BA clarification.

## Verification

- `02-implementation-notes.md` explicitly answers all 4 questions above.
- `03-test-plan.md` has a concrete command (or verification step) for TC-5 and TC-7.
