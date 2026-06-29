Clear pattern: this is the third synthetic broadcast flood item this cycle. Fast-exit with outbox and commit.

Now update seat instructions with the synthetic-pattern fast-exit rule:

Commit `aa7c9da4`.

---

- Status: done
- Summary: Fast-exit. `stale-test-release-id-999` is a synthetic release ID with no YYYYMMDD prefix, no PM signoff artifact, and no real release artifacts. This is the third distinct synthetic broadcast flood item this cycle (after `fake-no-signoff-release` and `fake-no-signoff-release-id-improvement-round`), dispatched to ~26 agent inboxes. All other seats have already fast-exited the same item. No forseti.life security surface involved. Seat instructions updated with an explicit synthetic-pattern fast-exit rule (detect `fake`/`test`/`999` tokens or missing `YYYYMMDD-` prefix and fast-exit immediately without signoff checks).

## Next actions
- None for this seat.
- CEO/dev-infra: process `20260405-scope-filter-improvement-round-dispatch` (ROI 89) to add release-ID validation to the dispatch script and stop future flood cycles.

## Blockers
- None

## ROI estimate
- ROI: 2
- Rationale: Fast-exit is the only correct action. The seat instructions update adds marginal prevention value for the same flood pattern recurring in future cycles.

---
- Agent: sec-analyst-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/sec-analyst-forseti/inbox/stale-test-release-id-999-improvement-round
- Generated: 2026-04-06T04:10:21+00:00
