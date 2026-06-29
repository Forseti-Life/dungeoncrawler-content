I'll work through this systematically: read the dev outbox, check acceptance criteria, run the audits, then produce the verification report.

**Step 1: Read the dev outbox and acceptance criteria.**

<tool_call>
{"name": "bash", "arguments": {"command": "cat /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/outbox/20260414-203542-impl-dc-ui-map-first-player-shell.md 2>/dev/null || echo 'FILE_NOT_FOUND'"}}
</tool_call>
<tool_response>
FILE_NOT_FOUND
</tool_response>

<tool_call>
{"name": "bash", "arguments": {"command": "ls /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/outbox/ 2>/dev/null | head -30 || echo 'DIR_NOT_FOUND'"}}
</tool_call>
<tool_response>
20260414-203542-impl-dc-ui-map-first-player-shell.md
20260414-232005-impl-dc-ui-map-first-player-shell.md
</tool_response>

<tool_call>
{"name": "bash", "arguments": {"command": "cat /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/outbox/20260414-232005-impl-dc-ui-map-first-player-shell.md"}}
</tool_call>
<tool_response>
- Status: done
- Summary: Implemented the first-player shell for the Dungeoncrawler UI map. The system prompt now receives the first player name from the `room_players` data (via `dungeoncrawler_get_room_state`), a dedicated `ForsetiUiPlayerShellService` resolves the first player name and formats it into a labeled shell `[PLAYER: <name>]`, and the existing `ForsetiUiMapBlockService` was updated to inject this label into the system prompt rendered by `dungeoncrawler-ui-map`. Unit tests were added for `ForsetiUiPlayerShellService` and updated for `ForsetiUiMapBlockService` to cover the injection logic. All added/modified tests pass.

## What was changed
- **New service**: `ForsetiUiPlayerShellService` (`forseti_ui/src/Service/ForsetiUiPlayerShellService.php`) — resolves first-player name from room state and returns formatted shell string `[PLAYER: <name>]`.
- **Updated service**: `ForsetiUiMapBlockService` (`forseti_ui/src/Service/ForsetiUiMapBlockService.php`) — injected `ForsetiUiPlayerShellService`; system prompt string now includes the player shell label via `getPlayerShell($roomState)`.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260417-unit-test-20260414-203542-impl-dc-ui-map-first-player-shell
- Generated: 2026-04-17T07:08:51+00:00
