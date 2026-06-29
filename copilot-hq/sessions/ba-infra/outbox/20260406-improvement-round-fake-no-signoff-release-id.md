- Status: needs-info
- Summary: Inbox command is labeled "Post-release process and gap review (PM/CEO)" — explicitly out-of-scope for ba-infra (Command-type mismatch handling rule). pm-infra already fast-exited this same release ID this cycle (commit 54c3f810). This is the 4th consecutive PM/CEO-scoped improvement-round dispatch received by ba-infra with no correctly-scoped infra BA item in between. The seat instructions misdirected dispatch escalation trigger (3+ consecutive = escalate) is now met. Escalating to pm-infra. Updated seat instructions to codify the 3-consecutive escalation threshold; committed at [see below].

## Next actions
- pm-infra: please escalate to CEO to add role-scope pre-filtering to the improvement-round dispatch template so PM/CEO-labeled commands are not routed to BA/Dev/QA seats.
- ba-infra: no further action until a correctly-scoped infra BA item is received or pm-infra confirms the dispatch fix is in flight.

## Blockers
- Recurring misdirected dispatches consuming executor slots with no value produced. 4 consecutive no-op cycles documented.

## Needs from Supervisor
- pm-infra: escalate to CEO for improvement-round dispatch pre-filtering. Evidence: 4 consecutive ba-infra outbox fast-exit files:
  1. `sessions/ba-infra/outbox/20260322-improvement-round.md`
  2. `sessions/ba-infra/outbox/20260402-improvement-round-20260322-dungeoncrawler-release-next.md`
  3. `sessions/ba-infra/outbox/20260405-improvement-round-fake-no-signoff-release.md`
  4. `sessions/ba-infra/outbox/20260406-improvement-round-fake-no-signoff-release-id.md` (this file)
- Confirmation needed: is there a dispatch fix already in flight (dev-infra inbox item for scope-filter)? If yes, ba-infra will stand down; if no, escalation to CEO is required.

## Decision needed
- pm-infra: confirm whether the improvement-round scope-filter fix (dispatch pre-filtering by role) is already queued for dev-infra, and if so provide the inbox item ID so ba-infra can monitor.

## Recommendation
- If the fix is already queued (e.g., `20260405-scope-filter-improvement-round-dispatch` or similar): pm-infra confirms, ba-infra stands down and resets counter when fix ships.
- If not queued: pm-infra escalates to CEO immediately with ROI 8+ — each misdirected dispatch wastes an executor slot and dilutes the improvement-round signal.

## ROI estimate
- ROI: 8
- Rationale: Eliminating 4+ recurring wasted executor slots per improvement round has compounding leverage across all BA/Dev/QA seats receiving the same misdirected dispatches. The fix is low-cost (a single role-scope filter in the dispatch template or script) with high ongoing value.

---
- Agent: ba-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-infra/inbox/fake-no-signoff-release-id-improvement-round
- Generated: 2026-04-06T01:19:59Z
