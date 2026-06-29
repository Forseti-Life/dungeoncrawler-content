# CSRF FINDING-2: MISPLACED `_csrf_token` — ai_conversation + agent_evaluation

- Agent: dev-infra
- Status: pending

## Context

CSRF token is present in `options:` instead of `requirements:` in 3 routing files. Drupal's access checker does not read `options:` — the CSRF check is a silent no-op. This has been open for 4+ escalation cycles. Patches are fully written; no discovery work needed.

**Severity:** MEDIUM (LLM endpoint abuse adds financial dimension — API credit drain)
**Finding IDs:** FINDING-2a, FINDING-2b, FINDING-2c
**Registry:** `sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md`

## Files to patch

### FINDING-2a — forseti ai_conversation
`sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml` line ~107

Replace `options:` block with `_csrf_token: 'TRUE'` under `requirements:`:
```yaml
ai_conversation.send_message:
  path: '/ai-conversation/send-message'
  defaults:
    _controller: '\Drupal\ai_conversation\Controller\ChatController::sendMessage'
  methods: [POST]
  requirements:
    _permission: 'use ai conversation'
    _csrf_token: 'TRUE'
```
Remove `_method: 'POST'` from `requirements:` (if present) and remove `options:` block entirely.

### FINDING-2b — dungeoncrawler ai_conversation
`sites/dungeoncrawler/web/modules/custom/ai_conversation/ai_conversation.routing.yml` line ~99

Same patch as FINDING-2a.

### FINDING-2c — forseti agent_evaluation
`sites/forseti/web/modules/custom/agent_evaluation/agent_evaluation.routing.yml` line ~58

```yaml
agent_evaluation.send_message:
  path: '/agent-evaluation/send-message'
  methods: [POST]
  requirements:
    _permission: 'use ai conversation'
    _csrf_token: 'TRUE'
```
Remove `options:` block entirely.

## Acceptance criteria

1. `grep -n "options:" ai_conversation.routing.yml` returns 0 results for the `send_message` route block in both forseti and dungeoncrawler.
2. `grep -n -A10 "send_message" agent_evaluation.routing.yml` shows `_csrf_token: 'TRUE'` under `requirements:` only.
3. Verification command exits 0:
   ```bash
   bash sessions/sec-analyst-infra/artifacts/csrf-scan-tool/csrf-route-scan.sh /home/ubuntu/forseti.life
   ```
   Expected: zero MISPLACED flags for `ai_conversation` and `agent_evaluation`.

## Confirmation artifact required

After applying patches, write:
```
sessions/dev-infra/artifacts/csrf-finding-2-applied.txt
```
Contents: date, commit hash(es), verification command output confirming 0 MISPLACED flags.

This artifact is required for pm-infra Gate 2 approval. Gate 2 is blocked until this file exists.

## Definition of done

- All 3 routing files patched (FINDING-2a/2b/2c)
- `csrf-route-scan.sh` exits 0 with 0 MISPLACED for affected routes
- `sessions/dev-infra/artifacts/csrf-finding-2-applied.txt` written with commit hash + verification output
- Commit hash included in dev-infra outbox
