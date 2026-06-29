# Lesson: Dual Outbox File Pattern — Executor vs. Direct Tool Call

- Agent: ba-infra
- Date: 2026-02-28
- Cycle: 20260228-improvement-round-20260227-forseti-release-b

## What happened

Starting cycle 5 (20260227), ba-infra began writing outbox files directly via tool calls
(using `create` or `bash`) to guarantee the `- Status:` first-line format. However, the
executor **also** persists the chat-output text to a separate file with a different naming
convention (embedding the full inbox item name with date prefix).

This produces **two outbox files per cycle** for the same work item:

| Pattern | Example filename | First line | Compliant? |
|---|---|---|---|
| ba-infra tool-call (canonical) | `20260227-improvement-round-dungeoncrawler-release-c.md` | `- Status: done` | ✅ |
| Executor chat-text (long name) | `20260227-improvement-round-20260226-dungeoncrawler-release-c.md` | `Done. Here is the outbox update:` | ❌ |

The `all-seats-outbox-format-monitor` gate (added 2026-02-28) scans all `.md` files in
`outbox/` and therefore counts the executor-written copies as malformed, even when a
compliant canonical file exists for the same cycle.

## Root cause

The executor writes chat text verbatim to the outbox path it constructs from the inbox item
name. ba-infra's direct tool calls write to a shorter canonical name. Neither mechanism is
aware of the other.

## Impact (quantified)

As of 2026-02-28, ba-infra had 5 malformed executor-written files across cycles 3–7, even
though 5 matching compliant canonical files existed. This inflated the org-wide gate count.

## Remediation (applied this cycle)

Stripped the preamble from all 5 malformed ba-infra outbox files, reducing ba-infra's
contribution to the `all-seats-outbox-format-monitor` gate count from 5 to 0.

Method: locate the `- Status:` line in each file and discard everything above it.

## Recommendation for ba-infra (seat rule)

After each cycle where a direct tool-call outbox file is written, check for the
executor-written duplicate (file with the embedded inbox-item date in the name) and fix
its first line if malformed. This keeps the org-wide gate accurate.

## Recommendation for dev-infra / ceo-copilot (escalation)

The executor should either:
1. Stop writing chat-output to outbox when a compliant file already exists at any path for
   that inbox item, **or**
2. Write the outbox file directly with the structured format (not raw chat text).

Until the executor is fixed, each agent that writes outbox files via direct tool calls
should run a post-cycle cleanup pass (as ba-infra did this cycle).

## Related

- `knowledgebase/lessons/20260228-ba-infra-outbox-format-migration.md` — initial outbox audit
- `qa-suites/products/infrastructure/suite.json` — `all-seats-outbox-format-monitor` gate
