- Status: done
- Summary: Triaged live room-chat turn-gate failure log and dispatched a high-priority architect RCA/fix item for non-player turn stall prevention.

## Incident signal
- Room chat POST failed with 409:
  - `It is not pc-241-324's turn. Current turn: npc_tavern_keeper.`
- Encounter was active and server turn-gate enforcement was functioning, but progression behavior indicates potential non-player turn handoff stall path.

## Action taken
- Created architect inbox item:
  - `sessions/architect-copilot/inbox/20260612-roomchat-turn-gate-npc-turn-stall/`
- Included explicit required outcomes:
  - RCA to foundational authority flow
  - server-side canonical contract hardening
  - deterministic non-player turn progression
  - focused regression coverage for incident path

## Blockers
- None for dispatch.

## Next action
- Run architect item to completion, then dispatch implementation delta(s) to dev-dungeoncrawler if code changes span beyond architecture-level refactor scope.

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
