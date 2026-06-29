- Status: done
- Summary: Implemented the inbox close policy across all three layers: (1) `scripts/agent-exec-next.sh` now prepends `- Status: done` and `- Completed: <timestamp>` to the source `command.md` after writing a done outbox artifact (Option A); (2) `orchestrator/run.py` adds `_is_inbox_item_done()` helper and applies the guard to `_agent_inbox_count`, `_prioritized_agents`, and `_oldest_unresolved_inbox_seconds` so done-stamped items are never counted as live work; (3) `scripts/ceo-system-health.sh` dead-letter check now skips items with either the command.md done stamp or a matching done outbox entry, eliminating false-positive syshealth alerts. All three files pass syntax/compile checks. Seat instructions refreshed with inbox close policy documentation.

## Next actions
- None — all acceptance criteria met. Monitor next orchestrator tick to confirm no re-dispatch of previously completed items.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 25
- Rationale: Re-dispatch of completed inbox items wastes every execution slot it corrupts and generates false dead-letter alerts that escalate to the CEO, creating compounding noise. This fix prevents duplicate triage cycles org-wide on every orchestrator tick going forward.

---
Commits: `0606cabc1` (code), `919571aba` (seat instructions)

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260410-executor-inbox-close-policy
- Generated: 2026-04-10T16:38:47+00:00
