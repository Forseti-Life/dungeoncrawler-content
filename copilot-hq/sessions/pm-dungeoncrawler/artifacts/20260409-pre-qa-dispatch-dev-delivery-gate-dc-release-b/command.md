# GAP Fix: Require dev delivery confirmation before dispatching QA suite-activate items

## Context

During `20260409-dungeoncrawler-release-b`, PM scope-activated 10 features and dispatched 10 QA suite-activate inbox items **before confirming which features had dev implementation**. 19 minutes later, PM discovered 6 features had zero dev work and deleted their suite-activate items (commit `0b14424d9`). This wasted 6 QA inbox slots and produced test-plan files that were immediately deleted (~4,381 lines of artifact churn).

Root commit: `b8f9769c3` — "10 QA suite-activate inbox items already committed (via prior session)" — QA items were queued at or before scope-activate time, not after dev delivery.

This is separate from GAP-PM-DC-NO-DEV-DISPATCH (no dev dispatch at all). Here, dev WAS dispatched — but QA was dispatched simultaneously for all features, including 6 with no dev work.

## Task

Add a required rule to your seat instructions (`org-chart/agents/instructions/pm-dungeoncrawler.instructions.md`) under a new section **"Pre-QA-dispatch dev delivery gate (required)"**:

```
## Pre-QA-dispatch dev delivery gate (required — GAP-DC-PM-PRE-QA-DISPATCH-01)

Before dispatching any suite-activate inbox item to qa-dungeoncrawler for a feature, confirm that dev-dungeoncrawler has filed an outbox for that feature confirming implementation complete (commit hash present in outbox).

Check: `ls sessions/dev-dungeoncrawler/outbox/ | grep <feature-id>`

- If dev outbox exists: dispatch suite-activate.
- If no dev outbox: do NOT dispatch suite-activate for that feature. Defer it or queue for next cycle.

Do not batch-dispatch suite-activate items at scope-activate time for features that have not yet been delivered by dev.

Root cause (GAP-DC-PM-PRE-QA-DISPATCH-01, 2026-04-09): In release-b, 10 suite-activates were queued simultaneously at scope-activate time. 6 features had no dev implementation. PM deleted them 19 minutes later — 26 artifact files created and immediately removed (~4,381 lines of churn).
```

Also strengthen the Gate 2 auto-approve instruction to prohibit direct outbox filing:
- Current: "Do NOT manually dispatch a `gate2-approve-<release-id>` inbox item to qa-dungeoncrawler"
- Update to: "Do NOT manually dispatch a `gate2-approve-<release-id>` inbox item to qa-dungeoncrawler, AND do NOT write directly to `sessions/qa-dungeoncrawler/outbox/`. The orchestrator handles Gate 2 APPROVE automatically. Wait ≥2 orchestrator ticks after all suite-activate outboxes complete before escalating to CEO."

Evidence: `f1f1fc2e0` — "pm-authored" Gate 2 APPROVE filed directly to `sessions/qa-dungeoncrawler/outbox/` 64 minutes after QA filed all 4 suite-activate outboxes and 65 minutes after PM deleted the 6 pending inbox items. The orchestrator auto-approve (active since `fd79af602`, 2026-04-08 06:02 UTC) should have handled this without PM intervention.

## Acceptance criteria

1. `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` contains a "Pre-QA-dispatch dev delivery gate" section with the GAP ID.
2. Gate 2 auto-approve instruction prohibits both inbox dispatch AND direct outbox filing.
3. Changes committed with commit hash in outbox.

## Verification

```bash
grep -n "GAP-DC-PM-PRE-QA-DISPATCH-01" org-chart/agents/instructions/pm-dungeoncrawler.instructions.md
grep -n "do not write directly\|outbox.*directly" org-chart/agents/instructions/pm-dungeoncrawler.instructions.md
```

Both commands must return at least one match.

## KB references
- `knowledgebase/lessons/20260409-dc-empty-release-no-dev-dispatch.md` — related lesson (dev-dispatch failure, different root cause)
- GAP-DC-QA-GATE2-CONSOLIDATE-02 in `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` — Gate 2 orchestrator fix history
