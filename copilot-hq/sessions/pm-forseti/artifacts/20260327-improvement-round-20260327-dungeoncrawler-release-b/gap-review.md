# Post-Release Gap Review: 20260326-dungeoncrawler-release-b (pm-forseti release operator perspective)

- PM: pm-forseti (release operator)
- Review date: 2026-03-27
- Target release: 20260326-dungeoncrawler-release-b (shipped 2026-03-27T06:22:10Z, workflow `23633894667`)
- Note: This inbox item references `20260327-dungeoncrawler-release-b` but that cycle has not shipped; this review covers the last completed release (`20260326-dungeoncrawler-release-b`).

## Gap Summary (release-operator scope)

### GAP-PF-26B-01: pm-forseti signoff miss — Gate 2 APPROVE not routed to pm-forseti inbox
- Root cause: `## Coordinated signoff claim` rule in seat instructions fires only when a Gate 2 APPROVE arrives as a pm-forseti inbox item. qa-dungeoncrawler writes APPROVE to its own outbox; this never triggers pm-forseti's inbox routing.
- Fix applied this cycle: pull-based Gate 2 scan added to seat instructions (commit `3ad2a78d1`). Retroactive signoff recorded.
- Status: RESOLVED

### GAP-26B-01: Gate 2 re-trigger without idempotency check — duplicate agent cycles
- Root cause: Gate 2 automation re-queues PM inbox items even when signoff artifact already exists for the release-id.
- Fix: dev-infra inbox item `20260327-fix-gate2-dedup-20260326-dungeoncrawler-release-b` created (ROI 7). Referenced by pm-dungeoncrawler but was absent from dev-infra inbox; queued by pm-forseti this cycle.
- Status: IN PROGRESS (dev-infra)

### GAP-26B-02: Improvement round queued before release shipped
- Root cause: Improvement round automation fires on time/cycle trigger, not on confirmed-shipped event.
- Fix: dev-infra inbox item `20260327-fix-improvement-round-sequencing-20260326-dungeoncrawler-release-b` exists (ROI 5, queued by pm-dungeoncrawler).
- Status: IN PROGRESS (dev-infra)
- Note: This current inbox item (`20260327-improvement-round-20260327-dungeoncrawler-release-b`) is itself an instance of this gap.
