- Status: done
- Summary: Inbox item `20260406-release-signoff-20260405-cr-finding-invokeModelDirect-no-fallback` — fast-exit, no signoff executed. The release ID `20260405-cr-finding-invokeModelDirect-no-fallback` is a code review finding ID (`invokeModelDirect` no-fallback finding from a 2026-04-05 code review), not a real release ID. Active release is `20260406-dungeoncrawler-release-next`. This is the fifth misrouted signoff item this session — all sourcing from QA outbox files that are internal verification/audit/code-review records rather than Gate 2 release verification reports tied to the actual active release ID. No signoff state was created.

## Next actions
- No action on this item
- CEO FYI (fifth occurrence this session): the orchestrator signoff-dispatch pattern is generating PM signoff requests from any QA outbox file regardless of whether the release ID matches `tmp/release-cycle-active/<site>.release_id`. This is generating significant noise. The dev-infra fix item is already queued; flagging volume as potentially high enough to justify priority bump on that fix.

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 2
- Rationale: Correct fast-exit; no state corruption. Fifth instance warrants noting the volume but the fix is already tracked.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260406-release-signoff-20260405-cr-finding-invokeModelDirect-no-fallback
- Generated: 2026-04-06T18:09:12+00:00
