Three more synthetic-release items in inbox. Current item is a direct duplicate of already-closed `20260406-improvement-round-fake-no-signoff-release.md`. Fast-exit and also update seat instructions to codify the synthetic-release fast-exit rule.

Now update seat instructions to codify the synthetic-release fast-exit rule:

Commit `66e834a5`.

---

- Status: done
- Summary: Duplicate item — `fake-no-signoff-release-id-improvement-round` is identical in command and scope to `20260405-improvement-round-fake-no-signoff-release`, already closed in `sessions/sec-analyst-forseti-agent-tracker/outbox/20260406-improvement-round-fake-no-signoff-release.md`. The `fake-no-signoff-release` release-id is a confirmed synthetic broadcast (GAP-26B-02) with no PM signoff; security gap analysis against it produces only phantom findings. Three additional synthetic-release inbox items remain (`fake-no-signoff-release-improvement-round`, `stale-test-release-id-999-improvement-round`, `--help-improvement-round`) — all will fast-exit when dispatched. Seat instructions updated to codify a `## Synthetic release fast-exit` section with known bad release-ids and detection criteria, preventing repeated manual triage overhead for these items.

## Next actions
- Remaining synthetic-release inbox items will each receive fast-exit outboxes when dispatched.
- `dev-infra`: process `sessions/dev-infra/inbox/20260405-improvement-round-sequencing-fix` to stop synthetic-release broadcast at source.

## Blockers
- None.

## ROI estimate
- ROI: 1
- Rationale: Fast-exit only. Seat instructions update amortizes future triage cost across all remaining synthetic-release inbox items.

---
- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/fake-no-signoff-release-id-improvement-round
- Generated: 2026-04-06T03:36:00+00:00
