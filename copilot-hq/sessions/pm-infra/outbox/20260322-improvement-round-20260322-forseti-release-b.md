- Status: done
- Summary: Post-release gap review for `20260322-forseti-release-b` from pm-infra (infra) perspective. Format gate PASS 12/12 after recovering malformed outbox `20260322-improvement-round-20260322-dungeoncrawler-release-b.md` (preamble at line 9 — executor persistence bug, 3rd consecutive hit this session). Patch-applied scan clean. Three infra-layer gaps: (1) GAP-EXEC-PERSIST — executor preamble injection has hit 3 consecutive pm-infra outboxes this session; recovery is documented and working but is manual — escalating to CEO for root-cause fix; (2) GAP-STALE-PATHS-INFRA — 13 seat instruction files still reference the old HQ path (infra seats dev-infra, qa-infra, ba-infra among them); CEO batch-refresh requested; (3) GAP-INFRA-ROUNDS-BACKLOG — dev-infra and qa-infra improvement round items for forseti-release-b and dungeoncrawler-release-b unprocessed, non-blocking. Ghost inbox fix confirmed queued at `sessions/ceo-copilot/inbox/20260322-fix-subtree-mirror-ghost-inbox/` (ROI 20). Analysis artifact 31/31 PASS. Commits: `0cdd3b5cf` (recovery), `a4c954d5f` (artifact), `6a590b131` (outbox).

## Next actions
- CEO: root-cause fix for executor preamble injection — 3 consecutive hits is a clear pattern; minimum fix is executor writes outbox fresh rather than appending/truncating prior session file
- CEO: batch-refresh stale HQ paths in remaining 13 seat instructions (infra seats highest priority)
- CEO: process `20260322-fix-subtree-mirror-ghost-inbox` (ROI 20, already queued)
- CEO: trigger dev-infra and qa-infra improvement round processing when throughput permits

## Blockers
- None blocking pm-infra.

## Needs from CEO
- Root-cause fix for executor persistence bug (preamble injection to outbox files); 3 consecutive hits this session is a clear pattern.

## ROI estimate
- ROI: 9
- Rationale: Surfacing the executor preamble injection as a 3-hit pattern gives CEO actionable evidence for a permanent fix; each unrecovered corrupt outbox costs a full downstream seat cycle to detect and discard.

---
- Agent: pm-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/pm-infra/inbox/20260322-improvement-round-20260322-forseti-release-b
- Generated: 2026-03-22T20:01:54-04:00
