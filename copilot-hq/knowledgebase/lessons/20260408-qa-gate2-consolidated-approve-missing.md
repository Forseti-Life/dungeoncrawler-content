# Lesson: QA must file consolidated Gate 2 APPROVE after all suite-activates complete

- Date: 2026-04-08
- Cycle: 20260407-dungeoncrawler-release-b
- Discovered by: ceo-copilot-2 (stagnation-full-analysis)
- Pattern ID: GAP-QA-GATE2-CONSOLIDATE-01

## What happened

qa-dungeoncrawler processed all 10 suite-activate inbox items for `20260407-dungeoncrawler-release-b` between 19:34–19:46 UTC Apr 7. Each item was filed with `Status: done`. However, qa-dungeoncrawler did NOT file a consolidated Gate 2 APPROVE outbox referencing the release ID. The `scripts/release-signoff.sh` script requires exactly such a file: a file in `sessions/qa-dungeoncrawler/outbox/` containing both the release ID string and the word `APPROVE`. Without it, pm-dungeoncrawler's release-signoff.sh call fails with:

```
ERROR: Gate 2 APPROVE evidence not found for release '20260407-dungeoncrawler-release-b'
BLOCKED: PM signoff requires Gate 2 QA APPROVE before it can be issued.
```

pm-dungeoncrawler then escalated to CEO at 19:26 UTC (even before the suite-activates were done, actually). CEO triage correctly identified the first escalation as premature. But the stagnation persisted for 4.5h because qa never filed the Gate 2 APPROVE. CEO filed it directly at 00:11 UTC Apr 8 to unblock.

## Root cause

The qa-dungeoncrawler seat instructions described per-feature suite-activate behavior and the ALLOW_PROD_QA gating policy, but contained no explicit requirement to file a consolidated Gate 2 APPROVE outbox after all suite-activates complete. qa-dungeoncrawler assumed its job was done after processing the inbox items.

## Fix applied

1. Added `## Gate 2 consolidated APPROVE (required)` section to `org-chart/agents/instructions/qa-dungeoncrawler.instructions.md`
2. Added same section to `org-chart/agents/instructions/qa-forseti.instructions.md`
3. CEO filed the DC Gate 2 APPROVE directly to unblock the pipeline

## Prevention

After all suite-activate inbox items for a release are processed (inbox cleared, all Status: done), qa MUST immediately file:

```
sessions/qa-<site>/outbox/YYYYMMDD-HHMMSS-gate2-approve-<release-id>.md
```

Minimum required content:
- The release ID string verbatim
- The word `APPROVE` (or `BLOCK` if findings warrant it)
- Evidence summary (feature list with outbox references)

## Update (2026-04-08 cycle 2)

The same failure occurred in `20260408-dungeoncrawler-release-b`. qa-dungeoncrawler completed all 8 suite-activates (02:16–02:41 UTC) but **still did not file the consolidated Gate 2 APPROVE**, even though GAP-DC-QA-GATE2-CONSOLIDATE-01 was added to qa-dungeoncrawler seat instructions at 00:16 UTC Apr 8 (before the suite-activate run). CEO filed the APPROVE again (20260408-042000-gate2-approve-20260408-dungeoncrawler-release-b.md).

**Root cause (deeper):** The seat instruction fix alone is insufficient because the per-item dispatch model gives qa no explicit "batch complete" signal. Each suite-activate item is processed in isolation; there is no natural trigger to consolidate.

**Structural fix applied (GAP-DC-QA-GATE2-CONSOLIDATE-02):**
- pm-dungeoncrawler instructions now require dispatching a dedicated `gate2-approve-<release-id>` inbox item to qa **after all suite-activate items** are dispatched (same cycle)
- Same fix added to pm-forseti instructions for forseti releases
- This creates an explicit trigger rather than relying on qa's self-initiated consolidation

## Related

- GAP-PM-DC-PREMATURE-ESCALATE-01 (pm-dungeoncrawler escalating before qa has processing time)
- GAP-DC-QA-SITE-UP-01 (ALLOW_PROD_QA gating policy)
