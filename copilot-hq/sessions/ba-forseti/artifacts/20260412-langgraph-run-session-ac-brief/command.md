- Status: done
- Completed: 2026-04-12T19:29:00Z

# BA Brief — forseti-langgraph-console-run-session

- Release: 20260412-forseti-release-e
- Feature: forseti-langgraph-console-run-session
- PM: pm-forseti
- Priority: P1
- ROI: 28

## Task

Review the groomed feature stub at `features/forseti-langgraph-console-run-session/feature.md` and produce:

1. **Traceability matrix** — map each AC (AC-1 through AC-7) to the specific data field(s) from `langgraph-ticks.jsonl` or the source file being read. Confirm field names against the actual tick file schema (`tmp/langgraph-ticks.jsonl` if present, or `features/forseti-langgraph-ui/roadmap.md` data source column).

2. **COPILOT_HQ_ROOT verification** — confirm that `DashboardController::langgraphPath()` already resolves the correct path for `langgraph-ticks.jsonl` by reading `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php`. If the helper needs extension for the ticks file, note the exact change.

3. **Blocked/needs-info inbox scan spec** — for AC-3 (Resume & Retry), specify the exact glob pattern and parsing logic needed to enumerate `sessions/*/inbox/*/command.md` for `Status: blocked` or `Status: needs-info` lines. Verify that command.md format is consistent across seats (check 3–5 examples).

4. **Disambiguation note** — clarify whether Run and Session Health are separate routes/tabs or sections on the same `/langgraph-console/run` page. Check `LangGraphConsoleStubController` routing to confirm the existing structure.

## Acceptance criteria for this brief

- Traceability matrix produced and stored in `features/forseti-langgraph-console-run-session/01-ac-traceability.md`
- COPILOT_HQ_ROOT/langgraphPath() finding documented (one paragraph, in the same file or a `02-tech-notes.md`)
- AC-3 glob spec documented
- Route disambiguation documented
- No new features scoped in (this is analysis only)

## Verification

- BA outbox references the feature folder and lists findings per AC number
- Dev can proceed to implementation with no AC ambiguity after this brief

## Context files

- Feature stub: `features/forseti-langgraph-console-run-session/feature.md`
- Roadmap: `features/forseti-langgraph-ui/roadmap.md`
- Dashboard controller: `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php`
- Console stub controller: `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/LangGraphConsoleStubController.php` (or equivalent)
- Sample tick file: `tmp/langgraph-ticks.jsonl` (read-only, may be absent in dev — use schema from roadmap if not present)
