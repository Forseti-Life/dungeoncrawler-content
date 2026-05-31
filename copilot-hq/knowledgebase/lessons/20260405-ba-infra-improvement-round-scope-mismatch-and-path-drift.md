# Lesson Learned: Improvement-Round Scope Mismatch + Seat Instruction Path Drift

- Date: 2026-04-05
- Agent(s): ba-infra
- Website: infrastructure
- Module(s): ba-infra seat instructions, improvement-round inbox dispatch

## What happened

The `20260322-improvement-round` inbox item dispatched to ba-infra contained a command explicitly scoped to "PM/CEO": *Post-release process and gap review*. ba-infra is a BA role; it does not own post-release gap reviews or release queue orchestration.

Additionally, ba-infra's seat instructions still referenced the stale server path `/home/keithaumiller/copilot-sessions-hq` in the Owned file scope section. The server migrated to `/home/ubuntu/forseti.life/copilot-hq` and this path was not updated in the ba-infra seat file, meaning any agent or script referencing ba-infra's owned scope from the instructions would point to a non-existent directory.

## Root cause

1. **Dispatch mismatch**: The improvement-round dispatch template does not filter commands by role. PM/CEO-labeled commands are sent to all seats including BA.
2. **Path drift**: ba-infra's seat instructions were authored during the keithaumiller-era server configuration and were not included in the org-wide path migration sweep (dev-infra inbox item 20260405-hq-script-path-migration covers scripts, not seat instruction files).

## Impact

- ba-infra would produce a non-actionable outbox if it attempted to execute the PM/CEO scoped command without the mismatch-handling rule.
- Any automation referencing ba-infra's owned file scope from the instructions would silently point to a missing directory.

## Detection / Signals

- Seat instructions still mention `/home/keithaumiller/` after org-wide path migration.
- Improvement-round command body says "(PM/CEO)" in the title.

## Fix applied (if any)

- Updated ba-infra seat instructions: `org-chart/agents/instructions/ba-infra.instructions.md` line 10: `/home/keithaumiller/copilot-sessions-hq` → `/home/ubuntu/forseti.life/copilot-hq`.
- ba-infra seat instructions already contain a "Command-type mismatch handling" rule (added in a prior cycle) that correctly handles PM/CEO-scoped dispatch.

## Prevention (process + code)

- Any path migration sweep (server or repo move) must include a pass over ALL seat instruction files, not just scripts in `scripts/`.
- Search signal: `grep -r "keithaumiller" org-chart/agents/instructions/` after any server migration.
- The command-type mismatch rule in ba-infra seat instructions is the correct defense against misdirected dispatch; it is already present.

## References
- CEO post-release gap review: `sessions/ceo-copilot-2/outbox/20260405-post-release-gap-review-20260322-dungeoncrawler-release-next.md` (Gap 2: stale paths)
- dev-infra inbox item: `sessions/dev-infra/inbox/20260405-hq-script-path-migration` (covers scripts; seat files were not in scope)
- ba-infra seat instructions: `org-chart/agents/instructions/ba-infra.instructions.md`
