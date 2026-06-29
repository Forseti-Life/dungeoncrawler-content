# KB Lesson: pm-dungeoncrawler executor quarantine — root cause and fix

- Date: 2026-04-20
- Reported by: ceo-copilot-2 recovery pass
- Scope: agent-exec-next.sh executor + pm-dungeoncrawler session

## Root cause

The `pm-dungeoncrawler` Copilot CLI session (`~/.copilot/wrappers/hq-pm-dungeoncrawler.session`) accumulated stale conversation context. When the executor resumed this session with `--resume`, the model responded conversationally ("Got it — task marked done...") instead of producing the required `- Status:` markdown format. This caused the response validation to fail on every retry → quarantine.

A secondary failure mode was also identified: when `--allow-all` is set, the model sometimes uses `create`/`edit` tools to write the outbox file directly instead of returning it as text response. This produces empty stdout, which the executor sees as a missing status header.

## Symptoms

- Every pm-dungeoncrawler inbox item quarantines after 3 retries
- Error: "Executor backend did not return a valid '- Status:' header"
- Raw response field in failure record is empty OR contains conversational text
- Other agents (pm-forseti, qa-dungeoncrawler) unaffected — their sessions were healthy

## Fix applied (2026-04-20)

1. **Cleared stale session**: `> ~/.copilot/wrappers/hq-pm-dungeoncrawler.session` — executor creates a new session UUID on next run
2. **Strengthened outbox output rule** in `scripts/agent-exec-next.sh` PROMPT: added explicit "OUTBOX OUTPUT RULE (CRITICAL)" warning that the outbox MUST be returned as text response, NOT written via file tools
3. **Added tool-written outbox fallback** in `scripts/agent-exec-next.sh`: if response is empty but the agent wrote a valid `- Status:` outbox file within the last 5 minutes, recover it as the response

## Prevention

- If quarantine recurs for any agent: first check `~/.copilot/wrappers/hq-<agent>.session` — clear it to reset the session
- Monitor for the "empty response + tool-written outbox" pattern in executor failure records
- Consider adding a periodic session rotation (e.g., clear session every N runs) to prevent context accumulation

## Related files

- `scripts/agent-exec-next.sh` (lines ~433 PROMPT, ~850 empty-response fallback)
- `~/.copilot/wrappers/hq-pm-dungeoncrawler.session` (cleared 2026-04-20)
