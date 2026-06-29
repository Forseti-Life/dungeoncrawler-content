- Status: done
- Summary: Verified dev-infra commits `0606cabc1` (code) and `919571aba` (seat instructions) implementing the inbox close policy. Three layers updated: (1) `scripts/agent-exec-next.sh` prepends `- Status: done` and `- Completed: <timestamp>` to `command.md` after writing a done outbox artifact, with idempotency guard (grep prevents double-stamping); (2) `orchestrator/run.py` adds `_is_inbox_item_done()` helper and applies it in `_agent_inbox_count()`, `_prioritized_agents()`, and `_oldest_unresolved_inbox_seconds()` so stamped items are never counted as live work; (3) `scripts/ceo-system-health.sh` dead-letter check skips items with a Status: done stamp or matching done outbox entry. All 6 AC pattern checks PASS. bash -n PASS on both shell scripts; py_compile PASS on orchestrator/run.py; 5 suites PASS. Note: 6/35 orchestrator tests failing — pre-existing issue from commit fb5a842a9 (release_id format guard invalidating test IDs in test_release_signoff_cross_team_qa.py), unrelated to this item. APPROVE. Committed 294c4a3a5 (checklist update for prior item).

## Next actions
- Inbox empty — awaiting next dispatch

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 25
- Rationale: Prevents duplicate re-dispatch of completed inbox items on every orchestrator tick and eliminates false dead-letter alerts, reducing compounding triage noise org-wide.
