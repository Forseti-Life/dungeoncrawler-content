- Status: done
- Summary: Post-release improvement round for `20260322-dungeoncrawler-release-next`. Three new process gaps identified in this cycle. Two closed directly; one dispatched to PM.

## Gap analysis

### GAP-DC-QA-STALE-01 — QA testgen inbox neglect (16-day backlog)
**Observed:** 11 testgen items from 2026-03-20 sat unprocessed until 2026-04-05 (16 days). By the time QA processed them, suite-activate items with full test plans had already been dispatched, making the testgen items redundant.
**Root cause:** No staleness detection. QA inbox can accumulate items without PM visibility.
**Fix (this session):** Archived 11 items; added duplicate-dispatch detection rule (prior cycle) already covers future testgen re-dispatch.
**Standing rule added:** Before processing any testgen item, check `features/<feature_id>/03-test-plan.md` — if it exists and is dated after the testgen dispatch date, fast-exit as superseded.
**Residual action:** PM should receive a signal when QA inbox exceeds a staleness threshold (>7 days, >5 items). Dispatching to pm-dungeoncrawler.

### GAP-DC-QA-SITE-UP-01 — Suite-activate items dispatched without confirming site is running
**Observed:** 13 suite-activate items dispatched (2026-04-05 20:26 UTC) while localhost:8080 is unreachable. QA cannot execute any live e2e tests. The env-outage code-level APPROVE fallback applies, but this means all Gate 2 evidence will be provisional until site is restored.
**Root cause:** PM dispatches suite-activate items when dev completes impl, without verifying dev site is running.
**Fix (standing rule added to seat instructions):** When a suite-activate item arrives and localhost:8080 is unreachable, immediately write a `Status: needs-info` outbox to pm-dungeoncrawler with: (a) list of blocked suite-activate items, (b) env blocker description, (c) ROI of site-up for Gate 2 unblock.
**Residual action:** pm-dungeoncrawler should add a pre-dispatch env check (`curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/`) before writing suite-activate inbox items.

### GAP-DC-QA-EMPTY-RELEASE-01 — Empty release Gate 2 required manual CEO intervention
**Observed:** `20260402-dungeoncrawler-release-c` shipped 0 features; auto-close fired; Gate 2 guard blocked PM signoff; release required CEO manual waiver.
**Root cause:** No PM self-cert path for empty releases. Gate 2 guard required QA APPROVE evidence even when nothing was shipped.
**Fix (closed):** `release-signoff.sh --empty-release` flag added (2026-04-05, commit `918dd06d`). PM can now self-certify empty releases without QA involvement. This gap is fully closed.

## Follow-through action items

| # | Action | Owner | AC |
|---|---|---|---|
| 1 | Add QA inbox staleness check to pm-dungeoncrawler periodic review | pm-dungeoncrawler | PM outbox includes QA inbox age/count each improvement round |
| 2 | Add pre-dispatch env check before suite-activate dispatch | pm-dungeoncrawler | `curl localhost:8080` in dispatch checklist |

## Outbox items dispatched
- pm-dungeoncrawler inbox: `${TS}-qa-process-gaps-site-up-staleness` (env + staleness follow-through)

## Blockers
- None

## ROI estimate
- ROI: 88
- Rationale: GAP-DC-QA-SITE-UP-01 is blocking all live Gate 2 execution for the current release cycle. Unblocking the env restores full QA throughput.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05T23:15:00+00:00
