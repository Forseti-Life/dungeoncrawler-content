# Outbox update: 20260222-hq-agent-guardrails-review

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-hq-agent-guardrails-review
- Generated: 2026-02-22T08:50:04-05:00

---

Now I have enough context to write a thorough code review. Let me compile findings:

---

- Status: done
- Summary: Code review of 8 HQ agent guardrail scripts completed. The overall architecture is sound — all four executor scripts strictly gate on `agents.yaml`, `consume-forseti-replies.sh` has correct routing logic for unknown agent IDs, escalation loop prevention is well-designed, and the consolidation/prune scripts are safe. However, two Python indentation bugs were found that silently break Drupal reply consumption and idle work generation; both are swallowed by `|| true` callers and produce no visible errors. One low-severity issue exists in the prune script. No phantom-agent-creation paths were found.

## Next actions
- Fix `consume-forseti-replies.sh` Python block indentation (Finding 1 — HIGH, breaks all Drupal reply ingestion)
- Fix `idle-work-generator.sh` Python `configured_agents_tsv()` indentation (Finding 2 — MEDIUM, breaks idle work generation)
- Evaluate whether `prune-legacy-agent-tracker-rows.sh` needs to clean up dependent tables (Finding 3 — LOW)

## Blockers
- None.

## Needs from CEO
- None.

---

## Findings

**Finding 1 — HIGH BUG: `consume-forseti-replies.sh` — Python body is outside the for loop**

Lines 58–79 of the embedded Python block are at 2-space indent, but the `for r in data:` loop body uses 4-space indent. This is an `IndentationError` in Python — the unindent to 2 spaces does not match any outer indentation level. Python crashes immediately. The caller in `agent-exec-once.sh` suppresses this with `|| true`, so the script silently exits 0 with no replies ever consumed.

The entire block starting at `intended = to_agent` through `resolved.append(in_reply_to)` must be indented to 4 spaces (inside the loop). The inner `if configured and to_agent not in configured:` block should remain at 4 spaces with its body at 8 spaces.

```python
# BROKEN — line 58 at 2 spaces, causes IndentationError
for r in data:
    rid = int(r.get("id"))
    ...
    if not to_agent or not msg:
        continue

  intended = to_agent      # ← should be 4 spaces
  if configured and ...:   # ← should be 4 spaces
```

**Finding 2 — MEDIUM BUG: `idle-work-generator.sh` — `cur = {` block runs on every YAML line**

In `configured_agents_tsv()`, lines 27–34 `cur = { ... }` are at 4-space indent (inside the `for ln in ...` loop) but OUTSIDE the `if m:` block at line 24. For every YAML line that does NOT match the `- id:` pattern, `m` is `None` and `m.group(1)` on line 28 raises `AttributeError`. The `continue` at line 35 is at 8-space indent (inside `if m: if cur:`), not inside `if m:` as intended. The entire `cur = {...} continue` block should be the body of `if m:` at 8-space indent.

```python
# BROKEN
    if m:
        if cur:
            agents.append(cur)
    cur = {             # ← should be 8 spaces, inside if m:
      'id': m.group(1).strip(),  # AttributeError when m is None
```

This causes `configured_agents_tsv` to crash. `agent-exec-once.sh` calls `idle-work-generator.sh >/dev/null 2>&1 || true`, so failures are silently swallowed — idle work items are never generated.

**Finding 3 — LOW: `prune-legacy-agent-tracker-rows.sh` — only deletes from `copilot_agent_tracker_agents`, not dependent tables**

The prune script deletes from `copilot_agent_tracker_agents` but the comment acknowledges FK constraints may exist. Event/status rows for legacy agent IDs are left as orphans (or the delete silently fails if FK constraints prevent it). Not a data-loss issue for configured seats, but incomplete cleanup. Recommend verifying whether `copilot_agent_tracker_agents` has cascade deletes on related tables, or explicitly pruning event rows for deleted agent IDs.

**Finding 4 — LOW: `agent-exec-loop.sh` hardcodes CEO thread IDs**

Line 74: `ceo_agents=(ceo-copilot ceo-copilot-2 ceo-copilot-3)` is hardcoded rather than derived from `agents.yaml`. If a CEO thread is added or removed from the YAML, the loop won't reflect it without a manual script edit. Low risk (CEO seats are stable), but worth noting for consistency.

**Confirmed PASS — No phantom agent creation paths found**

- `agent-exec-next.sh`: Python seat guardrail at top validates AGENT_ID against `agents.yaml` before any execution — solid.
- `consume-forseti-replies.sh`: routing logic correctly redirects unknown `to_agent_id` values to `ceo-copilot`; no phantom `sessions/<unknown>/` dirs would be created (when the Python actually runs).
- `idle-work-generator.sh`: `create_item` checks for existing inbox dir and outbox entry before creating — idempotent by design.
- `consolidate-legacy-sessions.sh`: only moves non-configured dirs, only to confirmed-configured base seats; `--dry-run` available; safe.
- `publish-forseti-agent-tracker.sh`: strictly iterates `configured_agent_ids()` from `agents.yaml`.
